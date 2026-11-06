<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/VoiceFlowRuntime.php';
if(is_dir('/home/appontrack'))throw new RuntimeException('CI only');
function flow_check(bool $ok,string $case):void {if(!$ok)throw new RuntimeException('VOICE_FLOW_RUNTIME_FAIL '.$case);}
seed_console();
$agent=one("SELECT id,site_id FROM agents WHERE account_id=4 AND status!='archived' LIMIT 1");
$site=(int)$agent['site_id'];$aid=(int)$agent['id'];
$actor=(int)one("SELECT user_id FROM memberships WHERE account_id=4 AND role='owner' LIMIT 1")['user_id'];
$other=(int)one("SELECT user_id FROM memberships WHERE account_id=5 AND role='owner' LIMIT 1")['user_id'];
$knowledge=voice_knowledge_add(4,$site,'QA policy','txt',
    'المواعيد تقبل بعد استلام معرف حجز حقيقي من النظام.','public');
$graph=['nodes'=>[
 ['id'=>'s','type'=>'start','text'=>'مرحبا'],
 ['id'=>'reason','type'=>'reasoning','text'=>'لا تخترع حقائق'],
 ['id'=>'q','type'=>'approval','text'=>'موافقة على قراءة البيانات؟'],
 ['id'=>'know','type'=>'knowledge_lookup','text'=>'القاعدة المعتمدة'],
 ['id'=>'tool','type'=>'tool','text'=>'قراءة من موصل محدد'],
 ['id'=>'end','type'=>'end','text'=>'انتهينا'],
 ['id'=>'decline','type'=>'end','text'=>'لا توجد موافقة'],
 ],'edges'=>[
 ['from'=>'s','to'=>'reason'],['from'=>'reason','to'=>'q'],
 ['from'=>'q','to'=>'know','label'=>'نعم'],['from'=>'q','to'=>'decline','label'=>'لا'],
 ['from'=>'know','to'=>'tool'],['from'=>'tool','to'=>'end','label'=>'success']
 ]];
