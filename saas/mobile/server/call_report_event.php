<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/call_report_store.php';
mobile_require_device();
if($_SERVER['REQUEST_METHOD']!=='POST')mobile_json(['ok'=>false,'error'=>'POST_only'],405);
$raw=file_get_contents('php://input',false,null,0,4097);
if(!is_string($raw)||strlen($raw)>4096)mobile_json(['ok'=>false,'error'=>'report_too_large'],413);
$event=json_decode($raw,true);
if(!is_array($event))mobile_json(['ok'=>false,'error'=>'invalid_json'],400);
try{
    mobile_json(ontrack_report_append($event,MOBILE_STORE.'/calls','device'));
}catch(InvalidArgumentException $e){
    mobile_json(['ok'=>false,'error'=>$e->getMessage()],422);
}catch(Throwable $e){
    error_log('ontrack call report: '.$e->getMessage());
    mobile_json(['ok'=>false,'error'=>'report_store_unavailable'],503);
}
