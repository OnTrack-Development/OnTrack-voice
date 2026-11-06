<?php
declare(strict_types=1);
// A web request must never trigger network crawling or background work.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/_core/Bootstrap.php';
require_once dirname(__DIR__).'/_core/VoiceKnowledgeWeb.php';
require_once dirname(__DIR__).'/_core/VoiceConnectorCache.php';
$private=private_path();
$lock=@fopen($private.'/.knowledge-sync.lock','c');
if($lock===false)exit(1);
if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);exit(0);}
$exitCode=0;
$report=['at'=>now(),'result'=>'ok'];
try{
    init_schema();
    $report['knowledge']=voice_kb_sync_due_all(12);
}catch(Throwable $error){
    // No raw URLs, connector secrets, or customer content in cron output.
    $exitCode=1;$report['result']='failed';$report['knowledge_error']='knowledge_worker_error';
}
try {
    // Only verified public catalogues. No customer data or live booking cache.
    $report['public_api_cache']=voice_connector_cache_due_all(4);
} catch(Throwable $error) {
    $exitCode=1;$report['result']='failed';$report['api_cache_error']='api_public_cache_worker_error';
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
