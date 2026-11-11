<?php
declare(strict_types=1);
require __DIR__.'/../saas/mobile/server/call_ledger.php';
function expect(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
$base=sys_get_temp_dir().'/ontrack-call-ledger-'.bin2hex(random_bytes(6));
$id='a4c54816-859c-48f1-8d0c-30a4ae5e3207';
try{
    $first=ontrack_call_event(['call_id'=>$id,'sequence'=>1,'phase'=>'ringing'],$base);
    expect($first===['ok'=>true,'duplicate'=>false,'call_id'=>$id,'phase'=>'ringing'],'response contract');
    $otherId='b4c54816-859c-48f1-8d0c-30a4ae5e3207';
    $held=fopen($base.'/.ledger-'.$id.'.lock','c');
    expect($held!==false && flock($held,LOCK_EX|LOCK_NB),'hold first call lock');
    try {
        $other=ontrack_call_event(['call_id'=>$otherId,'sequence'=>1,'phase'=>'ringing'],$base);
        expect($other===['ok'=>true,'duplicate'=>false,'call_id'=>$otherId,'phase'=>'ringing'],
            'independent call is not serialized behind first call');
    } finally { flock($held,LOCK_UN);fclose($held); }
    $same=ontrack_call_event(['call_id'=>$id,'sequence'=>1,'phase'=>'ringing'],$base);
    expect($same['duplicate']===true,'dedupe');
    ontrack_call_event(['call_id'=>$id,'sequence'=>2,'phase'=>'active'],$base);
    ontrack_call_event(['call_id'=>$id,'sequence'=>3,'phase'=>'ended','duration_seconds'=>22],$base);
    ontrack_call_event(['call_id'=>$id,'sequence'=>4,'phase'=>'identified',
        'duration_seconds'=>22,'phone_number'=>'+201012345678','contact_name'=>'أحمد علي'],$base);
    $sameMetadata=ontrack_call_event(['call_id'=>$id,'sequence'=>4,'phase'=>'identified',
        'duration_seconds'=>22,'phone_number'=>'+201012345678','contact_name'=>'أحمد علي'],$base);
    expect($sameMetadata['duplicate']===true,'idempotent metadata');
    $rows=ontrack_call_list($base);
    expect(count($rows)===2,'two independent calls');
    $rows=array_values(array_filter($rows,static fn(array $row):bool=>$row['call_id']===$id));
    expect($rows[0]['phase']==='ended' && $rows[0]['duration_seconds']===22,'terminal record');
    expect($rows[0]['answered_at_utc']!==null,'answered timestamp');
    expect($rows[0]['contact_name']==='أحمد علي'&&$rows[0]['phone_number']==='+201012345678','verified caller');
    expect($rows[0]['phase']==='ended','identity does not reopen call');
    try{ontrack_call_event(['call_id'=>$id,'sequence'=>5,'phase'=>'identified','phone_number'=>'+201000000000'],$base);
        throw new RuntimeException('conflicting identity allowed');}
    catch(InvalidArgumentException $e){expect($e->getMessage()==='conflicting_phone','identity integrity');}
    try{ontrack_call_event(['call_id'=>$id,'sequence'=>6,'phase'=>'active'],$base);throw new RuntimeException('accepted reopened call');}
    catch(InvalidArgumentException $e){expect($e->getMessage()==='call_already_ended','closed guard');}
    try{ontrack_call_event(['call_id'=>'../../bad','sequence'=>1,'phase'=>'ringing'],$base);throw new RuntimeException('accepted bad id');}
    catch(InvalidArgumentException $e){expect($e->getMessage()==='invalid_call_id','path guard');}
    echo "Call ledger tests PASS\n";
}finally{
    foreach(glob($base.'/*')?:[] as $p)if(is_file($p))unlink($p);
    foreach(glob($base.'/.ledger-*.lock')?:[] as $p)unlink($p);
    if(is_file($base.'/.ledger.lock'))unlink($base.'/.ledger.lock');
    if(is_dir($base))rmdir($base);
}
