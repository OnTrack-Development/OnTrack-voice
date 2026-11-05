<?php
declare(strict_types=1);
/**
 * Demo-only owner guard and persistent API credentials.
 * Private storage lives in /home/appontrack/.ayo-private, outside public_html.
 * The owner code hash is provisioned on the hosting machine (never in Git).
 */
const AYO_PRIVATE_DIR = '/home/appontrack/.ayo-private';
function ayo_private_path(string $file): string { return AYO_PRIVATE_DIR . '/' . $file; }
function ayo_private_read(string $file): array {
    $path = ayo_private_path($file);
    if (!is_file($path) || is_link($path)) return [];
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}
function ayo_private_write(string $file, array $data): void {
    if (!is_dir(AYO_PRIVATE_DIR) || is_link(AYO_PRIVATE_DIR)) throw new RuntimeException('مجلد الأسرار غير جاهز على الاستضافة');
    $path = ayo_private_path($file);
    if (is_link($path)) throw new RuntimeException('مسار الأسرار غير آمن');
    $tmp = tempnam(AYO_PRIVATE_DIR, '.ayo-');
    if (!$tmp) throw new RuntimeException('تعذر تجهيز حفظ الأسرار');
    try {
        @chmod($tmp, 0600);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $path)) throw new RuntimeException('تعذر الحفظ على الاستضافة');
        @chmod($path, 0600);
    } finally { if (is_file($tmp)) @unlink($tmp); }
}
function ayo_is_owner(): bool {
    if (!empty($_SESSION['ayo_owner'])) return true;
    $token = $_COOKIE['ayo_device'] ?? '';
    if (!is_string($token) || strlen($token) < 35 || strlen($token) > 120) return false;
    $hash = ayo_private_read('owner.json')['device_hash'] ?? '';
    if (is_string($hash) && strlen($hash) === 64 && hash_equals($hash, hash('sha256', $token))) {
        $_SESSION['ayo_owner'] = true;
        return true;
    }
    return false;
}
function ayo_require_owner(): void {
    if (!ayo_is_owner()) fail('الديمو خاص: افتح لوحة الدخول مرة واحدة برمز المالك', 403);
}
function ayo_owner_login(string $code): void {
    $attempt = $_SESSION['ayo_auth_failures'] ?? ['count' => 0, 'since' => time()];
    if (time() - (int)$attempt['since'] > 900) $attempt = ['count' => 0, 'since' => time()];
    if ($attempt['count'] >= 6) fail('محاولات كثيرة؛ جرّب بعد 15 دقيقة', 429);
    $guard = ayo_private_read('owner.json');
    $expected = $guard['code_sha256'] ?? '';
    if (strlen($expected) !== 64 || !hash_equals($expected, hash('sha256', trim($code)))) {
        $attempt['count']++;
        $_SESSION['ayo_auth_failures'] = $attempt;
        fail('رمز المالك غير صحيح', 403);
    }
    unset($_SESSION['ayo_auth_failures']);
    $_SESSION['ayo_owner'] = true;
    session_regenerate_id(true);
    $token = bin2hex(random_bytes(32));
    $guard['device_hash'] = hash('sha256', $token);
    ayo_private_write('owner.json', $guard);
    setcookie('ayo_device', $token, [
        'expires' => time() + 90 * 86400, 'path' => '/ayo',
        'secure' => true, 'httponly' => true, 'samesite' => 'Strict',
    ]);
}
function ayo_saved_keys(): array {
    if (!ayo_is_owner()) return [];
    $raw = ayo_private_read('credentials.json');
    $keys = [];
    foreach (['inworld', 'apinex', 'omnira'] as $name) {
        if (isset($raw[$name]) && is_string($raw[$name]) && strlen($raw[$name]) <= 2200) $keys[$name] = $raw[$name];
    }
    return $keys;
}
function ayo_persist_keys(array $incoming): void {
    ayo_require_owner();
    $keys = ayo_saved_keys();
    foreach (['inworld', 'apinex', 'omnira'] as $name) {
        if (isset($incoming[$name]) && is_string($incoming[$name]) && trim($incoming[$name]) !== '') {
            if (strlen($incoming[$name]) > 2200) fail('مفتاح أطول من المسموح');
            $keys[$name] = trim($incoming[$name]);
        }
    }
    if ($keys) ayo_private_write('credentials.json', $keys);
}
function ayo_migrate_legacy_keys(): void {
    if (!ayo_is_owner() || empty($_SESSION['ayo_keys']) || !is_array($_SESSION['ayo_keys'])) return;
    $existing = ayo_saved_keys();
    $missing = [];
    foreach (['inworld', 'apinex', 'omnira'] as $name) {
        if (empty($existing[$name]) && !empty($_SESSION['ayo_keys'][$name])) $missing[$name] = $_SESSION['ayo_keys'][$name];
    }
    if ($missing) ayo_persist_keys($missing);
    unset($_SESSION['ayo_keys']);
}
