<?php
declare(strict_types=1);
$bridgePrivate=sys_get_temp_dir().'/ontrack-bridge-'.bin2hex(random_bytes(6));
mkdir($bridgePrivate,0700);
function private_path():string {global $bridgePrivate;return $bridgePrivate;}
function dnc_phone_key(string $phone):string {
    $digits=preg_replace('/[^0-9]/','',$phone);
    if(str_starts_with($digits,'00'))$digits=substr($digits,2);
    if(preg_match('/^01[0-9]{9}$/D',$digits))$digits='20'.substr($digits,1);
    return $digits;
}
function dnc_ledger_contains(int $accountId,string $key):bool {
    return $accountId===7 && $key==='201001234567';
}
function all(string $sql,array $args=[]):array {
    if(!str_contains($sql,'account_id=? AND dnc=1'))throw new RuntimeException('unscoped_dnc_query');
    return $args===[8]?[['phone'=>'+201001234567']]:[];
}
function dnc_record_suppression(int $accountId,string $phone,string $source):void {
    if($accountId!==8||$source!=='legacy_contact')throw new RuntimeException('ledger_healing_scope');
}
function one(string $sql,array $args=[]):?array {
    if(!str_contains($sql,"d.account_id")||!str_contains($sql,"s.account_id=d.account_id")||
       !str_contains($sql,"ag.account_id=d.account_id"))throw new RuntimeException('unscoped_device_query');
    return $args===[hash('sha256','otdev_'.str_repeat('a',64))]?['account_id'=>7,'site_id'=>3]:null;
}
require_once __DIR__.'/../saas/console/_core/TenantAccountBridge.php';
function bridge_check(bool $value,string $message):void {
    if(!$value)throw new RuntimeException('TENANT_ACCOUNT_BRIDGE_FAIL '.$message);
}
try {
    $file=$bridgePrivate.'/tenant-account-bridge.json';
    file_put_contents($file,json_encode(['account_to_tenant'=>['7'=>91,'8'=>92]],JSON_THROW_ON_ERROR));
    chmod($file,0600);
    bridge_check(TenantAccountBridge::tenantId(7)===91,'explicit mapping');
    bridge_check(TenantAccountBridge::accountId(92)===8,'reverse mapping');
    bridge_check(TenantAccountBridge::tenantId(91)===null,'never infer equal numeric IDs');
    bridge_check(TenantAccountBridge::isSuppressed(7,'01001234567'),'ledger suppression');
    bridge_check(TenantAccountBridge::isSuppressed(8,'01001234567'),'legacy suppression');
    bridge_check(!TenantAccountBridge::isSuppressed(9,'01001234567'),'account isolation');
    bridge_check(TenantAccountBridge::authorizeBinding('otdev_'.str_repeat('a',64))!==null,'scoped binding');
    bridge_check(TenantAccountBridge::authorizeBinding('invalid')===null,'invalid binding');
} finally {@unlink($bridgePrivate.'/tenant-account-bridge.json');@rmdir($bridgePrivate);}
echo "TENANT_ACCOUNT_BRIDGE_DNC_DEVICE_SCOPE_PASS\n";
