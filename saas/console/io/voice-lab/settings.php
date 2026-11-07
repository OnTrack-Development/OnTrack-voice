<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';
header('Content-Type: application/json; charset=utf-8');
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET','POST'], true)) {
    header('Allow: GET, POST');
    voice_lab_error(405, 'method_not_allowed');
}
$user = voice_lab_access($method === 'POST');
$uid = (int)$user['id'];
if ($method === 'GET') {
    try {
        $data = voice_lab_read($uid);
        $saved = [
            'fish' => is_string($data['keys']['fish'] ?? null) && unseal_secret($data['keys']['fish']) !== null,
            'google' => is_string($data['keys']['google'] ?? null) && unseal_secret($data['keys']['google']) !== null,
        ];
        echo json_encode(['ok'=>true, 'csrf'=>csrf(), 'settings'=>$data['settings'], 'saved'=>$saved],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        voice_lab_error(503, 'settings_read_failed');
    }
    exit;
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 12000) voice_lab_error(413, 'request_too_large');
$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload) || !is_array($payload['keys'] ?? null)
    || !is_array($payload['settings'] ?? null)) voice_lab_error(400, 'invalid_json');

$allowed = [
    'emotionLevel' => ['intense','expressive','subtle'],
    'autoListen' => ['no','yes'],
    'fishModel' => ['drama-3','s2.1-pro-free','s2.1-pro'],
];
$preferences = [];
foreach ($allowed as $name => $options) {
    $value = $payload['settings'][$name] ?? ($name === 'fishModel' ? 'drama-3' : '');
    if (!is_string($value) || !in_array($value, $options, true)) voice_lab_error(422, 'invalid_setting');
    $preferences[$name] = $value;
}
if (!is_bool($payload['settings']['emotions'] ?? null)) voice_lab_error(422, 'invalid_emotions');
$preferences['emotions'] = $payload['settings']['emotions'];
$voiceId = strtolower(trim((string)($payload['settings']['voiceId'] ?? '')));
if (!preg_match('/^[a-f0-9]{32}$/D', $voiceId)) voice_lab_error(422, 'invalid_fish_voice_id');
$preferences['voiceId'] = $voiceId;

$fish = $payload['keys']['fish'] ?? '';
$google = $payload['keys']['google'] ?? '';
if (!is_string($fish) || !is_string($google)) voice_lab_error(422, 'invalid_key');
$fish = trim($fish);
$google = trim($google);
// Do not guess provider key formats. Only reject unsafe HTTP-header characters or oversized input.
if (strlen($fish) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $fish)) voice_lab_error(422, 'unsafe_fish_key');
if (strlen($google) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $google)) voice_lab_error(422, 'unsafe_google_key');
$remove = $payload['remove'] ?? [];
if (!is_array($remove) || array_diff($remove, ['fish','google'])) voice_lab_error(422, 'invalid_remove');

try {
    $data = voice_lab_read($uid);
    foreach (['fish'=>$fish, 'google'=>$google] as $provider=>$incoming) {
        if (in_array($provider, $remove, true)) unset($data['keys'][$provider]);
        elseif ($incoming !== '') $data['keys'][$provider] = seal_secret($incoming);
    }
    $data['settings'] = $preferences;
    voice_lab_write($uid, $data);
    session_write_close();
    echo json_encode(['ok'=>true, 'saved'=>[
        'fish' => isset($data['keys']['fish']),
        'google' => isset($data['keys']['google'])
    ]], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    voice_lab_error(503, 'settings_save_failed');
}
