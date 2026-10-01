<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'POST only'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$text = clean_text((string)($payload['text'] ?? ''), 2200);
$gender = ($payload['gender'] ?? 'male') === 'female' ? 'female' : 'male';
$rate = (string)($payload['rate'] ?? '+0%');

if ($text === '') {
    json_out(['ok' => false, 'error' => 'empty_text'], 422);
}
if (empty($config['gemini_api_key'])) {
    json_out(['ok' => false, 'error' => 'gemini_not_configured'], 503);
}

$voice = $gender === 'female'
    ? (string)$config['female_voice']
    : (string)$config['male_voice'];

$pace = match ($rate) {
    '-8%' => 'أبطأ سنة بسيطة من الكلام العادي',
    '+8%' => 'أسرع سنة بسيطة من الكلام العادي',
    default => 'بسرعة كلام طبيعية',
};

$style = $gender === 'female'
    ? 'صوت ست مصرية من القاهرة، دافي وطبيعي ومهني'
    : 'صوت راجل مصري من القاهرة، ودود وطبيعي ومهني';

$prompt = <<<PROMPT
اقرأ النص التالي فقط، من غير ما تضيف أو تحذف أي كلمة.
الأداء: {$style}.
اللهجة: مصرية قاهرية يومية واضحة، مش فصحى إذا كان النص مصري.
الإيقاع: {$pace}، بتوقفات قصيرة طبيعية بين الجمل، ومن غير نبرة مذيع أو نبرة روبوت.
الإحساس: موظف خدمة عملاء حقيقي بيتكلم في مكالمة تليفون عادية، هادي وواثق ومن غير مبالغة.
انطق "OnTrack" بالعربي "أون تراك".

النص:
{$text}
PROMPT;

$body = [
    'model' => (string)$config['tts_model'],
    'input' => $prompt,
    'response_format' => [
        'type' => 'audio',
        'mime_type' => 'audio/wav',
        'sample_rate' => 24000,
        'delivery' => 'inline',
    ],
    'generation_config' => [
        'speech_config' => [
            ['voice' => $voice],
        ],
    ],
];

$url = 'https://generativelanguage.googleapis.com/v1beta/interactions';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => (int)($config['tts_timeout'] ?? 45),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $config['gemini_api_key'],
        'Api-Revision: 2026-05-20',
        'User-Agent: OnTrackVoiceDemo/0.3.4',
    ],
]);

$raw = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($raw === false || $http < 200 || $http >= 300) {
    json_out([
        'ok' => false,
        'error' => 'gemini_tts_failed',
        'status' => $http,
        'detail' => $curlError !== '' ? $curlError : safe_google_error($raw),
    ], 502);
}

$data = json_decode($raw, true);

$audioData = null;
$mime = 'audio/wav';

$walk = function ($node) use (&$walk, &$audioData, &$mime) {
    if ($audioData !== null) return;
    if (!is_array($node)) return;

    if (isset($node['output_audio']) && is_array($node['output_audio'])) {
        $a = $node['output_audio'];
        if (!empty($a['data']) && is_string($a['data'])) {
            $audioData = $a['data'];
            if (!empty($a['mime_type'])) $mime = (string)$a['mime_type'];
            return;
        }
    }

    if (($node['type'] ?? null) === 'audio' && !empty($node['data']) && is_string($node['data'])) {
        $audioData = $node['data'];
        if (!empty($node['mime_type'])) $mime = (string)$node['mime_type'];
        return;
    }

    foreach ($node as $child) {
        if (is_array($child)) $walk($child);
        if ($audioData !== null) return;
    }
};
$walk($data);

if (!$audioData) {
    json_out([
        'ok' => false,
        'error' => 'gemini_tts_no_audio',
        'detail' => 'No audio block was found in the Gemini response',
    ], 502);
}

$audio = base64_decode($audioData, true);
if ($audio === false || strlen($audio) < 100) {
    json_out(['ok' => false, 'error' => 'invalid_audio_payload'], 502);
}

header('Content-Type: ' . ($mime ?: 'audio/wav'));
header('Content-Length: ' . strlen($audio));
header('Cache-Control: no-store');
header('X-TTS-Engine: gemini-3.8-flash-tts');
header('X-TTS-Voice: ' . $voice);
echo $audio;
