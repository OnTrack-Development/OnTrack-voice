<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/VoiceFlowRuntime.php';
if(is_dir('/home/appontrack')&&!getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('isolated_fixture_only');
function v67flow(bool $ok,string $why):void {
    if(!$ok)throw new RuntimeException('V67_DEVICE_FLOW_FAIL '.$why);
}
seed_console();
$account=4;
$agent=one("SELECT id,site_id FROM agents WHERE account_id=? AND status!='archived' LIMIT 1",[$account]);
$site=(int)$agent['site_id'];$aid=(int)$agent['id'];
$owner=(int)one("SELECT user_id FROM memberships WHERE account_id=? AND role='owner' LIMIT 1",[$account])['user_id'];
$graph=['nodes'=>[
 ['id'=>'s','type'=>'start','text'=>'مرحبا'],
 ['id'=>'q','type'=>'ask','text'=>'نراجع فاتورتك؟'],
 ['id'=>'t','type'=>'crm_lookup','text'=>'اطلب إذن المسؤول قبل القراءة'],
 ['id'=>'e','type'=>'end','text'=>'تم'],
 ['id'=>'decline','type'=>'end','text'=>'حاضر']],
 'edges'=>[
 ['from'=>'s','to'=>'q'],['from'=>'q','to'=>'t','label'=>'نعم'],
 ['from'=>'q','to'=>'decline','label'=>'لا'],['from'=>'t','to'=>'e','label'=>'success']]];
execute('UPDATE agents SET flow_json=? WHERE id=?',[
 json_encode($graph,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$aid]);
publish_agent_flow($account,$aid,$owner);
$conn=voice_connector_add($account,$site,'whmcs','V67 scoped WHMCS',
  'https://example.com','/includes/api.php','whmcs','id:secret');
execute("UPDATE voice_data_connectors SET status='verified' WHERE id=?",[$conn]);
$policy=voice_tool_policy_save($account,$site,$conn,'whmcs.invoices','',true);
voice_flow_tool_bind($account,$site,$aid,'t',$policy);
$binding=insert("INSERT INTO device_bindings(account_id,site_id,agent_id,
 installation_id,sim_slot,slot_claim,device_name,token_hash,status,created_at)
 VALUES (?,?,?,?,?,?,?,?,?,?)",[
  $account,$site,$aid,'device-flow-v67',0,hash('sha256','device-flow-v67|0'),
  'Test SIM',hash('sha256','auth-'.bin2hex(random_bytes(10))),'active',now()]);
$call='call-v67-'.bin2hex(random_bytes(10));
$opened=voice_flow_start($account,$site,$aid,$call,'device',0,$binding);
v67flow(!$opened['reused']&&$opened['status']==='active','real device binding creates state');
$start=voice_flow_process($account,$opened['session_id'],0,null,$binding);
v67flow($start['state']['awaiting']==='q','graph deterministic first question');
$waiting=voice_flow_process($account,$opened['session_id'],0,'نعم',$binding);
v67flow($waiting['status']==='awaiting_tool'&&
   (int)$waiting['state']['pending_tool']['policy_id']===$policy,
   'tool cannot execute by prompt alone');
$denied=false;
try{voice_flow_complete_tool($account,$opened['session_id'],$owner,111111);}
catch(InvalidArgumentException){$denied=true;}
v67flow($denied,'device flow cannot be completed without matching physical binding');
$request=voice_tool_prepare($account,$site,$policy,$owner,
  'v67-real-call-'.bin2hex(random_bytes(10)),['client_id'=>42],$call);
$approved=voice_tool_confirm($account,$site,$request['request_id'],$owner,
    $request['approval_token'],true,
    static fn()=>voice_tool_result('whmcs.invoices',[
       'result'=>'success','invoices'=>['invoice'=>[['id'=>72,'status'=>'Paid']]]]));
v67flow($approved['status']==='succeeded','real external result structured and verified');
$result=voice_flow_complete_tool($account,$opened['session_id'],$owner,
   $request['request_id'],$binding);
v67flow($result['status']==='active'&&$result['state']['cursor']==='e',
   'authorized human completion advances only matching SIM flow node');
$done=voice_flow_process($account,$opened['session_id'],0,null,$binding);
v67flow($done['status']==='completed','device flow completes after provider success');
v67flow(count(all('SELECT request_id FROM voice_flow_tool_consumptions WHERE request_id=?',
  [$request['request_id']]))===1,'idempotent tool result consumed once');
execute("UPDATE device_bindings SET status='revoked' WHERE id=?",[$binding]);
$denied=false;
try{voice_flow_start($account,$site,$aid,'call-revoked-456789','device',0,$binding);}
catch(InvalidArgumentException){$denied=true;}
v67flow($denied,'revoked device denied another state machine');
echo "V67_PAIRED_DEVICE_DETERMINISTIC_FLOW_AND_VERIFIED_TOOL_BRIDGE_PASS\n";