flow_check(count(flow_preview_compile($graph)['nodes'])===7,'new node vocabulary');
execute("UPDATE agents SET flow_json=? WHERE id=? AND account_id=4",
    [json_encode($graph,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$aid]);
$published=publish_agent_flow(4,$aid,$actor);
flow_check((int)$published['revision']>=1,'immutable published graph');
$link=voice_connector_add(4,$site,'whmcs','Contract WHMCS','https://example.com','/includes/api.php','whmcs','id:key');
execute("UPDATE voice_data_connectors SET status='verified' WHERE id=? AND account_id=4",[$link]);
$policy=voice_tool_policy_save(4,$site,$link,'whmcs.invoices','',true);
voice_flow_tool_bind(4,$site,$aid,'tool',$policy);
$callId='webdemo-abcdef12345678';
$start=voice_flow_start(4,$site,$aid,$callId,'web_preview',$actor);
flow_check(!$start['reused'],'new execution state');
$reused=voice_flow_start(4,$site,$aid,$callId,'web_preview',$actor);
flow_check($reused['reused']===true&&$reused['session_id']===$start['session_id'],'idempotent session');
$sessionId=(int)$start['session_id'];
$unauth=false;try{voice_flow_session(4,$sessionId,$other);}catch(InvalidArgumentException){$unauth=true;}
flow_check($unauth,'session owner isolated');
$one=voice_flow_process(4,$sessionId,$actor);
flow_check($one['state']['awaiting']==='q','waits for consent in graph');
$side=voice_flow_process(4,$sessionId,$actor,'ما أخبار الطقس؟');
flow_check($side['state']['awaiting']==='q'&&$side['state']['cursor']==='q',
    'side question preserves graph node');
$yes=voice_flow_process(4,$sessionId,$actor,'نعم');
flow_check($yes['status']==='awaiting_tool'&&$yes['state']['pending_tool']['policy_id']===$policy,
    'tool stopped for independent approval');
flow_check(implode('',array_map(static fn($e)=>$e['text']??'',$yes['events']))!=='',
    'approved knowledge used without inventing API result');
$prepared=voice_tool_prepare(4,$site,$policy,$actor,'voice-flow-invoice-abcdef1234',
    ['client_id'=>44],$callId);
$confirmed=voice_tool_confirm(4,$site,$prepared['request_id'],$actor,$prepared['approval_token'],true,
    static fn()=>voice_tool_result('whmcs.invoices',[
        'result'=>'success','invoices'=>['invoice'=>[['id'=>777,'status'=>'Paid']]]]));
flow_check($confirmed['status']==='succeeded','upstream result verified');
$linked=voice_flow_complete_tool(4,$sessionId,$actor,$prepared['request_id']);
flow_check($linked['status']==='active'&&$linked['state']['cursor']==='end','verified tool advances node');
$done=voice_flow_process(4,$sessionId,$actor);
flow_check($done['done']===true&&$done['status']==='completed','flow reaches end only after result');
flow_check(count(voice_flow_session_traces(4,$sessionId,$actor))>=6,'node traces captured');
$missing=voice_flow_start(4,$site,$aid,'webdemo-no-tool-bind','web_preview',$actor);
flow_check($missing['session_id']>0,'second test session');
$start2=voice_flow_process(4,$missing['session_id'],$actor);
$no=voice_flow_process(4,$missing['session_id'],$actor,'لا');
flow_check($no['status']==='completed','decline exits without tool');
$failed=voice_flow_start(4,$site,$aid,'webdemo-err12345678','web_preview',$actor);
voice_flow_process(4,$failed['session_id'],$actor);
voice_flow_process(4,$failed['session_id'],$actor,'نعم');
$request=voice_tool_prepare(4,$site,$policy,$actor,'voice-flow-fail-abcdef1234',['client_id'=>44],'webdemo-err12345678');
$unknown=voice_tool_confirm(4,$site,$request['request_id'],$actor,$request['approval_token'],true,
    static function(){throw new RuntimeException('remote_http_503');});
flow_check($unknown['status']==='failed','read failure remains failed');
$blocked=voice_flow_complete_tool(4,$failed['session_id'],$actor,$request['request_id']);
flow_check($blocked['status']==='blocked'&&$blocked['done'],'failed tool cannot advance as success');
$again=voice_flow_process(4,$failed['session_id'],$actor);
flow_check($again['status']==='blocked','blocked remains terminal');
// Missing policy binding is terminal, not a permanently awaiting external tool.
execute("DELETE FROM voice_flow_node_tools WHERE account_id=? AND site_id=? AND agent_id=? AND node_id=?",
    [4,$site,$aid,'tool']);
$unbound=voice_flow_start(4,$site,$aid,'webdemo-unbound1234','web_preview',$actor);
voice_flow_process(4,(int)$unbound['session_id'],$actor);
$stopped=voice_flow_process(4,(int)$unbound['session_id'],$actor,'نعم');
flow_check($stopped['status']==='blocked'&&$stopped['done']===true&&
    $stopped['state']['pending_tool']===null&&($stopped['state']['blocked']??false)===true,
    'unbound tool fails closed without awaiting forever');
$stoppedAgain=voice_flow_process(4,(int)$unbound['session_id'],$actor);
flow_check($stoppedAgain['status']==='blocked'&&$stoppedAgain['done']===true,
    'unbound flow remains terminal on retry');
$badComplete=false;
try{voice_flow_complete_tool(4,(int)$unbound['session_id'],$actor,999999);}
catch(InvalidArgumentException){$badComplete=true;}
flow_check($badComplete,'unbound tool cannot be released by fabricated completion');
// Existing sessions from earlier releases with placeholder policy_id=0 are reconciled.
$legacy=voice_flow_start(4,$site,$aid,'webdemo-legacy1234','web_preview',$actor);
$legacySessionId=(int)$legacy['session_id'];
$legacyState=['cursor'=>'tool','awaiting'=>null,'fields'=>[],'last_answer'=>null,
    'pending_tool'=>['node_id'=>'tool','policy_id'=>0,'blocked'=>true],
    'turns'=>1,'steps'=>3,'done'=>false];
execute("UPDATE voice_flow_sessions SET state_json=?,status='awaiting_tool'
    WHERE id=? AND account_id=?",[
    json_encode($legacyState,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$legacySessionId,4]);
$legacyResult=voice_flow_process(4,$legacySessionId,$actor);
flow_check($legacyResult['status']==='blocked'&&$legacyResult['done']===true&&
    $legacyResult['state']['pending_tool']===null,'legacy unbound tool session recovers');
flow_check(voice_flow_process(4,$legacySessionId,$actor)['status']==='blocked',
    'legacy reconciliation idempotent');
echo "VOICE_FLOW_RUNTIME_STATES_AND_TRACES_PASS\n";
