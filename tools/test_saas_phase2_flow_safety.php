<?php
declare(strict_types=1);
/**
 * Phase 2: deterministic Flow connector safety and terminal completion.
 * Isolated SQLite fixture only; NO network, SIM, deployment or real provider calls.
 */
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/VoiceFlowRuntime.php';
if(is_dir('/home/appontrack') && !getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('saas_phase2_fixture_only');
function core2_ok(bool $ok,string $why):void {
    if(!$ok)throw new RuntimeException('SAAS_PHASE2_FLOW_FAIL '.$why);
}
seed_console();
$agent=one("SELECT id,site_id,flow_json FROM agents WHERE account_id=4 AND status!='archived' LIMIT 1");
core2_ok($agent!==null,'seed agent');
$site=(int)$agent['site_id'];$aid=(int)$agent['id'];
$actor=(int)one("SELECT user_id FROM memberships WHERE account_id=4 AND role='owner' LIMIT 1")['user_id'];
$graph=['nodes'=>[
    ['id'=>'start','type'=>'start','text'=>''],
    ['id'=>'read','type'=>'crm_lookup','text'=>''],
    ['id'=>'end','type'=>'end','text'=>'']
], 'edges'=>[['from'=>'start','to'=>'read']]];
flow_preview_compile($graph);
execute('UPDATE agents SET flow_json=? WHERE id=? AND account_id=4',[
    json_encode($graph,JSON_THROW_ON_ERROR),$aid]);
publish_agent_flow(4,$aid,$actor);
$connector=voice_connector_add(4,$site,'whmcs','Flow E2E verified',
    'https://example.com','/includes/api.php','whmcs','id:secret');
execute("UPDATE voice_data_connectors SET status='verified' WHERE id=? AND account_id=4",[$connector]);
$readPolicy=voice_tool_policy_save(4,$site,$connector,'whmcs.invoices','',true);
$writePolicy=voice_tool_policy_save(4,$site,$connector,'whmcs.ticket.create','',true);
voice_flow_tool_bind(4,$site,$aid,'read',$readPolicy);
$context=['account_id'=>4,'site_id'=>$site,'agent_id'=>$aid];
$node=['id'=>'read','type'=>'crm_lookup'];
$verified=voice_flow_live_tool_policy($context,$node);
core2_ok($verified!==null && (int)$verified['id']===$readPolicy,'verified read policy accepted');
core2_ok(voice_flow_live_tool_policy($context,['id'=>'read','type'=>'crm_write'])===null,
    'write-node cannot silently inherit a read policy');
$callId='phase2-flow-e2e-abc123';
$start=voice_flow_start(4,$site,$aid,$callId,'web_preview',$actor);
$first=voice_flow_process(4,(int)$start['session_id'],$actor);
core2_ok($first['status']==='awaiting_tool' &&
    (int)$first['state']['pending_tool']['policy_id']===$readPolicy,
    'real published tool requests explicit approval');
$request=voice_tool_prepare(4,$site,$readPolicy,$actor,'phase2-invoices-approval-abc123',
    ['client_id'=>44],$callId);
$approved=voice_tool_confirm(4,$site,(int)$request['request_id'],$actor,
    (string)$request['approval_token'],true,
    static fn()=>voice_tool_result('whmcs.invoices',[
        'result'=>'success','invoices'=>['invoice'=>[['id'=>567,'status'=>'Paid']]
    ]]));
core2_ok($approved['status']==='succeeded','fake provider return explicitly verified');
$completed=voice_flow_complete_tool(4,(int)$start['session_id'],$actor,(int)$request['request_id']);
core2_ok($completed['status']==='completed' && $completed['done']===true &&
    (string)one('SELECT status FROM voice_flow_sessions WHERE id=?',[$start['session_id']])['status']==='completed',
    'successful terminal tool is completed, never mislabeled blocked');
$replayRejected=false;
try{voice_flow_complete_tool(4,(int)$start['session_id'],$actor,(int)$request['request_id']);}
catch(InvalidArgumentException){$replayRejected=true;}
core2_ok($replayRejected,'completed tool cannot execute twice');
// A previously approved node is no longer eligible when its API connector
// is disabled or its node mapping is reconfigured to a write-only policy.
execute('UPDATE voice_tool_policies SET enabled=0 WHERE id=? AND account_id=4',[$readPolicy]);
core2_ok(voice_flow_live_tool_policy($context,$node)===null,
    'disabled policy cannot remain executable through old binding');
$other=voice_flow_start(4,$site,$aid,'phase2-disabled-node-abc123','web_preview',$actor);
$blocked=voice_flow_process(4,(int)$other['session_id'],$actor);
core2_ok($blocked['status']==='blocked' && $blocked['done']===true &&
    $blocked['state']['pending_tool']===null,
    'disabled connector fails closed, does not wait forever or execute');
execute('UPDATE voice_tool_policies SET enabled=1 WHERE id=? AND account_id=4',[$readPolicy]);
execute("UPDATE voice_flow_node_tools SET policy_id=? WHERE account_id=4 AND site_id=? AND agent_id=? AND node_id='read'",
    [$writePolicy,$site,$aid]);
core2_ok(voice_flow_live_tool_policy($context,$node)===null,
    'stale node cannot be upgraded to a write connector without new approved mapping');
$other2=voice_flow_start(4,$site,$aid,'phase2-stale-node-abc123','web_preview',$actor);
$blocked2=voice_flow_process(4,(int)$other2['session_id'],$actor);
core2_ok($blocked2['status']==='blocked','stale read/write binding blocks access');
core2_ok(voice_flow_process(4,(int)$start['session_id'],$actor)['status']==='completed',
    'previously completed flow remains stable after policy changes');
echo "SAAS_PHASE2_FLOW_NONREGRESSION_PASS\n";
