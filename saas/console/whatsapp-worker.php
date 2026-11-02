<?php
declare(strict_types=1);
/**
 * CLI-only durable WhatsApp AI jobs. Schedule using the trusted local PHP 8.3
 * cron after verifying official Meta and enabling an OnTrack agent. HTTP forbidden.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/_core/Bootstrap.php';
require __DIR__.'/_core/WhatsAppNativeAgent.php';
init_schema();
$jobs=all("SELECT j.id,j.account_id,j.conversation_id,w.ai_enabled,w.ai_agent_id FROM wa_ai_jobs j
    JOIN wa_connections w ON w.id=j.connection_id AND w.account_id=j.account_id
    WHERE j.status='pending' AND w.state='verified'
    AND w.ai_enabled=1 AND w.ai_agent_id IS NOT NULL
    ORDER BY j.id ASC LIMIT 10");
$counts=['processed'=>0,'skipped'=>0,'failed'=>0];
foreach($jobs as $job){
    $out=wa_native_run_job((int)$job['id']);
    if(($out['status']??'')==='accepted_not_delivered')$counts['processed']++;
    elseif(($out['status']??'')==='failed')$counts['failed']++;
    else $counts['skipped']++;
}
echo json_encode($counts,JSON_THROW_ON_ERROR)."\n";
