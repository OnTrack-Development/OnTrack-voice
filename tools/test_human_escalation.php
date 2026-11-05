<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/DevicePairing.php';
require_once __DIR__.'/../saas/console/_core/TenantWorkspace.php';
require_once __DIR__.'/../saas/console/_core/CallHistory.php';
function he_ok(bool $x,string $message):void{
    if(!$x)throw new RuntimeException('HUMAN_ESCALATION_FAIL '.$message);
    echo 'PASS '.$message.PHP_EOL;
}
seed_console();
he_init();
$account=4;$site=1;$owner=(int)one("SELECT user_id FROM memberships WHERE account_id=? AND role='owner'",[$account])['user_id'];
$pair=device_pairing_create($account,$site,1,$owner,0);
$dev=device_pairing_redeem($pair,'human-escalation-test-001',0,'Human escalation fixture');
$b=device_binding_authorize($dev['device_token']);
he_ok($b!==null,'bound real test SIM');
$call='c8e37392-90d0-44e4-97ae-791ed5e9cd7a';
$staff=0;
try{
    $bad=false;try{he_contact_add($account,'مدير الشركة','01100000000','manager');}
    catch(InvalidArgumentException){$bad=true;}
    he_ok($bad,'local unverified phone rejected; E164 only');
    $staff=he_contact_add($account,'مدير التجربة','+201001234567','manager');
    $same=he_contact_add($account,'مدير التجربة','+201001234567','manager');
    he_ok($staff===$same,'contact number unique within company and update idempotent');
    $bad=false;try{he_route_configure(5,1,$staff,true);}
    catch(InvalidArgumentException){$bad=true;}
    he_ok($bad,'cannot configure another company agent');
    he_route_configure($account,1,$staff,false);
    tenant_workspace_call_event($b,['call_id'=>$call,'sequence'=>1,'phase'=>'ringing','duration_seconds'=>0]);
    tenant_workspace_call_event($b,['call_id'=>$call,'sequence'=>2,'phase'=>'active','duration_seconds'=>0]);
    tenant_workspace_call_event($b,['call_id'=>$call,'sequence'=>3,'phase'=>'identified',
        'phone_number'=>'+201011123456','contact_name'=>'عميل مهتم']);
    $report=tenant_call_report_save($b,['call_id'=>$call,
        'summary'=>'العميل طلب متابعة اشتراك وموظف بشري لمراجعة تفاصيل الباقة.',
        'customer_notes'=>'مطلوب الاتصال بعد الظهر','outcome'=>'follow_up',
        'handoff_status'=>'requested','handoff_reason'=>'طلب موظف مبيعات لمتابعة الاشتراك'],'device');
    he_ok($report['saved']&&$report['manager_notification']['reason']==='call_still_open',
        'request and reason stored before hang-up, outbound never starts early');
    tenant_workspace_call_event($b,['call_id'=>$call,'sequence'=>4,'phase'=>'ended','duration_seconds'=>34]);
    $st=one('SELECT agent_id,ended_at,status FROM calls WHERE external_call_id=?',[$call]);
    he_ok((int)$st['agent_id']===1 && $st['ended_at']!==null && $st['status']==='ended',
        'call has immutable assigned agent and terminal time');
    $absent=one('SELECT id FROM human_escalation_jobs WHERE device_binding_id=? AND call_id=?',[$b['id'],$call]);
    he_ok($absent===null,'default off means no manager auto-call');
    he_route_configure($account,1,$staff,true);
    $pending=he_schedule($b,$call);
    he_ok($pending['queued'] && !$pending['duplicate'],'explicit owner route enables queue after hangup');
    $sameJob=he_schedule($b,$call);
    he_ok($sameJob['duplicate'],'replayed call report cannot queue duplicate manager calls');
    $job=one('SELECT * FROM human_escalation_jobs WHERE device_binding_id=? AND call_id=?',
        [$b['id'],$call]);
    he_ok((int)$job['due_epoch']>=(int)strtotime($st['ended_at'].' UTC')+10,
        'manager notification due 10 seconds AFTER terminal call');
    $foreign=['id'=>$b['id'],'account_id'=>5,'site_id'=>3,'sim_slot'=>1];
    he_ok(he_claim($foreign)['job']===null,'another tenant/SIM cannot claim manager notification');
    he_ok(he_claim($b)['job']===null,'job is not claimable before ten seconds');
    execute('UPDATE human_escalation_jobs SET due_epoch=? WHERE id=?',[time()-1,$job['id']]);
    $claim=he_claim($b)['job'];
    he_ok($claim!==null && $claim['phone']==='+201001234567' &&
        $claim['name']==='مدير التجربة' &&
        str_contains($claim['reason'],'موظف مبيعات') &&
        str_contains($claim['summary'],'الاشتراك'),
        'device receives exact owner-approved staff number, real summary and reason');
    he_ok(he_claim($b)['job']===null,'one-shot lease prevents duplicate automatic dial');
    $bad=false;try{he_ack($foreign,(int)$claim['id'],'dial_started');}
    catch(InvalidArgumentException){$bad=true;}
    he_ok($bad,'foreign device cannot claim dialing success');
    $ack=he_ack($b,(int)$claim['id'],'dial_started');
    he_ok($ack['saved']&&$ack['status']==='dial_started','start call is distinguished from answering');
    $end=he_ack($b,(int)$claim['id'],'call_ended_unverified');
    he_ok($end['status']==='call_ended_unverified','call completion never proves manager heard briefing');
    $filters=call_history_filters($account,['q'=>'c8e37392']);
    $rows=call_history_rows($filters);
    $mine=array_values(array_filter($rows,fn($row)=>$row['external_call_id']===$call));
    he_ok(count($mine)===1 && $mine[0]['summary']!=='' &&
        $mine[0]['handoff_status']==='requested' &&
        $mine[0]['manager_notification_status']==='call_ended_unverified',
        'customer name, call summary, handoff and callback status visible in history');
    $second='c8e37392-90d0-44e4-97ae-791ed5e9cd7b';
    tenant_workspace_call_event($b,['call_id'=>$second,'sequence'=>1,'phase'=>'ringing']);
    tenant_call_report_save($b,['call_id'=>$second,'summary'=>'طلب الموظف',
        'handoff_status'=>'requested','handoff_reason'=>'سؤال فني'],'manual');
    tenant_workspace_call_event($b,['call_id'=>$second,'sequence'=>2,'phase'=>'ended']);
    he_ok(one('SELECT status FROM human_escalation_jobs WHERE device_binding_id=? AND call_id=?',
        [$b['id'],$second])['status']==='pending','second customer request scheduled');
    he_contact_disable($account,$staff);
    he_ok(one('SELECT status FROM human_escalation_jobs WHERE device_binding_id=? AND call_id=?',
        [$b['id'],$second])['status']==='cancelled','disabling number cancels unsent callback');
    he_ok(he_claim($b)['job']===null,'disabled recipient never receives callback');
    echo "HUMAN_ESCALATION_END_TO_END_PASS\n";
}finally{
    $calls=[$call,'c8e37392-90d0-44e4-97ae-791ed5e9cd7b'];
    foreach($calls as $cid){
        execute('DELETE FROM human_escalation_jobs WHERE device_binding_id=? AND call_id=?',[$b['id'],$cid]);
        execute('DELETE FROM tenant_call_reports WHERE device_binding_id=? AND call_id=?',[$b['id'],$cid]);
        execute('DELETE FROM tenant_call_events WHERE device_binding_id=? AND call_id=?',[$b['id'],$cid]);
        execute('DELETE FROM calls WHERE device_binding_id=? AND external_call_id=?',[$b['id'],$cid]);
    }
    execute('DELETE FROM human_escalation_routes WHERE account_id=? AND agent_id=?',[$account,1]);
    if($staff)execute('DELETE FROM human_escalation_contacts WHERE id=? AND account_id=?',[$staff,$account]);
    execute('DELETE FROM device_agent_revisions WHERE device_id=?',[$b['id']]);
    execute('DELETE FROM device_bindings WHERE id=?',[$b['id']]);
}
