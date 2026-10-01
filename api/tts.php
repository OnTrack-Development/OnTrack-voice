<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../lib/VoiceTutSpaceClient.php';
require __DIR__ . '/../lib/EdgeTtsClient.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'POST only'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$text = clean_text((string)($payload['text'] ?? ''), 1800);
$speaker = trim((string)($payload['speaker'] ?? 'Abdullah'));
$rate = (string)($payload['rate'] ?? '-4%');

$allowed = [
    'Abdelrahman','Abdullah','Kamal','Hossam','Mohamed','Omar','Sayed','Zaki','Aly','Essam','Ahmed',
    'Asmaa','Esraa','Hanan','Sarah','Yasmin','Omnia'
];
if (!in_array($speaker, $allowed, true)) $speaker = 'Abdullah';
if ($text === '') json_out(['ok' => false, 'error' => 'empty_text'], 422);

try {
    $vt = new VoiceTutSpaceClient();
    $result = $vt->synthesize($text, $speaker, 32, 2.5, 0.95);

    header('Content-Type: ' . $result['mime']);
    header('Content-Length: ' . strlen($result['audio']));
    header('Cache-Control: no-store');
    header('X-TTS-Engine: VoiceTut-HF-Space');
    header('X-TTS-Speaker: ' . $result['speaker']);
    header('X-TTS-Space: ' . $result['space']);
    echo $result['audio'];
    exit;
} catch (Throwable $voiceTutError) {
    // Fallback only, so the call can continue if the free Space is sleeping or queued.
    $gender = in_array($speaker, ['Asmaa','Esraa','Hanan','Sarah','Yasmin','Omnia'], true) ? 'female' : 'male';
    $voice = $gender === 'female' ? $config['female_voice'] : $config['male_voice'];

    try {
        $edge = new EdgeTtsClient(25);
        $audio = $edge->synthesize($text, $voice, $rate);
        header('Content-Type: audio/mpeg');
        header('Content-Length: ' . strlen($audio));
        header('Cache-Control: no-store');
        header('X-TTS-Engine: Edge-Fallback');
        header('X-VoiceTut-Error: ' . rawurlencode(mb_substr($voiceTutError->getMessage(),0,180,'UTF-8')));
        echo $audio;
        exit;
    } catch (Throwable $edgeError) {
        json_out([
            'ok' => false,
            'error' => 'all_tts_failed',
            'voicetut' => mb_substr($voiceTutError->getMessage(),0,500,'UTF-8'),
            'edge' => mb_substr($edgeError->getMessage(),0,500,'UTF-8'),
        ], 502);
    }
}
