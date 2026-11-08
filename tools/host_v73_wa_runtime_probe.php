<?php
declare(strict_types=1);
/** Production WhatsApp worker READ-ONLY runtime probe, no outbound I/O.
 * Operated over approved SSH from private staging. Never prints tenant data.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
try{
    require '/home/appontrack/public_html/_core/Bootstrap.php';
    $driver=driver();
    $pending=(int)(one("SELECT COUNT(*) n FROM wa_ai_jobs WHERE status='pending'")['n']??0);
    $eligible=(int)(one("SELECT COUNT(*) n FROM wa_ai_jobs j
        JOIN wa_connections w ON w.id=j.connection_id AND w.account_id=j.account_id
        JOIN accounts a ON a.id=j.account_id AND a.status='active'
        WHERE j.status='pending' AND j.attempts<2 AND w.state='verified'
        AND ((w.ai_enabled=1 AND w.ai_agent_id IS NOT NULL)
        OR (w.whmcs_bridge_verified_at IS NOT NULL AND w.whmcs_bridge_url IS NOT NULL))")['n']??0);
    // Validate the exact worker SELECT schema without acquiring jobs or
    // sending to Meta; LIMIT 0 still detects stale production columns.
    $selection=all("SELECT j.id,j.account_id,j.conversation_id,w.ai_enabled,w.ai_agent_id,
        w.whmcs_bridge_verified_at,w.whmcs_bridge_url,w.onboarding_method
        FROM wa_ai_jobs j
        JOIN wa_connections w ON w.id=j.connection_id AND w.account_id=j.account_id
        JOIN accounts a ON a.id=j.account_id AND a.status='active'
        WHERE j.status='pending' AND j.attempts<2 AND w.state='verified'
        AND ((w.ai_enabled=1 AND w.ai_agent_id IS NOT NULL)
        OR (w.whmcs_bridge_verified_at IS NOT NULL AND w.whmcs_bridge_url IS NOT NULL))
        ORDER BY j.id ASC LIMIT 0");
    $heartbeat=one("SELECT worker,last_run_at,last_success_at FROM
        operational_worker_heartbeat WHERE worker='whatsapp_ai'");
    $report=['probe'=>'v73_wa_read_only','database_driver'=>$driver,
        'pending_jobs_total'=>$pending,'potential_worker_jobs'=>$eligible,
        'whatsapp_heartbeat_seen'=>$heartbeat!==null,
        'last_run_age_seconds'=>$heartbeat!==null?
            max(0,time()-(int)strtotime((string)$heartbeat['last_run_at'].' UTC')):null,
        'no_meta_or_whmcs_requests'=>true,'read_only'=>true];
    echo json_encode($report,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $error){
    fwrite(STDERR,'V73_WA_READ_ONLY_PROBE_ERROR '.get_class($error)."\n");
    exit(1);
}
