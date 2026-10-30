<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
const MOBILE_STORE = '/home/appontrack/.ontrack-mobile';
function mobile_json(array $data, int $status=200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
function mobile_authorized(string $key): bool {
    $expected = trim((string)@file_get_contents(__DIR__.'/auth.sha256'));
    return strlen($key) >= 24 && preg_match('/^[a-f0-9]{64}$/D', $expected)
        && hash_equals($expected, hash('sha256', $key));
}
function mobile_require_device(): void {
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!str_starts_with($header, 'Bearer ') || !mobile_authorized(substr($header,7))) {
        mobile_json(['ok'=>false,'error'=>'connection_key_invalid'],401);
    }
}
function mobile_defaults(): array {
    return ['revision'=>1,'enabled'=>false,'auto_answer'=>true,'voice'=>'Aoede',
        'input_channel'=>'right','agent_name'=>'OnTrack',
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
