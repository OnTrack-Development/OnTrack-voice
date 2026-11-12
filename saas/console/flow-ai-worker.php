<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/_core/Bootstrap.php';
require __DIR__.'/_core/FlowAiJobs.php';
require __DIR__.'/_core/OperationalOutbox.php';
init_schema();
$handle=@fopen(private_path().'/.flow-ai-worker-v68.lock','c');
if($handle===false)throw new RuntimeException('flow_ai_worker_lock_unavailable');
if(!flock($handle,LOCK_EX|LOCK_NB)){fclose($handle);exit(0);}
try{
    flow_ai_job_recover();
    $pending=all("SELECT job_id FROM flow_ai_jobs WHERE status='pending' AND attempts<2 ORDER BY created_at ASC LIMIT 2");
    $counts=['done'=>0,'failed'=>0,'skipped'=>0];
    foreach($pending as $item){
        try{
            $out=flow_ai_job_run((string)$item['job_id']);
            $status=(string)($out['status']??'skipped');
            if(!array_key_exists($status,$counts))$status='skipped';
            $counts[$status]++;
        }catch(Throwable){
            $counts['failed']++;
        }
    }
    operational_worker_beat('flow_ai',count($pending),$counts['done'],$counts['failed']);
    echo json_encode($counts,JSON_THROW_ON_ERROR)."\n";
}finally{flock($handle,LOCK_UN);fclose($handle);}
