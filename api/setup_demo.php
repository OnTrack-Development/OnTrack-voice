<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/MaintenanceAuth.php';
ontrack_maintenance_require('POST');

function finish(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Never accept maintenance credentials or API keys in a URL/query string.
$key = trim((string)($_POST['key'] ?? ''));
if ($key === '' || strlen($key) < 20) {
    finish(['ok' => false, 'error' => 'invalid_key'], 422);
}

$config = [
    'app_name' => 'OnTrack Voice Demo',
    'gemini_api_key' => $key,
    'gemini_model' => 'gemini-3.8-flash',
    'gemini_timeout' => 30,
    'gemini_max_output_tokens' => 700,
    'gemini_thinking_level' => 'low',
    'tts_model' => 'gemini-3.8-flash-tts',
    'male_voice' => 'Charon',
    'female_voice' => 'Sulafat',
    'tts_timeout' => 45,
    'company_name' => 'OnTrack Development',
    'max_user_chars' => 1800,
    'max_history_items' => 10,
];

$payload = "<?php
return " . var_export($config, true) . ";
";
// Stage the replacement privately and commit it atomically. Keep any
// legacy config untouched for rollback; never write new keys into webroot.
$dir = '/home/appontrack/.ontrack-demo';
if ((!is_dir($dir) && !@mkdir($dir,0700,true)) || is_link($dir)) {
    finish(['ok' => false, 'error' => 'private_config_unavailable'], 503);
}
@chmod($dir,0700);
$path=$dir.'/demo_config.php';
if (is_link($path))finish(['ok' => false, 'error' => 'private_config_unavailable'], 503);
$temp=@tempnam($dir,'.ontrack-demo-');
if ($temp===false)finish(['ok' => false, 'error' => 'private_config_unavailable'], 503);
try {
    if (@file_put_contents($temp,$payload,LOCK_EX)!==strlen($payload) ||
        !@chmod($temp,0600) || !@rename($temp,$path)) {
        finish(['ok' => false, 'error' => 'cannot_write_local_config'], 500);
    }
} finally {
    if (is_file($temp)) @unlink($temp);
}

finish([
    'ok' => true,
    'configured' => true,
    'brain' => 'gemini-3.8-flash',
    'tts' => 'gemini-3.8-flash-tts',
]);
