<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/call_ledger.php';
mobile_require_device();
if($_SERVER['REQUEST_METHOD']!=='GET')mobile_json(['ok'=>false,'error'=>'GET_only'],405);
$items=ontrack_call_list(MOBILE_STORE.'/calls',40);
$rows=[];
foreach($items as $row){
    $rows[]=[
        'id'=>(string)($row['call_id']??''),
        'started'=>(string)($row['started_at_utc']??''),
        'status'=>(string)($row['phase']??'unknown'),
        'duration'=>isset($row['duration_seconds']) && is_numeric($row['duration_seconds'])?(int)$row['duration_seconds']:null,
        'name'=>is_string($row['contact_name']??null)?substr($row['contact_name'],0,100):null,
        'phone'=>is_string($row['phone_number']??null)?substr($row['phone_number'],0,40):null,
    ];
}
mobile_json(['ok'=>true,'calls'=>$rows]);
