<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/TenantWorkspace.php';
function call_ai_ok(bool $result,string $msg):void {
    if(!$result)throw new RuntimeException('CALL_AI_REPORT_FAILED '.$msg);
}
seed_console();
$callId='9f33a301-8d44-4b22-9123-1240e5c08792';
$binding=['id'=>987654321,'account_id'=>4,'site_id'=>1,'sim_slot'=>0];
try {
    tenant_workspace_call_event($binding,[
        'call_id'=>$callId,'sequence'=>1,'phase'=>'active','duration_seconds'=>0]);
    tenant_workspace_call_event($binding,[
        'call_id'=>$callId,'sequence'=>2,'phase'=>'identified','duration_seconds'=>0,
        'phone_number'=>'+201001232323','contact_name'=>'عميل التجربة']);
    tenant_workspace_call_event($binding,[
        'call_id'=>$callId,'sequence'=>3,'phase'=>'human_takeover','duration_seconds'=>0]);
    $manual=tenant_call_report_find($binding,$callId);
    call_ai_ok($manual!==null && $manual['handoff_status']==='requested' &&
        str_contains($manual['handoff_reason'],'زر الهاتف'),
        'device takeover recorded with factual note, without external transfer claim');
    $transcript="المتصل: مساء الخير. مهتم بالاشتراك في الباقة الجديدة. ".
        "الإيجنت: أقدر أساعدك في اختيار الباقة. ".
        "المتصل: ممكن موظف يتواصل معايا بكرة؟";
    $called=0;
    $transport=static function(array $setup,array $turn)use(&$called):array{
        ++$called;
        call_ai_ok(($setup['setup']['tools'][0]['functionDeclarations'][0]['name']??'')==='submit_call_report',
            'report tool not declared');
        call_ai_ok(($turn['clientContent']['turnComplete']??false)===true,
            'text is submitted as completed Live turn');
        call_ai_ok(str_contains((string)($turn['clientContent']['turns'][0]['parts'][0]['text']??''),'المتصل:'),
            'real transcript is passed to Live instead of synthetic summary');
        return ['toolCall'=>['functionCalls'=>[[
            'name'=>'submit_call_report',
            'args'=>['summary'=>'سأل عن الباقة الجديدة وطلب متابعة من موظف.',
                'customer_notes'=>'يفضل الاتصال غدًا.',
                'outcome'=>'follow_up','handoff_requested'=>true,
                'handoff_reason'=>'طلب متابعة بشرية']
        ]]]];
    };
    $report=tenant_workspace_call_analysis($binding,[
        'call_id'=>$callId,'transcript'=>$transcript],$transport);
    call_ai_ok($report['saved']===true&&!$report['duplicate'],'real transcript analyzed');
    call_ai_ok($report['report']['handoff_status']==='requested','handoff requested, never completed by AI');
    call_ai_ok(str_contains($report['report']['handoff_reason'],'زر الهاتف'),
        'model summary preserved previously recorded manual takeover');
    $archived=tenant_voice_transcript_find($binding,$callId);
    call_ai_ok($archived!==null && $archived['transcript']===$transcript &&
        $archived['source']==='gemini_live_device','actual Live text archived after analysis');
    call_ai_ok($report['report']['customer_notes']==='يفضل الاتصال غدًا.','notes saved');
    call_ai_ok($called===1,'single Live invocation');
    $second=tenant_workspace_call_analysis($binding,[
        'call_id'=>$callId,'transcript'=>$transcript],$transport);
    call_ai_ok($second['duplicate']===true&&$called===1,'duplicate avoids extra model cost');
    $stored=one('SELECT transcript FROM tenant_call_transcripts WHERE device_binding_id=? AND call_id=?',
        [$binding['id'],$callId]);
    call_ai_ok(is_array($stored)&&str_starts_with((string)$stored['transcript'],'enc:v1:') &&
        !str_contains((string)$stored['transcript'],$transcript),
        'real transcript encrypted at rest on tenant server');
    $visible=tenant_voice_transcript_find($binding,$callId);
    call_ai_ok(($visible['transcript']??'')===$transcript,'authorized owner receives exact true transcript');
    $foreignTranscript=tenant_voice_transcript_find(['id'=>$binding['id'],'account_id'=>5,
        'site_id'=>3,'sim_slot'=>1],$callId);
    call_ai_ok($foreignTranscript===null,'other account cannot retrieve protected transcript');
    execute('UPDATE tenant_call_transcripts SET transcript=? WHERE device_binding_id=? AND call_id=?',
        [$transcript,$binding['id'],$callId]);
    $migrated=tenant_voice_transcript_find($binding,$callId);
    call_ai_ok(($migrated['transcript']??'')===$transcript &&
        str_starts_with((string)one('SELECT transcript FROM tenant_call_transcripts WHERE device_binding_id=? AND call_id=?',
            [$binding['id'],$callId])['transcript'],'enc:v1:'),
        'legacy plaintext transcript encrypted in-place on scoped read');
    $record=one('SELECT * FROM tenant_call_reports WHERE device_binding_id=? AND call_id=?',[
        $binding['id'],$callId]);
    call_ai_ok(!str_contains(json_encode($record,JSON_UNESCAPED_UNICODE),$transcript),
        'raw transcript never stored in call report');
    $denied=false;
    try{tenant_workspace_call_analysis(['id'=>987654321,'account_id'=>5,
        'site_id'=>3,'sim_slot'=>1],['call_id'=>$callId,'transcript'=>$transcript],$transport);}
    catch(InvalidArgumentException){$denied=true;}
    call_ai_ok($denied,'cross tenant report analysis denied');
    $denied=false;
    try{tenant_workspace_call_analysis($binding,['call_id'=>$callId,'transcript'=>'صمت'],$transport);}
    catch(InvalidArgumentException){$denied=true;}
    call_ai_ok($denied,'empty transcript not summarized');
    // A failed Live summarizer must not erase a verified transcript or pretend
    // that a human has answered. Keep a visibly labelled factual excerpt.
    $fallbackId='ae83b221-92d7-4ef1-a456-94c10302ab17';
    try {
        tenant_workspace_call_event($binding,[
            'call_id'=>$fallbackId,'sequence'=>1,'phase'=>'active','duration_seconds'=>0]);
        tenant_workspace_call_event($binding,[
            'call_id'=>$fallbackId,'sequence'=>2,'phase'=>'ended','duration_seconds'=>42]);
        $failedSummary=tenant_workspace_call_analysis($binding,[
            'call_id'=>$fallbackId,
            'transcript'=>'المتصل: أريد معرفة تفاصيل الاشتراك الجديد. الإيجنت: سأوضح المزايا الآن.'
        ],static function():never {throw new RuntimeException('provider_503');});
        call_ai_ok($failedSummary['saved']===true &&
            str_contains($failedSummary['report']['summary'],'تعذر التلخيص الآلي') &&
            str_contains($failedSummary['report']['summary'],'تفريغ المكالمة') &&
            $failedSummary['report']['outcome']==='unknown' &&
            $failedSummary['report']['handoff_status']==='not_requested',
            'failed Gemini analysis keeps labelled factual excerpt without fake handoff');
        $savedRaw=tenant_voice_transcript_find($binding,$fallbackId);
        call_ai_ok($savedRaw!==null &&
            str_contains($savedRaw['transcript'],'الاشتراك الجديد'),
            'failed summary still archives true transcript securely');
    }finally {
        execute('DELETE FROM tenant_call_transcripts WHERE device_binding_id=? AND call_id=?',[$binding['id'],$fallbackId]);
        execute('DELETE FROM tenant_call_reports WHERE device_binding_id=? AND call_id=?',[$binding['id'],$fallbackId]);
        execute('DELETE FROM tenant_call_events WHERE device_binding_id=? AND call_id=?',[$binding['id'],$fallbackId]);
        execute('DELETE FROM calls WHERE device_binding_id=? AND external_call_id=?',[$binding['id'],$fallbackId]);
    }
    echo "GEMINI_LIVE_REAL_CALL_SUMMARY_MOCK_PASS\n";
}finally{
    execute('DELETE FROM tenant_call_transcripts WHERE device_binding_id=? AND call_id=?',[$binding['id'],$callId]);
    execute('DELETE FROM tenant_call_reports WHERE device_binding_id=? AND call_id=?',[$binding['id'],$callId]);
    execute('DELETE FROM tenant_call_events WHERE device_binding_id=? AND call_id=?',[$binding['id'],$callId]);
    execute('DELETE FROM calls WHERE device_binding_id=? AND external_call_id=?',[$binding['id'],$callId]);
}
