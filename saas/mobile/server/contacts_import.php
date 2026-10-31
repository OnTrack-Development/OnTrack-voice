<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/pilot_workspace.php';
mobile_require_device();
if($_SERVER['REQUEST_METHOD']!=='POST')mobile_json(['ok'=>false,'error'=>'POST_only'],405);
$raw=(string)file_get_contents('php://input');
if($raw===''||strlen($raw)>350000)mobile_json(['ok'=>false,'error'=>'too_large'],413);
$data=json_decode($raw,true);
if(!is_array($data)||($data['confirmed']??false)!==true||!is_array($data['contacts']??null)){
    mobile_json(['ok'=>false,'error'=>'explicit_confirmation_required'],422);
}
if(count($data['contacts'])>2000)mobile_json(['ok'=>false,'error'=>'too_many_contacts'],422);
$rows=[];
foreach($data['contacts'] as $row){
    if(!is_array($row)||!is_string($row['name']??null)||!is_string($row['phone']??null)
        ||strlen($row['name'])>350||strlen($row['phone'])>60)
        mobile_json(['ok'=>false,'error'=>'invalid_contact_row'],422);
    $rows[]=['name'=>$row['name'],'phone'=>$row['phone']];
}
try{
    $count=pilot_import_contacts($rows);
    mobile_json(['ok'=>true,'counts'=>$count]);
}catch(Throwable $e){mobile_json(['ok'=>false,'error'=>'contact_import_failed'],500);}
