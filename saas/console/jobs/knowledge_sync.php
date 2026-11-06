<?php
declare(strict_types=1);
// A web request must never trigger network crawling or background work.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/_core/Bootstrap.php';
require_once dirname(__DIR__).'/_core/VoiceKnowledgeWeb.php';
$private=private_path();
$lock=@fopen($private.'/.knowledge-sync.lock','c');
if($lock===false)exit(1);
if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);exit(0);}
$exitCode=0;
$report=['at'=>now(),'result'=>'ok'];
try{
    init_schema();
    $report['summary']=voice_kb_sync_due_all(12);
}catch(Throwable $error){
    // No raw URLs, connector secrets, or customer content in cron output.
    $exitCode=1;$report['result']='failed';$report['reason']='knowledge_worker_error';
}
try{
    $target=$private.'/knowledge-sync-last.json';
    $tmp=$target.'.'.getmypid().'.tmp';
    if(file_put_contents($tmp,json_encode($report,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX)!==false){
        @chmod($tmp,0600);@rename($tmp,$target);
    }
}catch(Throwable $ignored){}
flock($lock,LOCK_UN);fclose($lock);
echo json_encode($report,JSON_UNESCAPED_SLASHES)."\n";
exit($exitCode);
