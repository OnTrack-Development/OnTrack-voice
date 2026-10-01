<?php
/**
 * Minimal Microsoft Edge Read Aloud TTS client for upload-only shared hosting.
 * No Azure account / no voice API key.
 * Uses the public Edge Read Aloud WebSocket protocol.
 */
final class EdgeTtsClient
{
    private const HOST = 'speech.platform.bing.com';
    private const PATH = '/consumer/speech/synthesize/readaloud/edge/v1';
    private const TRUSTED_CLIENT_TOKEN = '6A5AA1D4EAFF4E9FB37E23D68491D6F4';
    private const SEC_MS_GEC_VERSION = '1-143.0.3650.75';
    private const ORIGIN = 'chrome-extension://jdiccldimpdaibmpdkjnbmckianbfold';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36';

    private $socket = null;
    private int $timeout;

    public function __construct(int $timeout = 25)
    {
        $this->timeout = max(5, min(45, $timeout));
    }

    public function synthesize(string $text, string $voice, string $rate = '+0%'): string
    {
        if (!function_exists('stream_socket_client')) {
            throw new RuntimeException('stream_socket_client is disabled on this hosting account');
        }
        if (!extension_loaded('openssl')) {
            throw new RuntimeException('OpenSSL extension is required');
        }

        $text = trim($text);
        if ($text === '') throw new InvalidArgumentException('Text is empty');

        $this->connect();
        try {
            $requestId = bin2hex(random_bytes(16));
            $timestamp = gmdate('D, d M Y H:i:s') . ' GMT';
            $outputFormat = 'audio-24khz-48kbitrate-mono-mp3';

            $config = "X-Timestamp:{$timestamp}
Content-Type:application/json; charset=utf-8
Path:speech.config

" .
                json_encode([
                    'context' => [
                        'synthesis' => [
                            'audio' => [
                                'metadataoptions' => [
                                    'sentenceBoundaryEnabled' => false,
                                    'wordBoundaryEnabled' => false,
                                ],
                                'outputFormat' => $outputFormat,
                            ],
                        ],
                    ],
                ], JSON_UNESCAPED_SLASHES);

            $safeText = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $safeVoice = htmlspecialchars($voice, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $safeRate = preg_match('/^[+-]?d{1,3}%$/', $rate) ? $rate : '+0%';
            $ssml = '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="ar-EG">' .
                '<voice name="' . $safeVoice . '"><prosody pitch="+0Hz" rate="' . $safeRate . '" volume="+0%">' .
                $safeText . '</prosody></voice></speak>';

            $speech = "X-RequestId:{$requestId}
Content-Type:application/ssml+xml
X-Timestamp:{$timestamp}
Path:ssml

{$ssml}";

            $this->sendFrame($config, 0x1);
            $this->sendFrame($speech, 0x1);

            $audio = '';
            $started = microtime(true);
            while (microtime(true) - $started < $this->timeout) {
                $frame = $this->readFrame();
                if ($frame === null) break;

                $opcode = $frame['opcode'];
                $payload = $frame['payload'];

                if ($opcode === 0x9) {
                    $this->sendFrame($payload, 0xA);
                    continue;
                }
                if ($opcode === 0x8) break;

                if ($opcode === 0x1) {
                    if (strpos($payload, 'Path:turn.end') !== false) break;
                    continue;
                }

                if ($opcode === 0x2) {
                    if (strlen($payload) < 2) continue;
                    $headerLength = unpack('nlen', substr($payload, 0, 2))['len'] ?? 0;
                    $audioOffset = 2 + (int)$headerLength;
                    if ($audioOffset <= strlen($payload)) {
                        $headers = substr($payload, 2, (int)$headerLength);
                        if (stripos($headers, 'Path:audio') !== false) {
                            $chunk = substr($payload, $audioOffset);
                            if ($chunk !== '') $audio .= $chunk;
                        }
                    }
                }
            }

            if ($audio === '') {
                throw new RuntimeException('Edge TTS returned no audio');
            }
            return $audio;
        } finally {
            $this->close();
        }
    }

    private function connect(): void
    {
        $secGec = $this->generateSecMsGec();
        $muid = strtoupper(bin2hex(random_bytes(16)));
        $connectionId = bin2hex(random_bytes(16));
        $query = http_build_query([
            'TrustedClientToken' => self::TRUSTED_CLIENT_TOKEN,
            'ConnectionId' => $connectionId,
            'Sec-MS-GEC' => $secGec,
            'Sec-MS-GEC-Version' => self::SEC_MS_GEC_VERSION,
        ], '', '&', PHP_QUERY_RFC3986);

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => self::HOST,
                'SNI_enabled' => true,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            'tls://' . self::HOST . ':443',
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!$socket) {
            throw new RuntimeException('Cannot connect to Edge TTS: ' . ($errstr ?: 'connection failed') . ' (' . $errno . ')');
        }
        stream_set_timeout($socket, $this->timeout);
        stream_set_blocking($socket, true);
        $this->socket = $socket;

        $key = base64_encode(random_bytes(16));
        $request = "GET " . self::PATH . "?{$query} HTTP/1.1
" .
            "Host: " . self::HOST . "
" .
            "Upgrade: websocket
" .
            "Connection: Upgrade
" .
            "Sec-WebSocket-Key: {$key}
" .
            "Sec-WebSocket-Version: 13
" .
            "Origin: " . self::ORIGIN . "
" .
            "User-Agent: " . self::USER_AGENT . "
" .
            "Pragma: no-cache
" .
            "Cache-Control: no-cache
" .
            "Cookie: muid={$muid};

";

        $this->writeAll($request);
        $response = $this->readHttpHeaders();
        if (!preg_match('#^HTTP/1.[01]s+101#', $response)) {
            $line = strtok($response, "
") ?: 'unknown response';
            throw new RuntimeException('Edge WebSocket handshake failed: ' . $line);
        }

        if (preg_match('/^Sec-WebSocket-Accept:s*(.+)$/mi', $response, $m)) {
            $expected = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
            if (trim($m[1]) !== $expected) {
                throw new RuntimeException('Invalid WebSocket accept key');
            }
        }
    }

    private function generateSecMsGec(): string
    {
        $ticks = time() + 11644473600;
        $ticks -= $ticks % 300;
        $windowsTicks = $ticks * 10000000;
        return strtoupper(hash('sha256', (string)$windowsTicks . self::TRUSTED_CLIENT_TOKEN));
    }

    private function sendFrame(string $payload, int $opcode): void
    {
        if (!is_resource($this->socket)) throw new RuntimeException('WebSocket is not connected');
        $length = strlen($payload);
        $head = chr(0x80 | ($opcode & 0x0F));
        $maskBit = 0x80;
        if ($length <= 125) {
            $head .= chr($maskBit | $length);
        } elseif ($length <= 65535) {
            $head .= chr($maskBit | 126) . pack('n', $length);
        } else {
            $head .= chr($maskBit | 127) . pack('NN', 0, $length);
        }

        $mask = random_bytes(4);
        $masked = '';
        for ($i = 0; $i < $length; $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }
        $this->writeAll($head . $mask . $masked);
    }

    private function readFrame(): ?array
    {
        $first = $this->readExact(2);
        if ($first === null || strlen($first) < 2) return null;
        $b1 = ord($first[0]);
        $b2 = ord($first[1]);
        $opcode = $b1 & 0x0F;
        $masked = ($b2 & 0x80) !== 0;
        $length = $b2 & 0x7F;

        if ($length === 126) {
            $ext = $this->readExact(2);
            if ($ext === null) return null;
            $length = unpack('nlen', $ext)['len'];
        } elseif ($length === 127) {
            $ext = $this->readExact(8);
            if ($ext === null) return null;
            $parts = unpack('Nhigh/Nlow', $ext);
            if (($parts['high'] ?? 0) !== 0) throw new RuntimeException('WebSocket frame too large');
            $length = (int)($parts['low'] ?? 0);
        }

        if ($length > 20 * 1024 * 1024) throw new RuntimeException('WebSocket frame exceeds safety limit');
        $mask = $masked ? $this->readExact(4) : null;
        $payload = $length > 0 ? $this->readExact($length) : '';
        if ($payload === null) return null;

        if ($masked && $mask !== null) {
            $unmasked = '';
            for ($i = 0; $i < strlen($payload); $i++) {
                $unmasked .= $payload[$i] ^ $mask[$i % 4];
            }
            $payload = $unmasked;
        }
        return ['opcode' => $opcode, 'payload' => $payload];
    }

    private function readHttpHeaders(): string
    {
        $buffer = '';
        $limit = 65536;
        while (strlen($buffer) < $limit && strpos($buffer, "

") === false) {
            $chunk = fread($this->socket, 2048);
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);
                if (!empty($meta['timed_out'])) throw new RuntimeException('WebSocket handshake timed out');
                if (feof($this->socket)) break;
                usleep(10000);
                continue;
            }
            $buffer .= $chunk;
        }
        return $buffer;
    }

    private function readExact(int $length): ?string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->socket, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);
                if (!empty($meta['timed_out']) || feof($this->socket)) return null;
                usleep(10000);
                continue;
            }
            $data .= $chunk;
        }
        return $data;
    }

    private function writeAll(string $data): void
    {
        $written = 0;
        $length = strlen($data);
        while ($written < $length) {
            $n = fwrite($this->socket, substr($data, $written));
            if ($n === false || $n === 0) throw new RuntimeException('Failed writing to WebSocket');
            $written += $n;
        }
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
    }
}
