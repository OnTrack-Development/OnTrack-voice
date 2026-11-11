<?php
declare(strict_types=1);
$private=sys_get_temp_dir().'/ontrack-laravel-mirror-'.bin2hex(random_bytes(6));
mkdir($private,0700);
function private_path():string {global $private;return $private;}
function dnc_phone_key(string $phone):string {
    $digits=preg_replace('/[^0-9]/','',$phone);
    if(str_starts_with($digits,'00'))$digits=substr($digits,2);
    if(preg_match('/^01[0-9]{9}$/D',$digits))$digits='20'.substr($digits,1);
    return $digits;
}
function dnc_ledger_contains(int $account,string $key):bool {return false;}
function all(string $sql,array $args=[]):array {
    if(!str_contains($sql,'account_id=? AND dnc=1'))throw new RuntimeException('unscoped_console_fallback');
    return [];
}
require_once __DIR__.'/../saas/console/_core/TenantAccountBridge.php';
function mirror_check(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('VOICE_MIRROR_FAIL '.$label);
}
try {
    $links=$private.'/tenant-account-bridge.json';
    file_put_contents($links,json_encode(['account_to_tenant'=>['7'=>91,'8'=>92,'9'=>93]],JSON_THROW_ON_ERROR));
    chmod($links,0600);
    mirror_check(!TenantAccountBridge::isSuppressed(9,'+201001234567'),'missing mirror falls back');
    $dbFile=$private.'/laravel.sqlite';
    $db=new PDO('sqlite:'.$dbFile,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('CREATE TABLE voice_dnc_entries (id INTEGER PRIMARY KEY,tenant_id INTEGER,account_id INTEGER,phone_normalized TEXT,reason TEXT,source TEXT,created_by INTEGER,created_at TEXT,updated_at TEXT,UNIQUE(tenant_id,phone_normalized))');
    $db->exec('CREATE TABLE voice_device_pairings (id INTEGER PRIMARY KEY,tenant_id INTEGER,account_id INTEGER,device_id TEXT UNIQUE,pairing_token_hash TEXT UNIQUE,status TEXT,last_seen_at TEXT,created_at TEXT,updated_at TEXT)');
    $db->exec("INSERT INTO voice_dnc_entries(tenant_id,account_id,phone_normalized,reason,source,created_at,updated_at) VALUES (93,9,'201001234567','opt_out','contact_optout','now','now')");
    $config=$private.'/laravel-mirror-db.json';
    file_put_contents($config,json_encode(['dsn'=>'sqlite:'.$dbFile],JSON_THROW_ON_ERROR));
    chmod($config,0600);
    mirror_check(TenantAccountBridge::isSuppressed(9,'01001234567'),'mirror DNC read');
    mirror_check(!TenantAccountBridge::isSuppressed(8,'01001234567'),'tenant isolation');
    TenantAccountBridge::mirrorDncToLaravel(7,'201001234567','contact_optout');
    mirror_check((int)$db->query("SELECT COUNT(*) FROM voice_dnc_entries WHERE tenant_id=91 AND account_id=7")->fetchColumn()===1,'Console write mirrored');
    TenantAccountBridge::mirrorDeviceToLaravel(7,501,hash('sha256','pairing-code'));
    mirror_check((int)$db->query("SELECT COUNT(*) FROM voice_device_pairings WHERE tenant_id=91 AND account_id=7 AND device_id='console:501' AND status='active'")->fetchColumn()===1,'device mirror scope');
    TenantAccountBridge::revokeMirroredDevice(7,501);
    mirror_check($db->query("SELECT status FROM voice_device_pairings WHERE device_id='console:501'")->fetchColumn()==='revoked','device revocation');
    unset($db);
    unlink($config);
    mirror_check(!TenantAccountBridge::isSuppressed(8,'01001234567'),'unavailable mirror is graceful');
} finally {
    foreach(glob($private.'/*')?:[] as $file)@unlink($file);
    @rmdir($private);
}
echo "VOICE_LARAVEL_MIRROR_CONSOLE_FALLBACK_PASS\n";
