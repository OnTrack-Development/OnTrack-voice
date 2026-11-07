<?php
declare(strict_types=1);
// Owner-only Voice Lab endpoints. Keys are encrypted in the SaaS private directory,
// never in public_html, HTML, JavaScript, logs, or the repository.
require_once __DIR__ . '/../../_core/Bootstrap.php';

function voice_lab_error(int $code, string $reason): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo json_encode(['ok' => false, 'error' => $reason], JSON_UNESCAPED_SLASHES);
    exit;
}

function voice_lab_access(bool $write = false): array {
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    startsession();
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $host = strtolower((string)parse_url('https://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    if ($origin !== '' && (string)parse_url($origin, PHP_URL_SCHEME) !== 'https') {
        voice_lab_error(403, 'wrong_origin');
    }
    if ($origin !== '' && strtolower((string)parse_url($origin, PHP_URL_HOST)) !== $host) {
        voice_lab_error(403, 'wrong_origin');
    }
    $user = me();
    if (!$user) voice_lab_error(401, 'admin_login_required');
    $authorized = false;
    if (!white_label_context()) {
        foreach (memberships((int)$user['id']) as $membership) {
            if (($membership['kind'] ?? '') === 'platform'
                && in_array((string)($membership['role'] ?? ''), ['owner','admin'], true)) {
                $authorized = true;
                break;
            }
        }
    }
    if (!$authorized) voice_lab_error(403, 'platform_admin_required');
    if ($write) {
        $supplied = (string)($_SERVER['HTTP_X_VOICE_LAB_CSRF'] ?? '');
        if ($supplied === '' || !hash_equals(csrf(), $supplied)) {
            voice_lab_error(419, 'csrf_expired');
        }
    }
    return $user;
}

function voice_lab_file(int $uid): string {
    if ($uid < 1) throw new RuntimeException('invalid_user');
    $dir = private_path() . '/voice-lab';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('private_settings_unavailable');
    }
    if (is_link($dir)) throw new RuntimeException('unsafe_settings_directory');
    @chmod($dir, 0700);
    return $dir . '/owner-' . $uid . '.json';
}

function voice_lab_read(int $uid): array {
    $path = voice_lab_file($uid);
    if (is_link($path)) throw new RuntimeException('unsafe_settings_path');
    if (!is_file($path)) return ['keys'=>[], 'settings'=>[]];
    $contents = @file_get_contents($path);
    $decoded = is_string($contents) ? json_decode($contents, true) : null;
    if (!is_array($decoded) || !is_array($decoded['keys'] ?? null)
        || !is_array($decoded['settings'] ?? null)) {
        throw new RuntimeException('saved_settings_invalid');
    }
    return $decoded;
}

function voice_lab_write(int $uid, array $data): void {
    $path = voice_lab_file($uid);
    if (is_link($path)) throw new RuntimeException('unsafe_settings_path');
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $tmp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
    try {
        if (file_put_contents($tmp, $json, LOCK_EX) !== strlen($json)) {
            throw new RuntimeException('settings_write_failed');
        }
        @chmod($tmp, 0600);
        if (!rename($tmp, $path)) throw new RuntimeException('settings_rename_failed');
        @chmod($path, 0600);
    } finally {
        if (is_file($tmp)) @unlink($tmp);
    }
}

function voice_lab_saved_key(int $uid, string $provider, string $incoming): string {
    $incoming = trim($incoming);
    if ($incoming !== '') return $incoming;
    $data = voice_lab_read($uid);
    $encoded = $data['keys'][$provider] ?? null;
    return is_string($encoded) ? (unseal_secret($encoded) ?? '') : '';
}
