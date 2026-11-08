<?php
declare(strict_types=1);
/**
 * Dedicated CLI-only durable WhatsApp dispatch. No HTTP entry and no
 * reactivation of previously stopped unrelated Host-Link / monitoring.
 * A stale 'sending' delivery is reviewed, NEVER blindly resent.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/_core/Bootstrap.php';
require __DIR__.'/_core/WhatsAppNativeAgent.php';
require __DIR__.'/_core/WhatsAppWhmcsBridge.php';
require __DIR__.'/_core/WhatsAppJobSafety.php';
init_schema();
$lockPath=private_path().'/.whatsapp-worker.lock';
$lock=@fopen($lockPath,'c');
if($lock===false)throw new RuntimeException('whatsapp_worker_lock_unavailable');
if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);exit(0);}
try{
    $recovered=wa_recover_stale_jobs(180,100);
    $jobs=all("SELECT j.id,j.account_id,j.conversation_id,w.ai_enabled,w.ai_agent_id,
        w.whmcs_bridge_verified_at,w.onboarding_method
        FROM wa_ai_jobs j
        JOIN wa_connections w ON w.id=j.connection_id AND w.account_id=j.account_id
        JOIN accounts a ON a.id=j.account_id AND a.status='active'
        WHERE j.status='pending' AND j.attempts<2 AND w.state='verified'
        AND ((w.ai_enabled=1 AND w.ai_agent_id IS NOT NULL)
             OR w.whmcs_bridge_verified_at IS NOT NULL)
        ORDER BY j.id ASC LIMIT 10");
    $counts=['native_sent'=>0,'bridge_sent'=>0,'skipped'=>0,'failed'=>0,
        'needs_review'=>0,'recovered'=>$recovered];
    foreach($jobs as $job){
        $native=(int)$job['ai_enabled']===1&&(int)$job['ai_agent_id']>0;
        $result=$native?wa_native_run_job((int)$job['id'])
            :wa_whmcs_run_job((int)$job['id']);
        $status=(string)($result['status']??'');
        if($status==='accepted_not_delivered')$counts[$native?'native_sent':'bridge_sent']++;
        elseif($status==='needs_review'||$status==='outbound_adapter_unavailable')
            $counts['needs_review']++;
        elseif($status==='failed')$counts['failed']++;
        else $counts['skipped']++;
    }
    echo json_encode($counts,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}finally{
    flock($lock,LOCK_UN);
    fclose($lock);
}
