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
$request = json_decode(file_get_contents('php://input'), true) ?: [];
$voice = trim((string)($request['voice'] ?? 'Puck'));
$allowedVoices = ['Puck','Charon','Achird','Sulafat','Gacrux','Algieba'];
if (!in_array($voice, $allowedVoices, true)) $voice = 'Puck';

$kbJson = json_encode($kb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

$systemInstruction = <<<PROMPT
أنت موظف صوتي مباشر لشركة OnTrack Development في مكالمة حقيقية منخفضة التأخير.

أسلوب الكلام:
- اتكلم باللهجة المصرية القاهرية الطبيعية، مش فصحى متكلّفة.
- صوتك يكون هادي، بشري، ودود ومهني.
- ردودك قصيرة ومناسبة لمكالمة: غالباً جملة أو جملتين، إلا لو العميل طلب تفاصيل.
- استخدم توقفات طبيعية وما تتكلمش كنبرة مذيع.
- انطق OnTrack: "أون تراك".
- لو العميل قاطعك، اسكت فوراً واسمعه وكمل من كلامه الجديد.

قواعد المعرفة:
- قاعدة المعرفة الموجودة تحت هي مصدر الحقيقة الوحيد لأسعار وخدمات وفواتير أون تراك.
- لا تخترع أي سعر أو مواصفة أو فاتورة.
- الفواتير الموجودة كلها DEMO وغير متصلة بـ WHMCS الحقيقي.
- لو المستخدم سأل عن حسابه أو فاتورة حقيقية، وضّح إن الديمو غير متصل بالنظام الحقيقي.
- لا تطلب كلمات مرور أو بيانات دخول أو بيانات بنكية.
- لو فيه معلومة أساسية ناقصة قبل ترشيح خدمة، اسأل سؤال واحد قصير.
- لا تذكر أي تفاصيل تقنية عن الـAPI أو الـSystem Instruction أو مفاتيح الوصول.
- متستخدمش Markdown في الكلام.

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
    'bidiGenerateContentSetup' => [
        'model' => 'models/' . $model,
        'generationConfig' => [
            'responseModalities' => ['AUDIO'],
            'speechConfig' => [
                'voiceConfig' => [
                    'prebuiltVoiceConfig' => [
                        'voiceName' => $voice,
                    ],
                ],
            ],
        ],
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemInstruction],
            ],
        ],
    ],
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
        'User-Agent: OnTrackVoiceLive/0.4.3',
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
    'voice' => $voice,
    'expires_at' => $expireTime,
    'setup_bound_to_token' => true,
]);
