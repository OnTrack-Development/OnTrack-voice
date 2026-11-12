<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/DevicePairing.php';
require_once __DIR__.'/../saas/console/_core/TenantWorkspace.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
function finding_ok(bool $value,string $why):void{
    if(!$value)throw new RuntimeException('SIM_CALL_FINDINGS_FAILED: '.$why);
}
seed_console();
$owner=one("SELECT user_id FROM memberships WHERE account_id=4 AND role='owner' LIMIT 1");
$actor=(int)$owner['user_id'];$addedAgent=null;$device=null;
$callId='11223344-5566-4788-aabb-123456789abc';
try {
    $base=one('SELECT * FROM agents WHERE id=1 AND account_id=4 AND site_id=1');
    finding_ok($base!==null,'seeded account/site');
    publish_agent_flow(4,1,$actor);
    $addedAgent=insert("INSERT INTO agents(account_id,site_id,name,voice,instructions,knowledge,flow_json,status,created_at)
      VALUES (?,?,?,?,?,?,?,'draft',?)",[
        4,1,'مساعد شريحة بديل','google.aoede','مساعد مراجعة','بيانات تجربة',
        $base['flow_json'],now()]);
    publish_agent_flow(4,$addedAgent,$actor);
    $code=device_pairing_create(4,1,1,$actor,0);
    $bound=device_pairing_redeem($code,'test-agent-switch-device-001',0,'SIM 1 test');
    $device=(int)$bound['device_id'];
    $secret=$bound['device_token'];
    $original=device_binding_authorize($secret);
    finding_ok((int)$original['agent_id']===1,'initial SIM agent');
    $updated=device_binding_change_agent(4,$device,$addedAgent);
    finding_ok($updated['changed']&&$updated['previous_agent_id']===1,'old agent correctly replaced');
    finding_ok($updated['agent_revision']===2,'first SIM agent revision increment');
    $current=device_binding_authorize($secret);
    finding_ok($current!==null && (int)$current['agent_id']===$addedAgent,'same token sees new agent');
    finding_ok((int)$current['id']===$device&&(int)$current['sim_slot']===0 &&
        (string)$current['token_hash']===(string)$original['token_hash'] &&
        (string)$current['slot_claim']===(string)$original['slot_claim'],
        'no QR, installation, phone slot, or bearer rotation');
    $profile=device_binding_heartbeat($current);
    finding_ok($profile['agent_id']===$addedAgent&&$profile['flow_ready']===true,
        'new published agent reaches next heartbeat');
    finding_ok($profile['agent_revision']===2,'heartbeat exposes changed agent revision');
    $unchanged=device_binding_change_agent(4,$device,$addedAgent);
    finding_ok(!$unchanged['changed']&&$unchanged['agent_revision']===2,
        'repeat agent selection idempotent; revision unchanged');
    $reject=false;
    try{device_binding_change_agent(5,$device,3);}catch(InvalidArgumentException){$reject=true;}
    finding_ok($reject,'other tenant cannot modify SIM agent');
    $reject=false;
    try{device_binding_change_agent(4,$device,2);}catch(InvalidArgumentException){$reject=true;}
    finding_ok($reject,'agent from other site denied');
    $reject=false;
    try{device_binding_change_agent(4,$device,1);}catch(InvalidArgumentException){$reject=true;}
    finding_ok(!$reject,'previous published agent remains selectable');
    finding_ok((int)device_binding_authorize($secret)['agent_id']===1,'restore original without re-pair');
    $history=all('SELECT agent_id,agent_revision FROM device_agent_revisions WHERE device_id=? ORDER BY agent_revision',[$device]);
    finding_ok(count($history)===3 &&
        (int)$history[0]['agent_id']===1 &&
        (int)$history[1]['agent_id']===$addedAgent &&
        (int)$history[2]['agent_id']===1 &&
        (int)$history[2]['agent_revision']===3,
        'original agent, replacement and revert retain immutable history');
    $binding=device_binding_authorize($secret);
    $ring=['call_id'=>$callId,'sequence'=>1,'phase'=>'ringing','duration_seconds'=>0];
    finding_ok(tenant_workspace_call_event($binding,$ring)['accepted'],'call event');
    $ident=['call_id'=>$callId,'sequence'=>2,'phase'=>'identified','duration_seconds'=>0,
        'phone_number'=>'+201011122233','contact_name'=>'أحمد الاختبار'];
    finding_ok(tenant_workspace_call_event($binding,$ident)['accepted'],'phone/contact identity saved');
    $ending=['call_id'=>$callId,'sequence'=>3,'phase'=>'ended','duration_seconds'=>48];
    finding_ok(tenant_workspace_call_event($binding,$ending)['accepted'],'ended event');
    // R040: late/out-of-order delivery cannot reopen a terminal call.
    $late=['call_id'=>$callId,'sequence'=>4,'phase'=>'active','duration_seconds'=>12];
    finding_ok(tenant_workspace_call_event($binding,$late)['accepted'],'late active accepted as ledger event');
    $replay=tenant_workspace_call_event($binding,$late);
    finding_ok($replay['duplicate']===true,'late event replay idempotent');
    $lateRing=['call_id'=>$callId,'sequence'=>5,'phase'=>'ringing','duration_seconds'=>0];
    tenant_workspace_call_event($binding,$lateRing);
    $state=one('SELECT status,duration_seconds FROM calls WHERE device_binding_id=? AND external_call_id=?',[$device,$callId]);
    finding_ok($state['status']==='ended'&&(int)$state['duration_seconds']===48,
        'terminal status and duration cannot regress after late events');
    $events=all('SELECT sequence FROM tenant_call_events WHERE device_binding_id=? AND call_id=?',[$device,$callId]);
    finding_ok(count($events)===5,'exactly one ledger event per sequence');
    $usageRef='voice-'.hash('sha256','4:1:'.$device.':0:'.$callId);
    $counted=all("SELECT id,units FROM usage_ledger
        WHERE account_id=4 AND kind='connected_call' AND reference=?",[$usageRef]);
    finding_ok(count($counted)===1&&(int)$counted[0]['units']===1,
        'real answered and ended GSM call metered once despite replay');

    // LTE can deliver model function-call summaries even when Google Live
    // does not deliver input/output transcription. This must still persist.
    $draft=tenant_workspace_call_event($binding,[
        'call_id'=>$callId,'sequence'=>6,'phase'=>'agent_report','duration_seconds'=>0,
        'summary'=>'سأل عن الأسعار وتوافر المنتج',
        'customer_notes'=>'يريد معرفة موعد التسليم']);
    finding_ok($draft['accepted']===true,'LTE model call note accepted');
    $preliminary=tenant_call_report_find($binding,$callId);
    finding_ok(str_contains($preliminary['summary'],'ملخص أولي غير مؤكد') &&
        str_contains($preliminary['customer_notes'],'موعد التسليم'),
        'non-transcribed LTE conversation still has preliminary notes');
    $requested=tenant_workspace_call_event($binding,[
        'call_id'=>$callId,'sequence'=>7,'phase'=>'customer_human_request',
        'duration_seconds'=>0,'handoff_reason'=>'طلب موظف مبيعات للمتابعة',
        'customer_notes'=>'يحتاج مكالمة متابعة']);
    finding_ok($requested['accepted']===true,'real caller handoff request recorded');
    $handoff=tenant_call_report_find($binding,$callId);
    finding_ok($handoff['handoff_status']==='requested' &&
        $handoff['handoff_reason']==='طلب موظف مبيعات للمتابعة',
        'staff request reason persisted without fake live transfer');
    finding_ok(tenant_workspace_call_event($binding,[
        'call_id'=>$callId,'sequence'=>7,'phase'=>'customer_human_request',
        'duration_seconds'=>0,'handoff_reason'=>'طلب موظف مبيعات للمتابعة'])['duplicate']===true,
        'LTE retry cannot duplicate staff request');
    // Verify the call remains attached to the authorized tenant and SIM.
    $scoped=one('SELECT id FROM calls WHERE account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=? AND external_call_id=?',[
        4,1,$device,0,$callId]);
    finding_ok($scoped!==null,'call remains scoped to tenant and paired SIM');
    $result=tenant_workspace_call_report($binding,[
        'call_id'=>$callId,'summary'=>'سأل عن المنتج والتسليم.',
        'customer_notes'=>'يُفضّل التواصل مساءً.','outcome'=>'interested',
        'handoff_status'=>'requested','handoff_reason'=>'طلب مسؤول المبيعات']);
    finding_ok($result['saved']===true&&!$result['duplicate'],'device report saved');
    $same=tenant_workspace_call_report($binding,[
        'call_id'=>$callId,'summary'=>'سأل عن المنتج والتسليم.',
        'customer_notes'=>'يُفضّل التواصل مساءً.','outcome'=>'interested',
        'handoff_status'=>'requested','handoff_reason'=>'طلب مسؤول المبيعات']);
    finding_ok($same['duplicate']===true,'report replay safe');
    $calls=tenant_workspace_calls($binding);
    $match=array_values(array_filter($calls['calls'],fn($item)=>$item['call_id']===$callId));
    finding_ok(count($match)===1,'only one real call');
    finding_ok($match[0]['phone']==='+201011122233'&&
        $match[0]['name']==='أحمد الاختبار','real verified number and contact name');
    finding_ok($match[0]['summary']==='سأل عن المنتج والتسليم.'&&
        $match[0]['customer_notes']==='يُفضّل التواصل مساءً.',
        'report visible on SIM endpoint');
    finding_ok($match[0]['handoff_status']==='requested',
        'employee handoff request not misrepresented as completed');
    $reject=false;
    try{tenant_workspace_call_report($binding,[
        'call_id'=>$callId,'summary'=>'تم التحويل','handoff_status'=>'completed',
        'handoff_reason'=>'كذبة']);}catch(InvalidArgumentException){$reject=true;}
    finding_ok($reject,'device cannot mark human handoff completed');
    $other=['id'=>9999,'account_id'=>5,'site_id'=>3,'sim_slot'=>1];
    $reject=false;try{tenant_workspace_call_report($other,[
        'call_id'=>$callId,'summary'=>'غير مصرح']);}catch(InvalidArgumentException){$reject=true;}
    finding_ok($reject,'foreign company blocked');
    // A selected status is not proof that a staff member answered.
    $reject=false;
    try{tenant_call_report_save($binding,[
        'call_id'=>$callId,'summary'=>'إقرار مزيف',
        'handoff_status'=>'completed',
        'handoff_reason'=>'لا يوجد تأكيد بشري'],'manual');
    }catch(InvalidArgumentException $e){
        $reject=$e->getMessage()==='human_handoff_explicit_staff_confirmation_required';
    }
    finding_ok($reject,'manual selection alone cannot claim staff accepted handoff');
    $summary=tenant_call_report_save($binding,[
        'call_id'=>$callId,'summary'=>'طلب المنتج والتسليم',
        'customer_notes'=>'يتابع معه أحمد','outcome'=>'follow_up',
        'handoff_status'=>'completed','handoff_reason'=>'أكد الموظف الاستلام عبر لوحة الإدارة',
        'staff_acknowledged'=>true
    ],'manual');
    finding_ok($summary['report']['handoff_status']==='completed',
        'human confirmation recorded explicitly');
    $fresh='f00fbabc-52b2-4de9-95e8-a37beaf14391';
    tenant_workspace_call_event($binding,[
        'call_id'=>$fresh,'sequence'=>1,'phase'=>'ringing','duration_seconds'=>0]);
    $reject=false;
    try{tenant_call_report_save($binding,[
        'call_id'=>$fresh,'summary'=>'لم يُطلب تدخل',
        'handoff_status'=>'completed','handoff_reason'=>'تم بنقرة',
        'staff_acknowledged'=>true],'manual');}
    catch(InvalidArgumentException $e){
        $reject=$e->getMessage()==='human_handoff_explicit_staff_confirmation_required';
    }
    finding_ok($reject,'cannot invent staff confirmation without a prior request');
    $reject=false;try{tenant_workspace_call_report($binding,[
        'call_id'=>$callId,'summary'=>'حذف التحويل']);}catch(InvalidArgumentException){$reject=true;}
    finding_ok($reject,'device cannot override human confirmation');
    $_SESSION=['uid'=>$actor,'account_id'=>4];
    $_GET=['tab'=>'calls','call'=>(string)one('SELECT id FROM calls WHERE external_call_id=?',[$callId])['id']];
    ob_start();render_calls(['account_id'=>4,'kind'=>'customer','role'=>'owner']);$html=(string)ob_get_clean();
    finding_ok(str_contains($html,'أحمد الاختبار')&&str_contains($html,'+201011122233'),
        'real name and phone visible on website');
    finding_ok(str_contains($html,'ملاحظات العميل')&&str_contains($html,'call_record_id')&&
        str_contains($html,'تم تأكيد استلام الموظف يدويًا'),'summary/notes/handoff on call details UI');
    echo "SIM_AGENT_SWITCH_AND_CALL_REPORTS_PASS\n";
}finally{
    if($device){
        $usageRef='voice-'.hash('sha256','4:1:'.$device.':0:'.$callId);
        execute('DELETE FROM usage_ledger WHERE reference=?',[$usageRef]);
        execute('DELETE FROM voice_call_answer_evidence WHERE device_binding_id=?',[$device]);
    }
    execute('DELETE FROM tenant_call_reports WHERE device_binding_id=?',[$device??-1]);
    execute('DELETE FROM tenant_call_events WHERE device_binding_id=?',[$device??-1]);
    execute('DELETE FROM calls WHERE device_binding_id=?',[$device??-1]);
    if($device){
        execute('DELETE FROM device_agent_revisions WHERE device_id=?',[$device]);
        execute('DELETE FROM device_bindings WHERE id=?',[$device]);
    }
    if($addedAgent){
        execute('DELETE FROM agent_flow_versions WHERE agent_id=?',[$addedAgent]);
        execute('DELETE FROM agents WHERE id=?',[$addedAgent]);
    }
}
