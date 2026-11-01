<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/pilot_workspace.php';
mobile_require_device();
if($_SERVER['REQUEST_METHOD']==='GET') {
    $rows=array_slice(array_reverse(pilot_read('contacts')),0,200);
    $suppressed=pilot_suppression_keys();
    $contacts=[];
    foreach($rows as $row) {
        $contacts[]=['id'=>(string)$row['id'],'name'=>(string)$row['name'],
            'phone'=>(string)$row['phone'],'do_not_call'=>!pilot_contact_dialable($row,$suppressed)];
    }
    mobile_json(['ok'=>true,'contacts'=>$contacts,'total'=>count(pilot_read('contacts'))]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')mobile_json(['ok'=>false,'error'=>'method_not_allowed'],405);
$raw=(string)file_get_contents('php://input');
if(strlen($raw)>1024)mobile_json(['ok'=>false,'error'=>'too_large'],413);
$data=json_decode($raw,true);
if(!is_array($data)||($data['confirmed']??false)!==true||
    !is_bool($data['blocked']??null)||
    !preg_match('/^[a-f0-9]{24}$/D',(string)($data['id']??'')))
    mobile_json(['ok'=>false,'error'=>'invalid_request'],422);
try {
    $found=pilot_dnc((string)$data['id'],$data['blocked']);
    if(!$found)mobile_json(['ok'=>false,'error'=>'not_found'],404);
    mobile_json(['ok'=>true,'updated'=>true]);
} catch(InvalidArgumentException $e) {
    mobile_json(['ok'=>false,'error'=>'verified_reconsent_required'],422);
} catch(Throwable $e) {
    mobile_json(['ok'=>false,'error'=>'storage_unavailable'],503);
}
