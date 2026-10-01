<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../lib/EdgeTtsClient.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'POST only'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$text = clean_text((string)($payload['text'] ?? ''), 2200);
$gender = ($payload['gender'] ?? 'male') === 'female' ? 'female' : 'male';
$rate = (string)($payload['rate'] ?? '-4%');

if ($text === '') json_out(['ok' => false, 'error' => 'empty_text'], 422);

$text = preg_replace('/\s*([،,.!?؟])\s*/u', '$1 ', $text) ?? $text;
$text = preg_replace('/\s+/u', ' ', trim($text)) ?? $text;

$voice = $gender === 'female' ? $config['female_voice'] : $config['male_voice'];

try {
    $client = new EdgeTtsClient(25);
    $audio = $client->synthesize($text, $voice, $rate);

    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . strlen($audio));
    header('Cache-Control: no-store');
    header('X-TTS-Engine: edge-read-aloud-no-key');
    header('X-TTS-Voice: ' . $voice);
    echo $audio;
} catch (Throwable $e) {
    json_out([
        'ok' => false,
        'fallback' => 'device',
        'error' => 'edge_tts_failed',
        'detail' => mb_substr($e->getMessage(), 0, 500, 'UTF-8'),
    ], 502);
}
