<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/TenantWorkspace.php';
require_once __DIR__.'/../saas/console/_core/VoiceToolGateway.php';
require_once __DIR__.'/../saas/console/_core/VoiceCallEvidence.php';
if(is_dir('/home/appontrack'))throw new RuntimeException('CI fixture only');
function timeline_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('TIMELINE_FAIL '.$label);}
seed_console();
$ours=['id'=>971743,'account_id'=>4,'site_id'=>1,'sim_slot'=>0];
$other=['id'=>971744,'account_id'=>5,'site_id'=>3,'sim_slot'=>1];
$call='71ff1c20-51b2-4fb3-a02f-f82fe21e19cc';
tenant_workspace_call_event($ours,['call_id'=>$call,'sequence'=>1,'phase'=>'ringing','duration_seconds'=>0]);
try{
    $entry=['call_id'=>$call,'event_id'=>'utterance-000001','speaker'=>'caller',
        'event_type'=>'transcript','text'=>'أحتاج أحجز ميعاد للغد، هل في مواعيد متاحة؟',
        'occurred_at'=>now()];
    $one=voice_call_timeline_store($ours,$entry);
    timeline_check($one['saved']===true&&!$one['duplicate'],'real device report stored');
    timeline_check(voice_call_timeline_store($ours,$entry)['duplicate']===true,'duplicate idempotent');
    $denied=false;
    try{voice_call_timeline_store($other,$entry);}catch(InvalidArgumentException){$denied=true;}
    timeline_check($denied,'cannot fake foreign SIM');
    $otherSlot=$ours;$otherSlot['sim_slot']=1;
    $denied=false;try{voice_call_timeline_store($otherSlot,$entry);}catch(InvalidArgumentException){$denied=true;}
    timeline_check($denied,'cannot fake other SIM slot');
    $corrupt=$entry;$corrupt['text']='Modified transcript';
    $denied=false;try{voice_call_timeline_store($ours,$corrupt);}catch(RuntimeException){$denied=true;}
    timeline_check($denied,'append-only idempotency');
    $bad=$entry;$bad['event_id']='utterance-000002';$bad['event_type']='spoken_text';$bad['speaker']='caller';
    $denied=false;try{voice_call_timeline_store($ours,$bad);}catch(InvalidArgumentException){$denied=true;}
    timeline_check($denied,'caller cannot be claimed as agent spoken text');
    $evidence=voice_call_evidence(4,1,$ours['id'],0,$call);
    timeline_check(count($evidence['transcript_events'])===1,'one real utterance listed');
    timeline_check($evidence['transcript_events'][0]['origin']==='device_reported','honest provenance');
    $denied=false;try{voice_call_evidence(5,1,$ours['id'],0,$call);}catch(InvalidArgumentException){$denied=true;}
    timeline_check($denied,'history cross-tenant filter');
    echo "VOICE_CALL_TIMELINE_EVIDENCE_PASS\n";
}finally{
    execute('DELETE FROM voice_call_timeline WHERE device_binding_id=?',[$ours['id']]);
    execute('DELETE FROM tenant_call_events WHERE device_binding_id=?',[$ours['id']]);
    execute('DELETE FROM calls WHERE device_binding_id=?',[$ours['id']]);
}
