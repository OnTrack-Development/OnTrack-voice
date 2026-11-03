<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/call_report_store.php';
require __DIR__.'/call_summary.php';
mobile_require_device();
if($_SERVER['REQUEST_METHOD']!=='POST')mobile_json(['ok'=>false,'error'=>'POST_only'],405);
$raw=file_get_contents('php://input',false,null,0,4097);
if(!is_string($raw)||strlen($raw)>4096)mobile_json(['ok'=>false,'error'=>'report_too_large'],413);
$event=json_decode($raw,true);
if(!is_array($event))mobile_json(['ok'=>false,'error'=>'invalid_json'],400);
try{
    $stored=ontrack_report_append($event,MOBILE_STORE.'/calls','device');
    if(($event['kind']??'')==='finalize'){
        // Live PHP is deployed flat at /saas/mobile; platform Gemini credentials remain server-only.
        require dirname(__DIR__,2).'/api/bootstrap.php';
        $key=(string)($config['gemini_api_key']??'');
        $model=(string)($config['gemini_summary_model']??'gemini-2.5-flash');
        $summary=ontrack_generate_call_summary((string)$event['call_id'],MOBILE_STORE.'/calls',
            static fn(string $transcript):string=>ontrack_google_summary($transcript,$key,$model));
        mobile_json($summary);
    }
    mobile_json($stored);
}catch(InvalidArgumentException $e){
    mobile_json(['ok'=>false,'error'=>$e->getMessage()],422);
}catch(Throwable $e){
    error_log('ontrack call report: '.$e->getMessage());
    mobile_json(['ok'=>false,'error'=>'report_store_unavailable'],503);
}
