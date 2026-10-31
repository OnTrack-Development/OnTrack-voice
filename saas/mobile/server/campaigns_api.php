<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/pilot_workspace.php';
mobile_require_device();
if($_SERVER['REQUEST_METHOD']==='GET') {
    $campaigns=pilot_read('campaigns');
    mobile_json(['ok'=>true,'campaigns'=>array_slice(array_reverse($campaigns),0,60)]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')mobile_json(['ok'=>false,'error'=>'method_not_allowed'],405);
$raw=(string)file_get_contents('php://input');
if(strlen($raw)>12000)mobile_json(['ok'=>false,'error'=>'too_large'],413);
$data=json_decode($raw,true);
if(!is_array($data)||($data['confirmed']??false)!==true)mobile_json(['ok'=>false,'error'=>'confirmation_required'],422);
try {
    $action=(string)($data['action']??'create');
    if($action==='preview'){
        $id=(string)($data['id']??'');
        if(!preg_match('/^[a-f0-9]{24}$/D',$id))throw new InvalidArgumentException('id');
        mobile_json(['ok'=>true,'preview'=>pilot_campaign_preview($id)]);
    }
    if(in_array($action,['pause','resume','cancel'],true)){
        $id=(string)($data['id']??'');
        if(!preg_match('/^[a-f0-9]{24}$/D',$id))throw new InvalidArgumentException('id');
        mobile_json(['ok'=>true,'campaign'=>pilot_campaign_transition($id,$action),'dispatched'=>false]);
    }
    if($action!=='create')throw new InvalidArgumentException('action');
    $item=pilot_campaign_create((string)($data['name']??''),(string)($data['starts']??''),(string)($data['ends']??''),(string)($data['scenario']??''));
    mobile_json(['ok'=>true,'campaign'=>$item,'dispatched'=>false]);
} catch (InvalidArgumentException $e) {
    mobile_json(['ok'=>false,'error'=>'invalid_campaign'],422);
} catch (Throwable $e) {
    mobile_json(['ok'=>false,'error'=>'campaign_store_error'],500);
}
