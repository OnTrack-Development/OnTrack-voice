<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/OperationalOutbox.php';
if(is_dir('/home/appontrack')&&!getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('v72_requires_isolated_fixture');
function v72(bool $ok,string $why):void {
    if(!$ok)throw new RuntimeException('V72_WORKER_HEALTH_FAIL '.$why);
}
seed_console();
$epoch=time();
$now=gmdate('Y-m-d H:i:s',$epoch);
$workerNames=['operational_outbox','operational_email','flow_ai','whatsapp_ai','knowledge_sync'];
$rows=array_map(static fn(string $name):array=>[
    'worker'=>$name,'last_run_at'=>$now,'last_success_at'=>$now,
    'scanned'=>1,'delivered'=>1,'failed'=>0
],$workerNames);
$ok=operational_workers_assess($rows,$epoch);
v72($ok['all_healthy']&&$ok['counts']['healthy']===5,'five current workers healthy');
$missing=operational_workers_assess(array_slice($rows,0,4),$epoch);
v72(!$missing['all_healthy']&&$missing['counts']['missing']===1,
    'missing knowledge worker must be visible');
$stale=$rows;
$stale[1]['last_run_at']=gmdate('Y-m-d H:i:s',$epoch-2000);
$old=operational_workers_assess($stale,$epoch);
v72($old['counts']['stale']===1,'stale operational email worker blocks healthy status');
$failed=$rows;
$failed[2]['last_success_at']=null;
$bad=operational_workers_assess($failed,$epoch);
v72($bad['counts']['failed']===1,'worker without recent successful run is failed');
$future=$rows;
$future[3]['last_run_at']=gmdate('Y-m-d H:i:s',$epoch+3600);
$badClock=operational_workers_assess($future,$epoch);
v72($badClock['counts']['invalid']===1,'future heartbeats never mark healthy');
foreach($workerNames as $name)operational_worker_beat($name,1,1,0);
$admin=operational_outbox_status(1,true);
v72($admin['health']['all_healthy']===true&&count($admin['workers'])===5,
    'platform admin receives complete current heartbeat snapshot');
$tenant=operational_outbox_status(5,false);
v72($tenant['workers']===[] && $tenant['health']===null,
    'customer and reseller cannot read platform-wide worker health');
echo "V72_WORKER_HEARTBEAT_STALE_MISSING_FAILED_AND_RBAC_PASS\n";
