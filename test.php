<?php
$config = require __DIR__ . '/api/demo_config.php';
$checks = [
  ['PHP >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>=')],
  ['cURL enabled', extension_loaded('curl')],
  ['Knowledge base readable', is_readable(__DIR__ . '/data/knowledge.json')],
  ['Gemini key configured', !empty($config['gemini_api_key'])],
  ['Live session endpoint exists', is_readable(__DIR__ . '/api/live_session.php')],
  ['HTTPS for microphone', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'],
];
?><!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>OnTrack Live Test</title><style>body{font-family:system-ui;background:#090b10;color:#eee;max-width:850px;margin:40px auto;padding:20px}.ok{color:#48d597}.bad{color:#ff6b6b}code{direction:ltr;display:inline-block}a{color:#8ec5ff}</style></head><body>
<h1>OnTrack Voice v0.4.0 — Gemini 3.8 Live</h1>
<?php foreach($checks as [$name,$ok]): ?><p class="<?= $ok?'ok':'bad' ?>">[<?= $ok?'OK':'FAIL' ?>] <?= htmlspecialchars($name) ?></p><?php endforeach; ?>
<p>Architecture: <code>Browser PCM → Gemini 3.8 Live WebSocket → Native Audio PCM 24kHz</code></p>
<p>Legacy VoiceTut / Edge / Browser SpeechRecognition: <strong>OFF</strong></p>
<p><a href="index.php">فتح المكالمة</a></p>
</body></html>
