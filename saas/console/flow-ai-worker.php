<?php
declare(strict_types=1);
/** CLI only; on shared hosting schedule once per minute using PHP 8.3. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/_core/Bootstrap.php';
require __DIR__.'/_core/FlowAiJobs.php';
init_schema();
flow_ai_job_recover();
$pending=all("SELECT job_id FROM flow_ai_jobs WHERE status='pending' AND attempts<2 ORDER BY created_at ASC LIMIT 2");
$counts=['done'=>0,'failed'=>0,'skipped'=>0];
foreach($pending as $item){
    $out=flow_ai_job_run((string)$item['job_id']);
    ++$counts[$out['status']??'skipped'];
}
echo json_encode($counts,JSON_THROW_ON_ERROR)."\n";
