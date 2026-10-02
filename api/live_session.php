<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'POST only'], 405);
}

if (empty($config['gemini_api_key'])) {
    json_out(['ok' => false, 'error' => 'gemini_not_configured'], 503);
}

$model = 'gemini-3.8-live';
$kbJson = json_encode($kb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

$systemInstruction = <<<PROMPT
أنت موظف صوتي مباشر لشركة OnTrack Development.
اتكلم باللهجة المصرية القاهرية الطبيعية، بشكل قصير وواضح ومهني.
انطق OnTrack: "أون تراك".
لو المستخدم قاطعك، وقف واسمعه.
استخدم قاعدة المعرفة التالية فقط في الأسعار والخدمات والفواتير، ولا تخترع بيانات.
كل الفواتير الموجودة DEMO وغير متصلة بـ WHMCS الحقيقي.
لا تطلب كلمات مرور أو بيانات بنكية.

قاعدة معرفة OnTrack:
{$kbJson}
PROMPT;

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$expireTime = $now->modify('+30 minutes')->format('Y-m-d\TH:i:s\Z');
$newSessionExpireTime = $now->modify('+2 minutes')->format('Y-m-d\TH:i:s\Z');

$tokenBody = [
    'uses' => 1,
    'expireTime' => $expireTime,
    'newSessionExpireTime' => $newSessionExpireTime,
];

$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/auth_tokens');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($tokenBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $config['gemini_api_key'],
        'User-Agent: OnTrackVoiceLive/0.4.4',
    ],
]);

$raw = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($raw === false || $http < 200 || $http >= 300) {
    $j = is_string($raw) ? json_decode($raw, true) : null;
    json_out([
        'ok' => false,
        'error' => 'ephemeral_token_failed',
        'status' => $http,
        'detail' => $curlError !== '' ? $curlError : ($j['error']['message'] ?? 'Google token service returned an error'),
        'google_status' => $j['error']['status'] ?? null,
    ], 502);
}

$data = json_decode($raw, true) ?: [];
$token = (string)($data['name'] ?? '');

if ($token === '') {
    json_out([
        'ok' => false,
        'error' => 'ephemeral_token_missing',
        'response_keys' => array_keys($data),
    ], 502);
}

json_out([
    'ok' => true,
    'token' => $token,
    'model' => $model,
    'expires_at' => $expireTime,
    'system_instruction' => $systemInstruction,
    'setup_bound_to_token' => false,
]);
