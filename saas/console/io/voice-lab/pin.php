<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function voice_lab_pin_admin(): bool {
    $person = me();
    if (!$person || white_label_context()) return false;
    foreach (memberships((int)$person['id']) as $membership) {
        if (($membership['kind'] ?? '') === 'platform'
            && in_array((string)($membership['role'] ?? ''), ['owner','admin'], true)) return true;
    }
    return false;
}

startsession();
voice_lab_same_origin();
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET','POST'], true)) {
    header('Allow: GET, POST');
    voice_lab_error(405, 'method_not_allowed');
}
$config = voice_lab_pin_config();
if ($method === 'GET') {
    echo json_encode([
        'ok'=>true,
        'configured'=>$config !== null,
        'authorized'=>voice_lab_pin_user() !== null,
        'can_setup'=>$config === null && voice_lab_pin_admin(),
        'setup_csrf'=>$config === null && voice_lab_pin_admin() ? csrf() : null
    ], JSON_UNESCAPED_SLASHES);
    exit;
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 256
    || !str_starts_with(strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? ''))), 'application/json')) {
    voice_lab_error(400, 'invalid_request');
}
$input = json_decode((string)file_get_contents('php://input'), true);
$pin = is_array($input) && is_string($input['pin'] ?? null) ? $input['pin'] : '';

// PIN provisioning is a one-time privileged owner action only.
// All subsequent callers need only the six-digit PIN, not an administrator login.
if ($config === null) {
    if (!is_array($input) || ($input['setup'] ?? null) !== true
        || !voice_lab_pin_admin()
        || !is_string($input['csrf'] ?? null)
        || !hash_equals(csrf(), $input['csrf'])) voice_lab_error(403, 'pin_setup_admin_required');
    if (preg_match('/^[0-9]{6}$/D', $pin) !== 1) voice_lab_error(422, 'pin_six_digits_required');
    $folder = private_path() . '/voice-lab';
    if (!is_dir($folder) || is_link($folder)) voice_lab_error(503, 'pin_storage_unavailable');
    $configured = [
        'owner_uid'=>(int)me()['id'],
        'salt'=>bin2hex(random_bytes(20)),
        'iterations'=>250000,
        'version'=>bin2hex(random_bytes(12))
    ];
    $configured['hash'] = hash_pbkdf2('sha256', $pin, $configured['salt'], $configured['iterations'], 64);
    $dest = $folder . '/access.json';
    if (is_link($dest) || is_file($dest)) voice_lab_error(409, 'pin_already_configured');
    $tmp = $folder . '/.access-' . bin2hex(random_bytes(8)) . '.tmp';
    try {
        $json = json_encode($configured, JSON_THROW_ON_ERROR);
        if (file_put_contents($tmp, $json, LOCK_EX) !== strlen($json)) {
            voice_lab_error(503, 'pin_setup_failed');
        }
        @chmod($tmp, 0600);
        if (!rename($tmp, $dest)) voice_lab_error(503, 'pin_setup_failed');
        @chmod($dest, 0600);
    } finally { if (is_file($tmp)) @unlink($tmp); }
    session_regenerate_id(true);
    $_SESSION['voice_lab_pin_version'] = $configured['version'];
    $_SESSION['voice_lab_pin_expires'] = time() + 12*3600;
    echo '{"ok":true,"authorized":true,"configured":true}';
    exit;
}

$directory = private_path() . '/voice-lab/pin-attempts';
if ((!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory))
    || is_link($directory)) voice_lab_error(503, 'pin_auth_unavailable');
$peer = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$path = $directory . '/' . hash_hmac('sha256', $peer, (string)$config['salt']) . '.json';
if (is_link($path)) voice_lab_error(503, 'pin_auth_unavailable');
$handle = fopen($path, 'c+');
if (!$handle) voice_lab_error(503, 'pin_auth_unavailable');
@chmod($path, 0600);
try {
    if (!flock($handle, LOCK_EX)) voice_lab_error(503, 'pin_auth_unavailable');
    $raw = stream_get_contents($handle);
    $attempt = json_decode(is_string($raw) ? $raw : '', true);
    $start = (int)($attempt['start'] ?? 0);
    $count = (int)($attempt['count'] ?? 0);
    if ($start <= 0 || time() - $start > 900) { $start=time(); $count=0; }
    if ($count >= 6) {
        header('Retry-After: ' . max(1, 900-(time()-$start)));
        voice_lab_error(429, 'too_many_attempts');
    }
    $valid = preg_match('/^[0-9]{6}$/D', $pin) === 1
        && hash_equals(
            (string)$config['hash'],
            hash_pbkdf2('sha256', $pin, (string)$config['salt'], (int)$config['iterations'], 64)
        );
    $stored = json_encode(['start'=>$start, 'count'=>$valid?0:$count+1], JSON_THROW_ON_ERROR);
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, $stored);
    fflush($handle);
    flock($handle, LOCK_UN);
} finally {
    fclose($handle);
}
if (!$valid) voice_lab_error(401, 'invalid_pin');
session_regenerate_id(true);
$_SESSION['voice_lab_pin_version'] = (string)$config['version'];
$_SESSION['voice_lab_pin_expires'] = time() + 12*3600;
unset($_SESSION['voice_lab_guest_settings']);
echo '{"ok":true,"authorized":true}';
