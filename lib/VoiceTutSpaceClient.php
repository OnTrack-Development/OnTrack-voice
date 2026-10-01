<?php
final class VoiceTutSpaceClient
{
    private array $spaces = [
        'https://mohammedaly22-voicetut-tts.hf.space',
        'https://ahmedffffff-voicetut-tts.hf.space',
    ];

    public function synthesize(string $text, string $speaker='Abdullah', int $steps=32, float $guidance=2.5, float $speed=0.95): array
    {
        $errors = [];
        foreach ($this->spaces as $base) {
            try {
                return $this->callSpace($base, $text, $speaker, $steps, $guidance, $speed);
            } catch (Throwable $e) {
                $errors[] = parse_url($base, PHP_URL_HOST) . ': ' . $e->getMessage();
            }
        }
        throw new RuntimeException(implode(' | ', $errors));
    }

    private function callSpace(string $base, string $text, string $speaker, int $steps, float $guidance, float $speed): array
    {
        $endpoint = rtrim($base, '/') . '/gradio_api/call/run_b_oneshot';
        $payload = [
            'data' => [
                $speaker,
                $text,
                'العربية (Egyptian)',
                max(8, min(64, $steps)),
                max(1.0, min(5.0, $guidance)),
                max(0.5, min(2.0, $speed)),
                true
            ]
        ];

        [$status,$body] = $this->request('POST', $endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), [
            'Content-Type: application/json',
            'Accept: application/json'
        ], 30);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('queue start failed HTTP ' . $status);
        }

        $j = json_decode($body, true);
        $eventId = $j['event_id'] ?? null;
        if (!is_string($eventId) || $eventId === '') {
            throw new RuntimeException('missing event_id');
        }

        [$status,$events] = $this->request('GET', $endpoint . '/' . rawurlencode($eventId), null, [
            'Accept: text/event-stream'
        ], 180);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('queue result failed HTTP ' . $status);
        }

        $resultData = $this->extractCompleteData($events);
        if ($resultData === null) {
            throw new RuntimeException('no completed audio result');
        }

        $audioUrl = $this->findAudioUrl($resultData, $base);
        if ($audioUrl === null) {
            throw new RuntimeException('audio url not found');
        }

        [$aStatus,$audio] = $this->request('GET', $audioUrl, null, ['Accept: audio/*'], 60);
        if ($aStatus < 200 || $aStatus >= 300 || strlen($audio) < 100) {
            throw new RuntimeException('audio download failed HTTP ' . $aStatus);
        }

        return [
            'audio' => $audio,
            'mime' => $this->guessMime($audioUrl, $audio),
            'space' => parse_url($base, PHP_URL_HOST),
            'speaker' => $speaker,
        ];
    }

    private function extractCompleteData(string $sse): mixed
    {
        $lines = preg_split('/\r?\n/', $sse) ?: [];
        $lastData = null;
        $event = null;
        foreach ($lines as $line) {
            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
                continue;
            }
            if (str_starts_with($line, 'data:')) {
                $raw = trim(substr($line, 5));
                $decoded = json_decode($raw, true);
                if ($decoded !== null) $lastData = $decoded;
                if ($event === 'complete' && $decoded !== null) return $decoded;
            }
        }
        return $lastData;
    }

    private function findAudioUrl(mixed $node, string $base): ?string
    {
        if (is_string($node)) {
            if (preg_match('#^https?://#i', $node) && preg_match('#(wav|mp3|ogg|audio|file=)#i', $node)) return $node;
            if (str_starts_with($node, '/gradio_api/file=')) return rtrim($base,'/') . $node;
            return null;
        }
        if (!is_array($node)) return null;

        if (isset($node['url']) && is_string($node['url']) && preg_match('#^https?://#i', $node['url'])) {
            return $node['url'];
        }

        if (isset($node['path']) && is_string($node['path'])) {
            $path = $node['path'];
            if (isset($node['url']) && is_string($node['url'])) return $node['url'];
            return rtrim($base,'/') . '/gradio_api/file=' . rawurlencode($path);
        }

        foreach ($node as $child) {
            $found = $this->findAudioUrl($child, $base);
            if ($found !== null) return $found;
        }
        return null;
    }

    private function request(string $method, string $url, ?string $body, array $headers, int $timeout): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'OnTrackVoiceDemo/VoiceTutSpace',
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $body ?? '';
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) throw new RuntimeException($err ?: 'curl failed');
        return [$status, $resp];
    }

    private function guessMime(string $url, string $audio): string
    {
        if (preg_match('/\.mp3(?:\?|$)/i', $url)) return 'audio/mpeg';
        if (preg_match('/\.ogg(?:\?|$)/i', $url)) return 'audio/ogg';
        if (substr($audio,0,4) === 'RIFF') return 'audio/wav';
        if (substr($audio,0,3) === 'ID3' || (strlen($audio)>2 && ord($audio[0])===0xFF)) return 'audio/mpeg';
        return 'audio/wav';
    }
}
