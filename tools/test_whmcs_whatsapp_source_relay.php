<?php
declare(strict_types=1);
require_once __DIR__.'/../integrations/whatsapp-webhook-relay/Relay.php';
function bridge_assert(bool $condition,string $message):void{
    if(!$condition)throw new RuntimeException('BRIDGE_TEST_FAILED: '.$message);
}
$queue=sys_get_temp_dir().'/ontrack-wa-bridge-'.bin2hex(random_bytes(6));
mkdir($queue,0700,true);
$callback='https://app.ontrackegy.com/saas/console/whatsapp-webhook.php?id='.str_repeat('a',36);
$secret=str_repeat('b',64);
$raw=json_encode(['object'=>'whatsapp_business_account','entry'=>[[
    'changes'=>[['field'=>'messages','value'=>[
        'metadata'=>['phone_number_id'=>'123456789012345'],
        'messages'=>[['id'=>'wamid.test.bridge.0001','from'=>'201000000000',
            'text'=>['body'=>'رسالة تجريبية'],'type'=>'text']]
    ]]]
]]],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
try {
    $failed=static fn(string $url,string $sig,string $body):array=>['status'=>503,'body'=>''];
    $state=ontrack_wa_relay_after_verified($raw,$callback,$secret,$queue,$failed);
    bridge_assert($state==='queued','first failure spools instead of losing message');
    bridge_assert(count(glob($queue.'/*.wa-event'))===1,'one durable spool file');
    ontrack_wa_relay_after_verified($raw,$callback,$secret,$queue,$failed);
    bridge_assert(count(glob($queue.'/*.wa-event'))===1,'redelivery does not duplicate queue file');
    $retried=ontrack_wa_relay_retry($callback,$secret,$queue,5,$failed);
    bridge_assert($retried['retained']===1&&$retried['sent']===0,'failed event is retained');
    $ok=static function(string $url,string $sig,string $body)use($callback,$secret,$raw):array{
        bridge_assert($url===$callback,'destination is pinned to OnTrack');
        bridge_assert(hash_equals('sha256='.hash_hmac('sha256',$body,$secret),$sig),'valid signed source payload');
        bridge_assert($body===$raw,'original signed Meta payload unchanged');
        return ['status'=>200,'body'=>'{"ok":true,"accepted":1,"duplicates":0}'];
    };
    $retried=ontrack_wa_relay_retry($callback,$secret,$queue,5,$ok);
    bridge_assert($retried['sent']===1&&$retried['retained']===0,'retry acknowledged');
    bridge_assert(count(glob($queue.'/*.wa-event'))===0,'successful event removed from queue');
    bridge_assert(ontrack_wa_relay_after_verified($raw,$callback,$secret,$queue,$ok)==='forwarded','immediate verified source forwarding');
    bridge_assert(count(glob($queue.'/*.wa-event'))===0,'successful forwarding does not spool');
    $rejected=false;
    try{ontrack_wa_relay_after_verified('{"object":"unknown"}',$callback,$secret,$queue,$ok);}
    catch(InvalidArgumentException){$rejected=true;}
    bridge_assert($rejected,'only official WhatsApp object is supported');
    $rejected=false;
    try{ontrack_wa_relay_after_verified($raw,'https://attacker.example/hook',$secret,$queue);}
    catch(Throwable){$rejected=true;}
    bridge_assert($rejected,'external arbitrary webhook destinations are rejected');
    echo "WHMCS_WHATSAPP_RELAY_DURABLE_RETRY_PASS\n";
}finally{
    foreach(glob($queue.'/*')?:[] as $path)@unlink($path);
    @unlink($queue.'/.drain.lock');@rmdir($queue);
}
