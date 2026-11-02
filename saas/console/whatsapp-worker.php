<?php
declare(strict_types=1);
/**
 * CLI-only durable WhatsApp AI jobs. Schedule using the trusted local PHP 8.3
 * cron after verifying WHMCS addon bridge and Meta channels. HTTP forbidden.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/_core/Bootstrap.php';
require __DIR__.'/_core/WhatsAppWhmcsBridge.php';
init_schema();
$jobs=all("SELECT j.id,j.account_id,j.conversation_id FROM wa_ai_jobs j
    JOIN wa_connections w ON w.id=j.connection_id AND w.account_id=j.account_id
    WHERE j.status='pending' AND w.state='verified' AND w.whmcs_bridge_verified_at IS NOT NULL
    ORDER BY j.id ASC LIMIT 10");
$counts=['processed'=>0,'skipped'=>0,'failed'=>0];
foreach($jobs as $job){
    $out=wa_whmcs_run_job((int)$job['id']);
    if(($out['status']??'')==='accepted_not_delivered')$counts['processed']++;
    elseif(($out['status']??'')==='failed')$counts['failed']++;
    else $counts['skipped']++;
}
echo json_encode($counts,JSON_THROW_ON_ERROR)."\n";
