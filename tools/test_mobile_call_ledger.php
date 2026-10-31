<?php
declare(strict_types=1);
require __DIR__.'/../saas/mobile/server/call_ledger.php';
function expect(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
$base=sys_get_temp_dir().'/ontrack-call-ledger-'.bin2hex(random_bytes(6));
$id='a4c54816-859c-48f1-8d0c-30a4ae5e3207';
try{
    $first=ontrack_call_event(['call_id'=>$id,'sequence'=>1,'phase'=>'ringing'],$base);
    expect($first['ok']===true && !$first['duplicate'],'first event');
    $same=ontrack_call_event(['call_id'=>$id,'sequence'=>1,'phase'=>'ringing'],$base);
    expect($same['duplicate']===true,'dedupe');
    ontrack_call_event(['call_id'=>$id,'sequence'=>2,'phase'=>'active'],$base);
    ontrack_call_event(['call_id'=>$id,'sequence'=>3,'phase'=>'ended','duration_seconds'=>22],$base);
    $rows=ontrack_call_list($base);
    expect(count($rows)===1,'one call');
    expect($rows[0]['phase']==='ended' && $rows[0]['duration_seconds']===22,'terminal record');
    expect($rows[0]['answered_at_utc']!==null,'answered timestamp');
    try{ontrack_call_event(['call_id'=>$id,'sequence'=>4,'phase'=>'active'],$base);throw new RuntimeException('accepted reopened call');}
    catch(InvalidArgumentException $e){expect($e->getMessage()==='call_already_ended','closed guard');}
    try{ontrack_call_event(['call_id'=>'../../bad','sequence'=>1,'phase'=>'ringing'],$base);throw new RuntimeException('accepted bad id');}
    catch(InvalidArgumentException $e){expect($e->getMessage()==='invalid_call_id','path guard');}
    echo "Call ledger tests PASS\n";
}finally{
    foreach(glob($base.'/*')?:[] as $p)if(is_file($p))unlink($p);
    if(is_file($base.'/.ledger.lock'))unlink($base.'/.ledger.lock');
    if(is_dir($base))rmdir($base);
}
