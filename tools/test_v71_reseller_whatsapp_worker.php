<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/CustomerAccounts.php';
require_once __DIR__.'/../saas/console/_core/WhatsAppNativeAgent.php';
require_once __DIR__.'/../saas/console/_core/WhatsAppWhmcsBridge.php';
require_once __DIR__.'/../saas/console/_core/WhatsAppJobSafety.php';
if(is_dir('/home/appontrack')&&!getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('v71_requires_isolated_fixture');
function v71(bool $ok,string $reason):void {
    if(!$ok)throw new RuntimeException('V71_WA_PARENT_SUSPEND_FAIL '.$reason);
}
seed_console();
$admin=['kind'=>'platform','account_id'=>1,'role'=>'owner'];
$site=one("SELECT id FROM sites WHERE account_id=5 AND status='active' LIMIT 1");
$agent=one("SELECT id FROM agents WHERE account_id=5 AND site_id=? LIMIT 1",[$site['id']]);
v71($site!==null&&$agent!==null,'child has a site and an agent');
if(!published_agent_flow(5,(int)$site['id'],(int)$agent['id']))
    publish_agent_flow(5,(int)$agent['id'],1);
$channel=wa_create_connection(5,(int)$site['id'],'Suspended reseller proof',
    '1099000071000','fake-meta-token','fake-app-secret','verify-token-for-fixture-71000',
    '','',(int)$agent['id']);
$c=wa_connection($channel,5);
wa_test_meta_connection($c,static fn()=>['status'=>200,'body'=>'{"id":"1099000071000"}']);
putenv('ONTRACK_GEMINI_API_KEY=isolated-no-provider');
putenv('ONTRACK_GEMINI_LIVE_MODEL=gemini-3.5-flash');
wa_native_toggle(5,$channel,true);
$c=wa_connection($channel,5);
$event=['object'=>'whatsapp_business_account','entry'=>[[
    'changes'=>[['value'=>[
        'metadata'=>['phone_number_id'=>'1099000071000'],
        'messages'=>[['id'=>'wamid.v71_parent_fixture_01',
            'from'=>'201001234567','type'=>'text',
            'text'=>['body'=>'مساء الخير']]]]
    ]]]]];
$ingested=wa_webhook_ingest($c,$event);
v71($ingested['accepted']===1,'incoming message saved');
$job=one("SELECT * FROM wa_ai_jobs WHERE account_id=5 AND connection_id=?
    AND source_message_id='wamid.v71_parent_fixture_01'",[$channel]);
v71($job!==null&&$job['status']==='pending','durable child message queued');
$id=(int)$job['id'];
$models=0;$sends=0;
$mockAi=static function()use(&$models):string{
    $models++;
    return $models%2===1
        ? '{"decision":"reply","reply_text":"أهلًا بحضرتك","buttons":[]}'
        : '{"decision":"reply","reply_text":"أهلًا بحضرتك","buttons":[],"approved":true}';
};
$mockMeta=static function()use(&$sends):array{
    $sends++;
    return ['status'=>200,'body'=>'{"messages":[{"id":"wamid.v71_parent_out_01"}]}'];
};
customer_accounts_status($admin,2,'suspended');
v71(wa_native_run_job($id,$mockAi,$mockMeta)['status']==='service_suspended',
    'native agent cannot run while parent suspended');
v71(wa_whmcs_run_job($id,static function(){throw new RuntimeException('bridge_should_not_run');},$mockMeta)['status']==='service_suspended',
    'WHMCS bridge cannot run while parent suspended');
v71($models===0&&$sends===0,'no AI, WHMCS or Meta transport invoked');
v71(one('SELECT status,attempts FROM wa_ai_jobs WHERE id=?',[$id])['status']==='pending',
    'suspended pending job preserved');
execute("UPDATE wa_ai_jobs SET status='processing',attempts=1 WHERE id=?",[$id]);
$blocked=wa_job_send_once($job,'رد تمت مراجعته',$mockMeta,[],true);
$stopped=one('SELECT status,attempts FROM wa_ai_jobs WHERE id=?',[$id]);
v71($blocked['status']==='service_suspended'&&$stopped['status']==='pending'&&
    (int)$stopped['attempts']===0&&$sends===0,
    'pre-send transaction rechecks parent and preserves retry budget');
customer_accounts_status($admin,2,'active');
$out=wa_native_run_job($id,$mockAi,$mockMeta);
v71($out['status']==='accepted_not_delivered'&&$models===2&&$sends===1,
    'parent restoration resumes exactly one reviewed Meta send');
v71(wa_native_run_job($id,$mockAi,$mockMeta)['status']==='not_pending'&&$sends===1,
    'replay after restoration never sends duplicate message');
v71((string)one('SELECT status FROM accounts WHERE id=5')['status']==='active',
    'child account state never rewritten');
echo "V71_RESELLER_SUSPEND_NATIVE_WHATSAPP_WHMCS_BRIDGE_NO_DUPLICATE_PASS\n";
