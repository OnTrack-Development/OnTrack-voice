<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/call_ledger.php';
require __DIR__.'/call_report_store.php';
mobile_require_device();
if($_SERVER['REQUEST_METHOD']!=='GET')mobile_json(['ok'=>false,'error'=>'GET_only'],405);
$items=ontrack_call_list(MOBILE_STORE.'/calls',40);
$rows=[];
foreach($items as $row){
    $report=ontrack_report_read((string)$row['call_id'],MOBILE_STORE.'/calls');
    $publicReport=['transcript_count'=>0,'summary'=>null,'summary_source'=>null,'handoff'=>null,'note'=>null,'outcome'=>null];
    foreach($report['entries'] as $entry){
        $kind=$entry['kind']??'';
        if($kind==='transcript'){$publicReport['transcript_count']++;continue;}
        if($kind==='summary'){
            if(($entry['source']??'')==='ai' || $publicReport['summary']===null){
                $publicReport['summary']=mb_substr((string)$entry['text'],0,1200,'UTF-8');
                $publicReport['summary_source']=($entry['source']??'')==='ai'?'ai':'employee';
            }
        }
        if($kind==='handoff')$publicReport['handoff']=mb_substr((string)$entry['text'],0,350,'UTF-8');
        if($kind==='customer_note')$publicReport['note']=mb_substr((string)$entry['text'],0,350,'UTF-8');
        if($kind==='disposition')$publicReport['outcome']=mb_substr((string)$entry['text'],0,250,'UTF-8');
    }
    $rows[]=[
        'id'=>(string)($row['call_id']??''),
        'started'=>(string)($row['started_at_utc']??''),
        'status'=>(string)($row['phase']??'unknown'),
        'duration'=>isset($row['duration_seconds']) && is_numeric($row['duration_seconds'])?(int)$row['duration_seconds']:null,
        'name'=>is_string($row['contact_name']??null)?substr($row['contact_name'],0,100):null,
        'phone'=>is_string($row['phone_number']??null)?substr($row['phone_number'],0,40):null,
        'report'=>$publicReport,
    ];
}
mobile_json(['ok'=>true,'calls'=>$rows]);
