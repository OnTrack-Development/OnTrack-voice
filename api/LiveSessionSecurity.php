<?php
declare(strict_types=1);

/**
 * Public root demo guard. This deliberately does not alter authenticated
 * tenant device/v1 voice sessions, the legacy pilot's authorized internal
 * include, or the currently installed Android/OTA contract.
 *
 * Anonymous visitors can still use the OnTrack demo from index.php, but
 * direct token-minting without a page session is no longer accepted.
 */
function ontrack_live_demo_session(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        session_name('ONTRACKLIVE');
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime'=>0, 'path'=>'/', 'secure'=>$https,
            'httponly'=>true, 'samesite'=>'Lax',
        ]);
        if (!session_start()) throw new RuntimeException('live_session_store_unavailable');
    }
    if (!isset($_SESSION['ontrack_live_csrf']) ||
        !is_string($_SESSION['ontrack_live_csrf']) ||
        !preg_match('/\A[a-f0-9]{64}\z/D', $_SESSION['ontrack_live_csrf'])) {
        $_SESSION['ontrack_live_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['ontrack_live_csrf'];
}

/** The CSRF nonce is tied to the server-side session, never the caller's IP. */
function ontrack_live_demo_request_allowed(array $server, string $expected, string $received): bool {
    if (!preg_match('/\A[a-f0-9]{64}\z/D', $expected) ||
        !preg_match('/\A[a-f0-9]{64}\z/D', $received) ||
        !hash_equals($expected, $received)) return false;

    $site = strtolower(trim((string)($server['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($site === 'cross-site') return false;
    $origin = trim((string)($server['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') {
        $parts = parse_url($origin);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) ||
            isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])) return false;
        $hostname = strtolower((string)($parts['host'] ?? ''));
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $hostHeader = strtolower((string)($server['HTTP_HOST'] ?? ''));
        $allowedHost = $hostname !== '' && in_array($hostname, [
            'app.ontrackegy.com', 'ontrackegy.com', 'www.ontrackegy.com', 'localhost', '127.0.0.1'
        ], true);
        $port = isset($parts['port']) ? ':'.(int)$parts['port'] : '';
        if (!$allowedHost || $hostHeader !== $hostname.$port ||
            ($scheme !== 'https' && !($scheme === 'http' &&
                in_array($hostname, ['localhost','127.0.0.1'], true)))) return false;
    }
    return true;
}

/**
 * File-lock based quota on a private directory: protects against parallel
 * requests without relying on cookies or attacker-controlled forwarded IP.
 * Return false (fail-closed) when storage is not writable.
 */
function ontrack_live_demo_quota(string $directory, string $ip, int $timestamp, array $limits = []): bool {
    $hourLimit = max(1, (int)($limits['hour_ip'] ?? 80));
    $dayLimit = max(1, (int)($limits['day_ip'] ?? 180));
    $globalLimit = max(1, (int)($limits['day_global'] ?? 250));
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) return false;
    if (is_link($directory) || !is_writable($directory)) return false;

    $day = gmdate('Ymd', $timestamp);
    $path = $directory.'/live-'.$day.'.json';
    $handle = @fopen($path, 'c+');
    if (!is_resource($handle)) return false;
    try {
        @chmod($path, 0600);
        if (!flock($handle, LOCK_EX)) return false;
        try {
            rewind($handle);
            $raw = stream_get_contents($handle, 131072);
            $state = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($state) || ($state['day'] ?? '') !== $day) {
                $state = ['day'=>$day, 'global'=>0, 'ips'=>[]];
            }
            $ipHash = hash('sha256', $ip);
            $hour = intdiv($timestamp, 3600);
            $entry = $state['ips'][$ipHash] ?? ['hour'=>$hour,'hour_count'=>0,'day_count'=>0];
            if ((int)($entry['hour'] ?? -1) !== $hour) {
                $entry['hour'] = $hour;
                $entry['hour_count'] = 0;
            }
            if ((int)$entry['hour_count'] >= $hourLimit ||
                (int)$entry['day_count'] >= $dayLimit ||
                (int)($state['global'] ?? 0) >= $globalLimit) return false;
            ++$entry['hour_count'];
            ++$entry['day_count'];
            $state['ips'][$ipHash] = $entry;
            ++$state['global'];
            $encoded = json_encode($state, JSON_THROW_ON_ERROR);
            if (strlen($encoded) > 130000) return false;
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) ||
                !fflush($handle)) return false;
            return true;
        } finally {
            flock($handle, LOCK_UN);
        }
    } finally {
        fclose($handle);
    }
}

function ontrack_live_demo_guard_dir(): string {
    if (is_dir('/home/appontrack')) return '/home/appontrack/.ontrack-live-guard';
    // Local developer / isolated CI only. Never use a public web directory.
    return sys_get_temp_dir().'/ontrack-live-'.hash('sha256', __DIR__);
}

function ontrack_live_demo_authorize(): void {
    // The legacy pilot validates the device bearer before including the root
    // session builder on older flat installs. No HTTP query/header bypass.
    if (defined('ONTRACK_INTERNAL_MOBILE_LIVE_SESSION') &&
        ONTRACK_INTERNAL_MOBILE_LIVE_SESSION === true) return;
    try {
        $expected = ontrack_live_demo_session();
    } catch (Throwable $e) {
        json_out(['ok'=>false,'error'=>'session_store_unavailable'],503);
    }
    $received = (string)($_SERVER['HTTP_X_ONTRACK_LIVE_CSRF'] ?? '');
    if (!ontrack_live_demo_request_allowed($_SERVER, $expected, $received)) {
        json_out(['ok'=>false,'error'=>'live_session_authorization_required'],403);
    }
    // Release the PHP session lock before the slow upstream HTTP request.
    session_write_close();
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!ontrack_live_demo_quota(ontrack_live_demo_guard_dir(), $ip, time())) {
        header('Retry-After: 3600');
        json_out(['ok'=>false,'error'=>'live_demo_quota_exceeded'],429);
    }
}
