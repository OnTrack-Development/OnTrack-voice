<?php
declare(strict_types=1);
require __DIR__.'/../saas/console/database/Seed.php';
require __DIR__.'/../saas/console/_core/TenantWorkspace.php';
function text_ok(bool $x,string $msg):void{if(!$x)throw new RuntimeException('VOICE_TRANSCRIPT_ARCHIVE_FAIL '.$msg);}
$ours=['id'=>971431,'account_id'=>4,'site_id'=>1,'sim_slot'=>0];
$other=['id'=>971432,'account_id'=>5,'site_id'=>3,'sim_slot'=>1];
$id='32ff1c20-51b2-4fb3-a02f-f82fe21e19cc';
$actual="المتصل: محتاج متابعة مكالمة جديدة يوم الخميس.\nالإيجنت: حاضر؛ سأطلب من الموظف التواصل بعد الموافقة.";
seed_console();
// Cleanup an old fixture report from an interrupted Windows runner attempt.
// A stale summary otherwise short-circuits the forced outage test.
execute('DELETE FROM tenant_call_reports WHERE device_binding_id=? AND call_id=?',[$ours['id'],$id]);
try{
    tenant_workspace_call_event($ours,['call_id'=>$id,'sequence'=>1,'phase'=>'ringing','duration_seconds'=>0]);
    $fail=false;
    try{
        tenant_workspace_call_analysis($ours,['call_id'=>$id,'transcript'=>$actual],
            static function():array{throw new RuntimeException('forced_google_outage');});
    }catch(RuntimeException $e){$fail=$e->getMessage()==='forced_google_outage';}
    text_ok($fail,'test outage delivered to caller');
    $stored=tenant_voice_transcript_find($ours,$id);
    text_ok(($stored['transcript']??null)===$actual,'real transcript persisted even when AI unavailable');
    text_ok(($stored['source']??null)==='gemini_live_device','transcript source distinguished');
    text_ok(tenant_voice_transcript_store($ours,$id,$actual)['duplicate'],'retry without duplicate');
    $listing=tenant_workspace_calls($ours)['calls'];
    $match=array_values(array_filter($listing,static fn(array $c):bool=>$c['call_id']===$id));
    text_ok(count($match)===1 && $match[0]['has_transcript']===true,'call list exposes transcript availability');
    $read=tenant_workspace_call_transcript($ours,['call_id'=>$id]);
    text_ok($read['transcript']===$actual,'authorized SIM fetch of full transcript');
    $denied=false;
    try{tenant_workspace_call_transcript($other,['call_id'=>$id]);}
    catch(InvalidArgumentException $e){$denied=$e->getMessage()==='call_not_found_for_sim';}
    text_ok($denied,'other tenant denied');
    $denied=false;
    try{tenant_voice_transcript_find($other,$id);}catch(Throwable $e){$denied=true;}
    text_ok($denied||tenant_voice_transcript_find($other,$id)===null,'other tenant cannot read transcript');
    $wrongSlot=$ours;$wrongSlot['sim_slot']=1;
    text_ok(tenant_voice_transcript_find($wrongSlot,$id)===null,'other physical slot cannot read transcript');
    $invalid=false;
    try{tenant_voice_transcript_store($ours,$id,'hello');}
    catch(InvalidArgumentException $e){$invalid=$e->getMessage()==='invalid_call_transcript';}
    text_ok($invalid,'never accept invented short data');
    echo "TENANT_VOICE_TRANSCRIPT_ARCHIVE_PASS\n";
}finally{
    execute('DELETE FROM tenant_call_reports WHERE device_binding_id=? AND call_id=?',[$ours['id'],$id]);
    execute('DELETE FROM tenant_call_transcripts WHERE device_binding_id=?',[$ours['id']]);
    execute('DELETE FROM tenant_call_events WHERE device_binding_id=?',[$ours['id']]);
    execute('DELETE FROM calls WHERE device_binding_id=?',[$ours['id']]);
}
