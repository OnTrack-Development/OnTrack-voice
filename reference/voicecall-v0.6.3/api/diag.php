<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const TOKEN_HASH = '60a11e1fa3f8f0ddc9b4711cff115079450e4672b8a0f3c2c1435361f4bb67f7';

function out(array $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    exit;
}

function curl_req(string $method, string $url, array $headers=[], ?string $body=null, int $timeout=25): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => 'OnTrackVoiceDiag/0.3.7',
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $body ?? '';
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, is_string($raw)?$raw:'', $ctype, $err];
}

$token=(string)($_SERVER['HTTP_X_MAINTENANCE_TOKEN'] ?? ($_GET['token'] ?? ''));
if (!hash_equals(TOKEN_HASH, hash('sha256',$token))) out(['ok'=>false,'error'=>'unauthorized'],401);

$config = require __DIR__ . '/demo_config.php';
$key = (string)($config['gemini_api_key'] ?? '');

$result = [
    'ok' => true,
    'php' => PHP_VERSION,
    'curl' => extension_loaded('curl'),
    'gemini_model' => $config['gemini_model'] ?? null,
    'gemini_key_present' => strlen($key) > 20,
    'gemini' => null,
    'voicetut' => [],
];

if ($key !== '') {
    $body = json_encode([
        'model' => 'gemini-3.8-flash',
        'input' => 'رد بكلمة تمام فقط',
        'generation_config' => [
            'thinking_level' => 'low',
            'max_output_tokens' => 32,
        ],
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

    [$code,$raw,$ctype,$err] = curl_req(
        'POST',
        'https://generativelanguage.googleapis.com/v1beta/interactions',
        ['Content-Type: application/json','x-goog-api-key: '.$key],
        $body,
        30
    );

    $j = json_decode($raw,true);
    $safe = null;
    if (is_array($j)) {
        if (isset($j['error'])) {
            $safe = [
                'code' => $j['error']['code'] ?? null,
                'status' => $j['error']['status'] ?? null,
                'message' => $j['error']['message'] ?? null,
            ];
        } else {
            $text='';
            foreach (($j['steps'] ?? []) as $step) {
                if (($step['type'] ?? '') !== 'model_output') continue;
                foreach (($step['content'] ?? []) as $part) {
                    if (($part['type'] ?? '') === 'text') $text .= (string)($part['text'] ?? '');
                }
            }
            $safe = [
                'id' => $j['id'] ?? null,
                'status' => $j['status'] ?? null,
                'text' => mb_substr(trim($text),0,120,'UTF-8'),
            ];
        }
    }

    $result['gemini'] = [
        'http' => $code,
        'content_type' => $ctype,
        'curl_error' => $err ?: null,
        'response' => $safe ?? mb_substr($raw,0,500,'UTF-8'),
    ];
}

$targets = [
    'hf_space_meta' => 'https://huggingface.co/api/spaces/mohammedaly22/VoiceTut-TTS',
    'hf_space_config' => 'https://mohammedaly22-voicetut-tts.hf.space/config',
    'hf_gradio_info' => 'https://mohammedaly22-voicetut-tts.hf.space/gradio_api/info',
];

foreach ($targets as $name=>$url) {
    [$code,$raw,$ctype,$err] = curl_req('GET',$url,['Accept: application/json'],null,20);
    $extra = null;
    $j = json_decode($raw,true);
    if ($name === 'hf_space_meta' && is_array($j)) {
        $extra = [
            'id' => $j['id'] ?? null,
            'sdk' => $j['sdk'] ?? null,
            'stage' => $j['runtime']['stage'] ?? ($j['stage'] ?? null),
            'hardware' => $j['runtime']['hardware']['current'] ?? null,
        ];
    } elseif ($name === 'hf_gradio_info' && is_array($j)) {
        $eps = array_keys($j['named_endpoints'] ?? []);
        $extra = ['named_endpoints' => array_slice($eps,0,30)];
    }

    $result['voicetut'][$name] = [
        'http' => $code,
        'content_type' => $ctype,
        'curl_error' => $err ?: null,
        'info' => $extra,
        'body_preview' => $extra ? null : mb_substr($raw,0,250,'UTF-8'),
    ];
}

out($result);
