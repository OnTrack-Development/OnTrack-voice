<?php
declare(strict_types=1);
/** CI only: real DB lifecycle, mock Live tool response, no Google credentials. */
if(is_dir('/home/appontrack'))throw new RuntimeException('CI_ONLY');
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/FlowAiJobs.php';
seed_console();
flow_ai_jobs_init();
function job_assert(bool $ok,string $why):void{if(!$ok)throw new RuntimeException('ASYNC_JOB_FAIL: '.$why);}
$agent=one("SELECT id FROM agents WHERE account_id=4 AND status!='archived' ORDER BY id LIMIT 1");
$user=one("SELECT user_id AS id FROM memberships WHERE account_id=4 LIMIT 1");
job_assert(is_array($agent)&&is_array($user),'seeded account and agent');
$aid=(int)$agent['id'];$uid=(int)$user['id'];
$graph=['nodes'=>[
  ['id'=>'start','type'=>'start','text'=>'ابدأ','x'=>180,'y'=>50],
  ['id'=>'end','type'=>'end','text'=>'انتهى','x'=>180,'y'=>240]
], 'edges'=>[['from'=>'start','to'=>'end','label'=>'التالي']]];
$id=flow_ai_job_enqueue(4,$uid,$aid,'graph','اعمل مخطط خدمة عملاء كامل',$graph,[]);
job_assert(($s=flow_ai_job_status($id,4,$uid,$aid))['status']==='pending','job queued');
job_assert(flow_ai_job_active(4,$uid,$aid)!==null,'resume active task');
$blocked=false;
try{flow_ai_job_status($id,5,$uid,$aid);}catch(InvalidArgumentException){$blocked=true;}
job_assert($blocked,'other account cannot poll result');
$blocked=false;
try{flow_ai_job_enqueue(4,$uid,$aid,'graph','نفس المهمة مرة أخرى',$graph,[]);}catch(InvalidArgumentException){$blocked=true;}
job_assert($blocked,'same agent cannot enqueue duplicate active request');
$transport=static fn(array $setup,array $turn):array=>[
    'toolCall'=>['functionCalls'=>[['name'=>'submit_editor_proposal','args'=>$graph]]]
];
$r=flow_ai_job_run($id,$transport);
job_assert($r['status']==='done','background runner completed');
$done=flow_ai_job_status($id,4,$uid,$aid);
job_assert($done['status']==='done'&&$done['result']['graph']===$graph,'poll gets validated result');
job_assert(flow_ai_chat_read(4,$uid,$aid)['pending_graph']===$graph,'proposal preserved for review');
job_assert(flow_ai_job_run($id,$transport)['status']==='skipped','idempotent completion');
$id2=flow_ai_job_enqueue(4,$uid,$aid,'graph','اختبار إلغاء مهمة Live',$graph,[]);
flow_ai_job_cancel_scope(4,$uid,$aid);
job_assert(flow_ai_job_status($id2,4,$uid,$aid)['status']==='cancelled','cancel prevents late writes');
job_assert(flow_ai_job_run($id2,$transport)['status']==='skipped','cancelled job not executed');
echo "FLOW_AI_ASYNC_LIVE_JOB_QUEUE_PASS\n";
