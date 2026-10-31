<?php
declare(strict_types=1);
require __DIR__.'/common.php';
mobile_require_device();
if ($_SERVER['REQUEST_METHOD']!=='POST') mobile_json(['ok'=>false,'error'=>'POST_only'],405);
$raw=file_get_contents('php://input',false,null,0,2048);
$body=json_decode((string)$raw,true);
if (!is_array($body)) mobile_json(['ok'=>false,'error'=>'json'],400);
$phase=(string)($body['phase']??'');
if (!in_array($phase,['idle','ringing','ai','human','connecting'],true)) mobile_json(['ok'=>false,'error'=>'phase'],400);
$revision=max(0,min(99999999,(int)($body['revision']??0)));
$data=['phase'=>$phase,'agent_running'=>!empty($body['agent_running']),
    'connected'=>($body['connected']??false)===true,
    'pending_sync'=>($body['pending_sync']??false)===true,
    'policy_revision'=>$revision,'seen_at'=>gmdate('c')];
if(!is_dir(MOBILE_STORE)) mobile_json(['ok'=>false,'error'=>'store'],503);
$file=MOBILE_STORE.'/call-status.json';
$tmp=tempnam(MOBILE_STORE,'call-');
try {
    if($tmp===false || file_put_contents($tmp,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),LOCK_EX)===false)
        throw new RuntimeException('status_write');
    chmod($tmp,0600);
    if(!rename($tmp,$file)) throw new RuntimeException('status_commit');
} finally {if(is_string($tmp) && is_file($tmp)) unlink($tmp);}
mobile_json(['ok'=>true]);
