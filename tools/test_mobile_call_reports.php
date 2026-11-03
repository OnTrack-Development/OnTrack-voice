<?php
declare(strict_types=1);
require __DIR__.'/../saas/mobile/server/call_ledger.php';
require __DIR__.'/../saas/mobile/server/call_report_store.php';
require __DIR__.'/../saas/mobile/server/call_summary.php';
function report_expect(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
function report_reject(callable $check,string $expected):void{
    try{$check();}catch(InvalidArgumentException $e){
        report_expect($e->getMessage()===$expected,"Unexpected error: ".$e->getMessage());return;
    }
    throw new RuntimeException("Expected ".$expected);
}
$base=sys_get_temp_dir().'/ontrack-report-'.bin2hex(random_bytes(6));
$id='a4c54816-859c-48f1-8d0c-30a4ae5e3207';
$eventId='b4c54816-859c-48f1-8d0c-30a4ae5e3207';
$entry=['call_id'=>$id,'entry_id'=>$eventId,'kind'=>'transcript','role'=>'caller','text'=>'هل ممكن حد من الدعم يكمل معايا؟'];
try{
    report_reject(fn()=>ontrack_report_append($entry,$base,'device'),'call_not_found');
    ontrack_call_event(['call_id'=>$id,'sequence'=>1,'phase'=>'ringing'],$base);
    ontrack_call_event(['call_id'=>$id,'sequence'=>2,'phase'=>'active'],$base);
    $first=ontrack_report_append($entry,$base,'device');
    report_expect($first['ok']&&!$first['duplicate'],'first transcription');
    report_expect(ontrack_report_append($entry,$base,'device')['duplicate'],'network retry idempotence');
    $second=$entry;$second['entry_id']='c4c54816-859c-48f1-8d0c-30a4ae5e3207';
    $second['role']='agent';$second['text']='هحوّلك لزميلي';
    ontrack_report_append($second,$base,'device');
    ontrack_call_event(['call_id'=>$id,'sequence'=>3,'phase'=>'ended','duration_seconds'=>28],$base);
    $handoff=$entry;$handoff['entry_id']='d4c54816-859c-48f1-8d0c-30a4ae5e3207';
    $handoff['role']='system';$handoff['kind']='handoff';$handoff['text']='الموظف استلم المكالمة يدويًا';
    ontrack_report_append($handoff,$base,'device');
    $note=$entry;$note['entry_id']='e4c54816-859c-48f1-8d0c-30a4ae5e3207';
    $note['kind']='customer_note';$note['role']='employee';$note['text']='متابعة مطلوبة يوم الخميس';
    ontrack_report_append($note,$base,'admin');
    $report=ontrack_report_read($id,$base);
    report_expect(count($report['entries'])===4,'persisted four unique source-tagged entries');
    report_expect($report['entries'][0]['source']==='device'&&$report['entries'][3]['source']==='admin','source tagging');
    report_expect($report['entries'][0]['text']===$entry['text'],'Arabic speech preserved');
    report_reject(fn()=>ontrack_report_append($note,$base,'device'),'invalid_report_kind');
    $malicious=$entry;$malicious['entry_id']='f4c54816-859c-48f1-8d0c-30a4ae5e3207';$malicious['role']='system';
    report_reject(fn()=>ontrack_report_append($malicious,$base,'device'),'invalid_transcript_role');
    $malicious['call_id']='../../outside';
    report_reject(fn()=>ontrack_report_append($malicious,$base,'device'),'invalid_call_id');
    $malicious=$entry;$malicious['entry_id']='f4c54816-859c-48f1-8d0c-30a4ae5e3207';
    $malicious['text']=str_repeat('x',1501);
    report_reject(fn()=>ontrack_report_append($malicious,$base,'device'),'invalid_report_text');
    report_expect(!is_file($base.'/reports/../../outside.json'),'no path traversal');
    $modelCalls=0;
    $result=ontrack_generate_call_summary($id,$base,static function(string $transcript)use(&$modelCalls):string {
        ++$modelCalls;
        report_expect(str_contains($transcript,'العميل: هل ممكن حد'),'genuine caller text only');
        report_expect(str_contains($transcript,'الإيجنت: هحوّلك'),'genuine agent text');
        return 'الملخص: طلب العميل متابعة الدعم، وتم تحويل المكالمة يدويًا.';
    });
    report_expect($result['summary_status']==='generated','AI summary from real transcript');
    report_expect(ontrack_generate_call_summary($id,$base,static function()use(&$modelCalls):string {
        ++$modelCalls;return 'should never execute';
    })['duplicate'],'summary deduplication');
    report_expect($modelCalls===1,'summary model not called twice on retry');
    $entries=ontrack_report_read($id,$base)['entries'];
    report_expect(count($entries)===5&&$entries[4]['kind']==='summary'&&$entries[4]['source']==='ai','summary correctly attributed');
    $empty='c4c54816-859c-48f1-8d0c-30a4ae5e3207';
    ontrack_call_event(['call_id'=>$empty,'sequence'=>1,'phase'=>'missed'],$base);
    $withoutText=ontrack_generate_call_summary($empty,$base,static function():string{throw new RuntimeException('should not call AI');});
    report_expect($withoutText['summary_status']==='no_transcript','no fabricated summary for empty call');
    $final=$entry;$final['entry_id']='f4c54816-859c-48f1-8d0c-30a4ae5e3207';$final['kind']='finalize';$final['role']='system';$final['text']='end_of_call';
    report_expect(ontrack_report_append($final,$base,'device')['ok'],'device final marker');
    report_reject(fn()=>ontrack_report_append($final,$base,'admin'),'invalid_report_kind');
    echo "Mobile call report tests PASS\n";
}finally{
    foreach(glob($base.'/reports/*')?:[] as $file)if(is_file($file))unlink($file);
    @unlink($base.'/reports/.reports.lock');@rmdir($base.'/reports');
    foreach(glob($base.'/*')?:[] as $file)if(is_file($file))unlink($file);
    @unlink($base.'/.ledger.lock');@rmdir($base);
}
