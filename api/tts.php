<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../lib/EdgeTtsClient.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'POST only'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$text = clean_text((string)($payload['text'] ?? ''), 1800);
$speaker = trim((string)($payload['speaker'] ?? 'Abdullah'));
$rate = (string)($payload['rate'] ?? '-4%');

if ($text === '') json_out(['ok' => false, 'error' => 'empty_text'], 422);

$female = ['Asmaa','Esraa','Hanan','Sarah','Yasmin','Omnia'];
$gender = in_array($speaker, $female, true) ? 'female' : 'male';
$voice = $gender === 'female' ? $config['female_voice'] : $config['male_voice'];

try {
    $edge = new EdgeTtsClient(25);
    $audio = $edge->synthesize($text, $voice, $rate);
    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . strlen($audio));
    header('Cache-Control: no-store');
    header('X-TTS-Engine: Edge-Egyptian-Fallback');
    header('X-TTS-Voice: ' . $voice);
    echo $audio;
} catch (Throwable $e) {
    json_out([
        'ok' => false,
        'error' => 'edge_tts_failed',
        'detail' => mb_substr($e->getMessage(),0,500,'UTF-8'),
    ], 502);
}
