<?php
$config = require __DIR__ . '/api/demo_config.php';
$checks = [];
$checks[] = ['PHP version >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>=')];
$checks[] = ['cURL enabled - Gemini', extension_loaded('curl')];
$checks[] = ['mbstring enabled', extension_loaded('mbstring')];
$checks[] = ['OpenSSL enabled - Edge fallback', extension_loaded('openssl')];
$checks[] = ['stream_socket_client enabled - Edge fallback', function_exists('stream_socket_client')];
$checks[] = ['knowledge.json readable', is_readable(__DIR__ . '/data/knowledge.json')];
$checks[] = ['Gemini API key configured', !empty($config['gemini_api_key'])];
$checks[] = ['Brain = Gemini 3.8 Flash', ($config['gemini_model'] ?? '') === 'gemini-3.8-flash'];
$checks[] = ['Voice API key required = NO', true];
$checks[] = ['HTTPS for microphone', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'];
?><!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>OnTrack Voice Test</title><style>body{font-family:system-ui;background:#090b10;color:#eee;max-width:850px;margin:40px auto;padding:20px}.ok{color:#48d597}.bad{color:#ff6b6b}code{direction:ltr;display:inline-block}a{color:#8ec5ff}</style></head><body>
<h1>OnTrack Voice v0.3.5 — فحص الاستضافة</h1>
<?php foreach($checks as [$name,$ok]): ?><p class="<?= $ok?'ok':'bad' ?>">[<?= $ok?'OK':'FAIL' ?>] <?= htmlspecialchars($name) ?></p><?php endforeach; ?>
<p>العقل: <code><?= htmlspecialchars($config['gemini_model']) ?></code></p>
<p>الصوت: Device TTS + Edge المصري بدون Voice API Key.</p>
<p><a href="index.php">فتح الديمو</a></p>
</body></html>
