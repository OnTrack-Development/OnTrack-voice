<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const TOKEN_HASH = '60a11e1fa3f8f0ddc9b4711cff115079450e4672b8a0f3c2c1435361f4bb67f7';

function finish(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$marker = __DIR__ . '/.demo_setup_done';
if (is_file($marker)) {
    finish(['ok' => true, 'configured' => true, 'locked' => true]);
}

$token = (string)($_SERVER['HTTP_X_MAINTENANCE_TOKEN'] ?? ($_POST['token'] ?? $_GET['token'] ?? ''));
if (!hash_equals(TOKEN_HASH, hash('sha256', $token))) {
    finish(['ok' => false, 'error' => 'unauthorized'], 401);
}

$key = trim((string)($_POST['key'] ?? $_GET['key'] ?? ''));
if ($key === '' || strlen($key) < 20) {
    finish(['ok' => false, 'error' => 'invalid_key'], 422);
}

$config = [
    'app_name' => 'OnTrack Voice Demo',
    'gemini_api_key' => $key,
    'gemini_model' => 'gemini-3.5-flash-lite',
    'gemini_timeout' => 25,
    'gemini_max_output_tokens' => 500,
    'male_voice' => 'ar-EG-ShakirNeural',
    'female_voice' => 'ar-EG-SalmaNeural',
    'company_name' => 'OnTrack Development',
    'max_user_chars' => 1800,
    'max_history_items' => 10,
];

$payload = "<?php
return " . var_export($config, true) . ";
";
$path = __DIR__ . '/demo_config.local.php';
if (@file_put_contents($path, $payload, LOCK_EX) === false) {
    finish(['ok' => false, 'error' => 'cannot_write_local_config'], 500);
}
@chmod($path, 0600);
@file_put_contents($marker, gmdate('c'), LOCK_EX);
finish(['ok' => true, 'configured' => true, 'locked' => true]);
