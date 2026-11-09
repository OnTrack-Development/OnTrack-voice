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
    $stored=one('SELECT account_id,site_id,sim_slot,speaker,event_type,content,occurred_at
      FROM voice_call_timeline WHERE device_binding_id=? AND call_id=? AND event_id=?',
      [$ours['id'],$call,$entry['event_id']]);
    timeline_check($stored!==null,'event available for concurrent collision check');
    voice_call_timeline_assert_same_event($stored,[4,1,$ours['id'],0,$call],
      'caller','transcript',trim($entry['text']),$entry['occurred_at']);
    $changed=false;
    try { voice_call_timeline_assert_same_event($stored,[4,1,$ours['id'],0,$call],
      'caller','transcript','tampered',$entry['occurred_at']); }
    catch(RuntimeException $e){$changed=$e->getMessage()==='timeline_replay_changed';}
    timeline_check($changed,'concurrent event ID must not overwrite a different transcript');
    $denied=false;
    try{voice_call_timeline_store($other,$entry);}catch(InvalidArgumentException){$denied=true;}
    timeline_check($denied,'cannot fake foreign SIM');
    $otherSlot=$ours;$otherSlot['sim_slot']=1;
    $denied=false;try{voice_call_timeline_store($otherSlot,$entry);}catch(InvalidArgumentException){$denied=true;}
    timeline_check($denied,'cannot fake other SIM slot');
    $corrupt=$entry;$corrupt['text']='Modified transcript';
    $denied=false;try{voice_call_timeline_store($ours,$corrupt);}catch(RuntimeException){$denied=true;}
    timeline_check($denied,'append-only idempotency');
    foreach([
        ['speaker'=>'agent'],
        ['event_type'=>'notice'],
        ['occurred_at'=>gmdate('Y-m-d H:i:s',time()-10)]
    ] as $mutation){
        $forged=array_replace($entry,$mutation);
        $denied=false;
        try{voice_call_timeline_store($ours,$forged);}
        catch(RuntimeException $error){$denied=$error->getMessage()==='timeline_replay_changed';}
        timeline_check($denied,'event_id replay cannot modify speaker, event type or timestamp');
    }
    $forged=$entry;$forged['occurred_at']='invalid date';
    $denied=false;
    try{voice_call_timeline_store($ours,$forged);}
    catch(InvalidArgumentException){$denied=true;}
    timeline_check($denied,'duplicate event cannot evade timestamp format validation');
    $impossible=$entry;$impossible['event_id']='utterance-impossible';
    $impossible['occurred_at']=gmdate('Y-m-d').' 24:00:00';
    $denied=false;try{voice_call_timeline_store($ours,$impossible);}catch(InvalidArgumentException){$denied=true;}
    timeline_check($denied,'24:00 rollover timestamp is not valid evidence');

    require_once __DIR__.'/../saas/console/_core/BillingUsage.php';
    timeline_check(!voice_usage_update($ours,$call,'active'),'ringing call not billed');
    timeline_check(!voice_usage_update($ours,$call,'active'),'duplicate active callback idempotent');
    $tampered=$ours;$tampered['account_id']=5;
    $denied=false;try{voice_usage_update($tampered,$call,'active');}
    catch(RuntimeException $ex){$denied=$ex->getMessage()==='voice_usage_evidence_scope_mismatch';}
    timeline_check($denied,'answered evidence cannot be reused across tenants');
    $bad=$entry;$bad['event_id']='utterance-000002';$bad['event_type']='spoken_text';$bad['speaker']='caller';
    $denied=false;try{voice_call_timeline_store($ours,$bad);}catch(InvalidArgumentException){$denied=true;}
    timeline_check($denied,'caller cannot be claimed as agent spoken text');
    $agentEvent=$entry;
    $agentEvent['event_id']='utterance-agent-0001';
    $agentEvent['speaker']='agent';
    $agentEvent['event_type']='spoken_text';
    $agentEvent['text']='هل الميعاد الصباحي مناسب لحضرتك؟';
    voice_call_timeline_store($ours,$agentEvent);
    $followup=$entry;
    $followup['event_id']='utterance-000003';
    $followup['text']='متاح يوم الاتنين؟';
    $followup['occurred_at']=gmdate('Y-m-d H:i:s',time()+1);
    voice_call_timeline_store($ours,$followup);
    $second=$followup;$second['event_id']='utterance-000004';
    $second['text']='أفضل بعد الضهر';
    voice_call_timeline_store($ours,$second);
    $evidence=voice_call_evidence(4,1,$ours['id'],0,$call);
    timeline_check(count($evidence['transcript_events'])===4,'all four immutable utterances preserved');
    timeline_check(count($evidence['grouped_transcript'])===3&&
        $evidence['grouped_transcript'][2]['event_count']===2,
        'adjacent caller fragments grouped for display, agent not merged');
    timeline_check($evidence['grouped_transcript'][2]['event_ids']===
        ['utterance-000003','utterance-000004'],
        'grouped transcript keeps source event IDs for audit');
    timeline_check($evidence['transcript_events'][0]['origin']==='device_reported','honest provenance');
    timeline_check($evidence['verified_references']===[],
        'call without verified external requests has no fabricated API references');
    $denied=false;try{voice_call_evidence(5,1,$ours['id'],0,$call);}catch(InvalidArgumentException){$denied=true;}
    timeline_check($denied,'history cross-tenant filter');
    echo "VOICE_CALL_TIMELINE_EVIDENCE_PASS\n";
}finally{
    execute('DELETE FROM voice_call_answer_evidence WHERE device_binding_id=?',[$ours['id']]);
    execute('DELETE FROM voice_call_timeline WHERE device_binding_id=?',[$ours['id']]);
    execute('DELETE FROM tenant_call_events WHERE device_binding_id=?',[$ours['id']]);
    execute('DELETE FROM calls WHERE device_binding_id=?',[$ours['id']]);
}
