<?php
declare(strict_types=1);
require __DIR__.'/../saas/mobile/server/call_ledger.php';
require __DIR__.'/../saas/mobile/server/call_report_store.php';
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
    echo "Mobile call report tests PASS\n";
}finally{
    foreach(glob($base.'/reports/*')?:[] as $file)if(is_file($file))unlink($file);
    @unlink($base.'/reports/.reports.lock');@rmdir($base.'/reports');
    foreach(glob($base.'/*')?:[] as $file)if(is_file($file))unlink($file);
    @unlink($base.'/.ledger.lock');@rmdir($base);
}
