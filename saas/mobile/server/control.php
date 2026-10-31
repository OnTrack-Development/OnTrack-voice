<?php
declare(strict_types=1);
require __DIR__.'/common.php';
mobile_require_device();
if ($_SERVER['REQUEST_METHOD']!=='POST') mobile_json(['ok'=>false,'error'=>'method'],405);
$body=json_decode((string)file_get_contents('php://input',false,null,0,512),true);
if (!is_array($body) || !array_key_exists('enabled',$body) || !is_bool($body['enabled']))
    mobile_json(['ok'=>false,'error'=>'boolean_enabled_required'],400);
if (!is_dir(MOBILE_STORE)) mobile_json(['ok'=>false,'error'=>'store_missing'],503);
$lock=fopen(MOBILE_STORE.'/control.lock','c');
if (!$lock || !flock($lock,LOCK_EX)) mobile_json(['ok'=>false,'error'=>'lock_failed'],503);
try {
    $old=mobile_settings();
    if ((bool)$old['enabled'] !== $body['enabled']) {
        $old['enabled']=$body['enabled'];
        $old['revision']=(int)$old['revision']+1;
        mobile_store($old);
    }
    $result=['ok'=>true,'enabled'=>(bool)$old['enabled'],'revision'=>(int)$old['revision']];
} finally { flock($lock,LOCK_UN);fclose($lock); }
mobile_json($result);
