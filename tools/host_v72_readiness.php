<?php
declare(strict_types=1);
/** Read-only production readiness probe. SSH/CLI only, outside public_html.
 * Never prints customer data, rows, database credentials, SMTP or API keys.
 * Healthy heartbeat means worker ran, not that a provider delivered a message.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
const V72_HOST_ROOT='/home/appontrack/public_html';
try {
    require_once V72_HOST_ROOT.'/_core/Bootstrap.php';
    require_once V72_HOST_ROOT.'/_core/OperationalOutbox.php';
    $driver=driver();
    if(!in_array($driver,['mysql','sqlite'],true))
        throw new RuntimeException('unsupported_host_database_driver');
    $ping=one('SELECT 1 AS ok');
    if((int)($ping['ok']??0)!==1)throw new RuntimeException('db_read_ping_failed');
    $required=['accounts','users','memberships','plans','subscriptions',
        'device_bindings','outbox','operational_email_jobs','wa_ai_jobs',
        'operational_worker_heartbeat'];
    $tables=[];
    foreach($required as $table){
        $present=$driver==='mysql'
            ?one('SELECT 1 AS present FROM information_schema.tables
                WHERE table_schema=DATABASE() AND table_name=? LIMIT 1',[$table])
            :one("SELECT 1 AS present FROM sqlite_master
                WHERE type='table' AND name=? LIMIT 1",[$table]);
        $tables[$table]=$present!==null;
    }
    $workers=[];
    if($tables['operational_worker_heartbeat']){
        $workers=all('SELECT worker,last_run_at,last_success_at,scanned,delivered,failed
            FROM operational_worker_heartbeat ORDER BY worker LIMIT 30');
    }
    $health=operational_workers_assess($workers);
    $statuses=[];
    foreach($health['workers'] as $worker)
        $statuses[(string)$worker['worker']]=(string)$worker['status'];
    // A read-only SELECT, not a backup or a restored-database test.
    $missing=array_keys(array_filter($tables,static fn(bool $present):bool=>!$present));
    $warnings=[];
    if($driver!=='mysql')$warnings[]='production_database_not_mysql';
    if($missing!==[])$warnings[]='required_schema_tables_missing';
    if(!$health['all_healthy'])$warnings[]='workers_missing_stale_or_failed';
    $report=[
        'probe'=>'v72_read_only',
        'database_driver'=>$driver,
        'mysql_connection_verified'=>$driver==='mysql',
        'read_only_ping'=>'pass',
        'required_tables_checked'=>count($tables),
        'missing_tables'=>$missing,
        'worker_health'=>$statuses,
        'worker_health_counts'=>$health['counts'],
        'worker_heartbeat_ok'=>$health['all_healthy'],
        'backup_restore_tested'=>false,
        'external_whmcs_meta_smtp_delivery_tested'=>false,
        'owner_sim_e2e_tested'=>false,
        'warnings'=>$warnings,
        'operational_readiness'=>$warnings===[]?'partial_pending_restore_and_external_e2e':'needs_attention'
    ];
    echo json_encode($report,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $failure){
    // Error class only; SQL errors might otherwise include connection metadata.
    fwrite(STDERR,'V72_HOST_READINESS_PROBE_FAILED '.get_class($failure)."\n");
    exit(1);
}
