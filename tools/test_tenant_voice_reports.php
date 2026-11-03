<?php
declare(strict_types=1);
require __DIR__.'/../saas/console/database/Seed.php';
require __DIR__.'/../saas/console/_core/TenantWorkspace.php';
function tvr(bool $ok,string $why):void{if(!$ok)throw new RuntimeException('VOICE_REPORT_TEST: '.$why);}
function tvr_reject(callable $call,string $reason):void{
    try{$call();}catch(InvalidArgumentException $e){
        tvr($e->getMessage()===$reason,'expected '.$reason.', got '.$e->getMessage());return;
    }
    throw new RuntimeException('VOICE_REPORT_TEST: expected rejection '.$reason);
}
seed_console();
$own=['id'=>990011,'account_id'=>4,'site_id'=>1,'sim_slot'=>0,'agent_id'=>1];
$foreign=['id'=>990012,'account_id'=>5,'site_id'=>3,'sim_slot'=>1,'agent_id'=>3];
$callId='12345678-1234-4234-9234-123456789012';
$empty='62345678-1234-4234-9234-123456789012';
$eventId='b4c54816-859c-48f1-8d0c-30a4ae5e3207';
try{
    tenant_workspace_call_event($own,['call_id'=>$callId,'sequence'=>1,'phase'=>'ringing','duration_seconds'=>0]);
    $event=['call_id'=>$callId,'entry_id'=>$eventId,'kind'=>'transcript',
        'role'=>'caller','text'=>'عايز موظف الدعم يكلمني بكرة'];
    tvr(tenant_report_append($own,$event)['accepted'],'genuine text recorded');
    tvr(tenant_report_append($own,$event)['duplicate'],'HTTP retry is deduped');
    $agent=$event;$agent['entry_id']='c4c54816-859c-48f1-8d0c-30a4ae5e3207';
    $agent['role']='agent';$agent['text']='حاضر، هسيب ملاحظة للمتابعة';
    tenant_report_append($own,$agent);
    tvr(count(tenant_report_entries($own,$callId))===2,'source-ordered voice turns');
    tvr_reject(fn()=>tenant_report_append($foreign,$event),'tenant_call_not_found');
    tvr_reject(fn()=>tenant_report_entries($foreign,$callId),'tenant_call_not_found');
    $bad=$event;$bad['entry_id']='d4c54816-859c-48f1-8d0c-30a4ae5e3207';$bad['kind']='summary';
    tvr_reject(fn()=>tenant_report_append($own,$bad,'device'),'invalid_report_kind');
    $bad=$event;$bad['entry_id']='d4c54816-859c-48f1-8d0c-30a4ae5e3207';$bad['role']='system';
    tvr_reject(fn()=>tenant_report_append($own,$bad,'device'),'invalid_transcript_role');
    tenant_workspace_call_event($own,['call_id'=>$callId,'sequence'=>2,'phase'=>'ended','duration_seconds'=>28]);
    $called=0;
    $result=tenant_report_summarize($own,$callId,static function(string $transcript)use(&$called):string{
        ++$called;
        tvr(str_contains($transcript,'العميل: عايز موظف'),'actual caller transcript in prompt');
        tvr(str_contains($transcript,'الإيجنت: حاضر'),'actual agent transcript in prompt');
        return 'طلب العميل متابعة الموظف غدًا. لا توجد معلومات مؤكدة عن الرضا.';
    });
    tvr($result['summary_status']==='generated','AI summary from actual text');
    tvr(tenant_report_summarize($own,$callId,static function()use(&$called):string{
        ++$called;return 'duplicate';
    })['duplicate'],'summary idempotent');
    tvr($called===1,'no repeated model costs');
    $entries=tenant_report_entries($own,$callId);
    tvr(count($entries)===3 && $entries[2]['source']==='ai','AI provenance preserved');
    $note=['call_id'=>$callId,'entry_id'=>tenant_report_new_uuid(),'kind'=>'customer_note',
        'role'=>'employee','text'=>'يجب التواصل مع العميل بعد موافقته'];
    tenant_report_append($own,$note,'employee');
    $history=tenant_workspace_calls($own);
    $matching=array_values(array_filter($history['calls'],fn(array $row)=>$row['call_id']===$callId));
    tvr(count($matching)===1,'one call in history');
    tvr($matching[0]['report']['transcript_count']===2,'mobile sees real transcript count');
    tvr($matching[0]['report']['summary_source']==='ai','mobile identifies AI source');
    tvr(str_contains((string)$matching[0]['report']['customer_note'],'العميل'),'mobile sees employee note');
    tenant_workspace_call_event($own,['call_id'=>$empty,'sequence'=>1,'phase'=>'missed','duration_seconds'=>0]);
    $noText=tenant_report_summarize($own,$empty,static function():string{
        throw new RuntimeException('summary model must not run on empty call');
    });
    tvr($noText['summary_status']==='no_transcript','no fabricated empty call summary');
    $end=['call_id'=>$callId,'entry_id'=>tenant_report_new_uuid(),'kind'=>'finalize',
        'role'=>'system','text'=>'end_of_call'];
    tvr(tenant_report_append($own,$end)['accepted'],'device end marker');
    tvr_reject(fn()=>tenant_report_append($own,$end,'employee'),'invalid_report_kind');
    tvr((int)one('SELECT COUNT(*) n FROM tenant_voice_report_entries WHERE account_id=? AND device_binding_id=?',
        [5,$own['id']])['n']===0,'no cross-account writes');
    echo "TENANT_VOICE_REPORTS_PASS\n";
}finally{
    execute('DELETE FROM tenant_voice_report_entries WHERE device_binding_id=?',[$own['id']]);
    execute('DELETE FROM tenant_call_events WHERE device_binding_id=?',[$own['id']]);
    execute('DELETE FROM calls WHERE device_binding_id=?',[$own['id']]);
}
