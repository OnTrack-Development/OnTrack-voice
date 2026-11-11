<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
const MOBILE_STORE = '/home/appontrack/.ontrack-mobile';
function mobile_json(array $data, int $status=200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
/**
 * Prefer the operator-provisioned digest. A missing private setting retains
 * the original pilot digest until the host has been migrated.
 * A present but invalid setting fails closed; it must not silently downgrade.
 */
function mobile_auth_digest(
    string $legacyFile=__DIR__.'/auth.sha256',
    string $privateFile=MOBILE_STORE.'/auth.sha256'
): ?string {
    $env=getenv('ONTRACK_MOBILE_AUTH_SHA256');
    if ($env!==false && $env!=='') {
        return preg_match('/^[a-f0-9]{64}$/D',$env) ? $env : null;
    }
    if (file_exists($privateFile) || is_link($privateFile)) {
        if (is_link($privateFile) || !is_file($privateFile) || !is_readable($privateFile)) return null;
        $mode=@fileperms($privateFile);
        if (!is_int($mode) || (PHP_OS_FAMILY!=='Windows' && ($mode & 0077)!==0)) return null;
        $digest=trim((string)@file_get_contents($privateFile,false,null,0,80));
        return preg_match('/^[a-f0-9]{64}$/D',$digest) ? $digest : null;
    }
    $digest=trim((string)@file_get_contents($legacyFile,false,null,0,80));
    return preg_match('/^[a-f0-9]{64}$/D',$digest) ? $digest : null;
}
function mobile_authorized(string $key): bool {
    if (strlen($key)<24) return false;
    $expected=mobile_auth_digest();
    return $expected!==null && hash_equals($expected,hash('sha256',$key));
}
function mobile_require_device(): void {
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!str_starts_with($header, 'Bearer ') || !mobile_authorized(substr($header,7))) {
        mobile_json(['ok'=>false,'error'=>'connection_key_invalid'],401);
    }
}
function mobile_defaults(): array {
    return ['revision'=>1,'enabled'=>false,'auto_answer'=>true,'voice'=>'Aoede',
        'input_channel'=>'mix','answer_delay_seconds'=>5,'response_style'=>'short_ar_egyptian','agent_name'=>'OnTrack',
        'instruction'=>'اتكلم بالمصري بجمل قصيرة. استمع للعميل ورد على كلامه. هذه مكالمة هاتفية؛ لا تذكر شاشة أو روابط.'];
}
function mobile_settings(): array {
    $data = json_decode((string)@file_get_contents(MOBILE_STORE.'/settings.json'),true);
    return array_replace(mobile_defaults(), is_array($data)?$data:[]);
}
function mobile_store(array $data): void {
    if (!is_dir(MOBILE_STORE) && !mkdir(MOBILE_STORE,0700,true)) throw new RuntimeException('private_storage_unavailable');
    $temp = tempnam(MOBILE_STORE,'cfg-');
    try {
        if (file_put_contents($temp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('settings_write_failed');
        chmod($temp,0600);
        if (!rename($temp,MOBILE_STORE.'/settings.json')) throw new RuntimeException('settings_commit_failed');
    } finally { if (is_file($temp)) unlink($temp); }
}
