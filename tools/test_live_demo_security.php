<?php
declare(strict_types=1);

require __DIR__.'/../api/LiveSessionSecurity.php';

function expect_guard(bool $ok, string $what): void {
    if (!$ok) throw new RuntimeException('LIVE_DEMO_SECURITY_FAILED: '.$what);
}

$token = str_repeat('a', 64);
$server = [
    'HTTP_HOST'=>'app.ontrackegy.com',
    'HTTP_ORIGIN'=>'https://app.ontrackegy.com',
    'HTTP_SEC_FETCH_SITE'=>'same-origin',
];
expect_guard(ontrack_live_demo_request_allowed($server, $token, $token), 'valid root demo same-origin token');
expect_guard(!ontrack_live_demo_request_allowed($server, $token, ''), 'direct unauthenticated token minting rejected');
expect_guard(!ontrack_live_demo_request_allowed($server, $token, str_repeat('b', 64)), 'wrong session token rejected');
expect_guard(!ontrack_live_demo_request_allowed(array_replace($server, [
    'HTTP_ORIGIN'=>'https://attacker.example'
]), $token, $token), 'cross-origin rejected');
expect_guard(!ontrack_live_demo_request_allowed(array_replace($server, [
    'HTTP_SEC_FETCH_SITE'=>'cross-site'
]), $token, $token), 'cross-site fetch metadata rejected');
expect_guard(!ontrack_live_demo_request_allowed(array_replace($server, [
    'HTTP_ORIGIN'=>'http://app.ontrackegy.com'
]), $token, $token), 'HTTP downgrade rejected');
expect_guard(ontrack_live_demo_request_allowed([
    'HTTP_HOST'=>'127.0.0.1:18773',
    'HTTP_ORIGIN'=>'http://127.0.0.1:18773',
    'HTTP_SEC_FETCH_SITE'=>'same-origin',
], $token, $token), 'local developer loopback remains functional');
expect_guard(ontrack_live_demo_request_allowed([
    'HTTP_HOST'=>'app.ontrackegy.com',
], $token, $token), 'non-browser same-origin caller with valid server cookie/nonce remains compatible');

$dir = sys_get_temp_dir().'/ontrack-live-unit-'.bin2hex(random_bytes(8));
try {
    $base = strtotime('2026-11-09 12:00:00 UTC');
    $limits = ['hour_ip'=>2,'day_ip'=>4,'day_global'=>5];
    expect_guard(ontrack_live_demo_quota($dir, '198.51.100.1', $base, $limits), 'IP first request');
    expect_guard(ontrack_live_demo_quota($dir, '198.51.100.1', $base, $limits), 'IP second request');
    expect_guard(!ontrack_live_demo_quota($dir, '198.51.100.1', $base, $limits), 'IP hourly lock');
    expect_guard(ontrack_live_demo_quota($dir, '198.51.100.1', $base+3600, $limits), 'IP hourly window resets');
    expect_guard(ontrack_live_demo_quota($dir, '198.51.100.1', $base+7200, $limits), 'IP daily count remains');
    expect_guard(!ontrack_live_demo_quota($dir, '198.51.100.1', $base+10800, $limits), 'IP daily lock');
    expect_guard(ontrack_live_demo_quota($dir, '198.51.100.2', $base, $limits), 'second IP can access');
    expect_guard(!ontrack_live_demo_quota($dir, '198.51.100.3', $base, $limits), 'global daily cap');
    expect_guard(ontrack_live_demo_quota($dir, '198.51.100.1', $base+86400, $limits), 'new UTC day resets cap');
    $currentFiles = glob($dir.'/*.json');
    expect_guard(count($currentFiles) === 2, 'daily data files used for atomic quotas');
    foreach ($currentFiles as $file) {
        $stored = (string)file_get_contents($file);
        expect_guard(!str_contains($stored, '198.51.100.'), 'IP address not exposed in quota file');
    }
} finally {
    foreach (glob($dir.'/*') ?: [] as $path) @unlink($path);
    @rmdir($dir);
}

$sessionDir = sys_get_temp_dir().'/ontrack-live-session-test-'.bin2hex(random_bytes(8));
try {
    $t=strtotime('2026-11-09 12:00:00 UTC');
    $limits=['hour_ip'=>10,'day_ip'=>10,'day_global'=>20,
             'hour_session'=>2,'day_session'=>3];
    expect_guard(ontrack_live_demo_quota($sessionDir,'198.51.100.11',$t,$limits,'nonce-A'),
        'session mint first IP');
    expect_guard(ontrack_live_demo_quota($sessionDir,'198.51.100.12',$t,$limits,'nonce-A'),
        'same session across another IP');
    expect_guard(!ontrack_live_demo_quota($sessionDir,'198.51.100.13',$t,$limits,'nonce-A'),
        'same session cannot bypass hourly quota by changing IP');
    expect_guard(ontrack_live_demo_quota($sessionDir,'198.51.100.13',$t,$limits,'nonce-B'),
        'independent valid session remains allowed');
    expect_guard(ontrack_live_demo_quota($sessionDir,'198.51.100.14',$t+3600,$limits,'nonce-A'),
        'session hour resets without losing daily usage');
    expect_guard(!ontrack_live_demo_quota($sessionDir,'198.51.100.15',$t+3600,$limits,'nonce-A'),
        'session daily quota cannot be bypassed by rotating IP');
    $contents=(string)file_get_contents($sessionDir.'/live-'.gmdate('Ymd',$t).'.json');
    expect_guard(!str_contains($contents,'nonce-A') && !str_contains($contents,'nonce-B'),
        'raw session nonce never persisted to quota storage');
} finally {
    foreach (glob($sessionDir.'/*') ?: [] as $path) @unlink($path);
    @rmdir($sessionDir);
}
$root = dirname(__DIR__);
$php = file_get_contents($root.'/api/live_session.php');
$js = file_get_contents($root.'/assets/app.js');
$html = file_get_contents($root.'/index.php');
$mobile = file_get_contents($root.'/saas/mobile/server/session.php');
expect_guard(str_contains($php, 'ontrack_live_demo_authorize();'), 'token endpoint protected by guard');
expect_guard(str_contains($php, "'uses' => 1"), 'single-use Gemini token preserved');
expect_guard(str_contains($html, 'ontrack-live-csrf'), 'legitimate demo page issues CSRF');
expect_guard(str_contains($js, "'X-OnTrack-Live-CSRF'"), 'legitimate demo sends session-bound CSRF');
expect_guard(str_contains($js, "mode: 'outbound'"), 'outbound mission mode preserved');
expect_guard(str_contains($mobile, "mobile_require_device();") &&
    str_contains($mobile, "ONTRACK_INTERNAL_MOBILE_LIVE_SESSION"), 'authorized legacy phone path preserved');

echo "LIVE_DEMO_SECURITY_NONREGRESSION_PASS\n";
