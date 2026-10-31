<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/pilot_workspace.php';
mobile_require_device();
if($_SERVER['REQUEST_METHOD']==='GET') {
    $campaigns=pilot_read('campaigns');
    $attempts=[];
    foreach(array_slice(array_reverse(pilot_read('attempts')),0,50) as $row){
        $attempts[]=['id'=>(string)$row['id'],'campaign_id'=>(string)$row['campaign_id'],
            'name'=>(string)$row['name'],'phone'=>(string)$row['phone'],
            'status'=>(string)$row['status'],'created_at'=>(string)$row['created_at']];
    }
    mobile_json(['ok'=>true,'campaigns'=>array_slice(array_reverse($campaigns),0,60),'attempts'=>$attempts]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')mobile_json(['ok'=>false,'error'=>'method_not_allowed'],405);
$raw=(string)file_get_contents('php://input');
if(strlen($raw)>12000)mobile_json(['ok'=>false,'error'=>'too_large'],413);
$data=json_decode($raw,true);
if(!is_array($data)||($data['confirmed']??false)!==true)mobile_json(['ok'=>false,'error'=>'confirmation_required'],422);
try {
    $action=(string)($data['action']??'create');
    if($action==='claim'){
        mobile_json(['ok'=>true,'claim'=>pilot_campaign_claim(),'dial_requested'=>false]);
    }
    if($action==='verify'){
        $attempt=(string)($data['attempt_id']??'');
        if(!preg_match('/^[a-f0-9]{32}$/D',$attempt))throw new InvalidArgumentException('attempt_id');
        mobile_json(['ok'=>true,'verification'=>pilot_campaign_verify_claim($attempt)]);
    }
    if($action==='finish'){
        $attempt=(string)($data['attempt_id']??'');
        if(!preg_match('/^[a-f0-9]{32}$/D',$attempt))throw new InvalidArgumentException('attempt_id');
        mobile_json(['ok'=>true,'attempt'=>pilot_campaign_finish($attempt,(string)($data['result']??''))]);
    }
    if($action==='arm'){
        $id=(string)($data['id']??'');
        if(!preg_match('/^[a-f0-9]{24}$/D',$id))throw new InvalidArgumentException('id');
        mobile_json(['ok'=>true,'campaign'=>pilot_campaign_arm($id),'dispatched'=>false]);
    }
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
    if(isset($data['contact_ids'])&&!is_array($data['contact_ids']))throw new InvalidArgumentException('targets');
    $targets=$data['contact_ids']??[];
    $item=pilot_campaign_create((string)($data['name']??''),(string)($data['starts']??''),(string)($data['ends']??''),(string)($data['scenario']??''),$targets);
    mobile_json(['ok'=>true,'campaign'=>$item,'dispatched'=>false]);
} catch (InvalidArgumentException $e) {
    mobile_json(['ok'=>false,'error'=>'invalid_campaign'],422);
} catch (Throwable $e) {
    mobile_json(['ok'=>false,'error'=>'campaign_store_error'],500);
}
