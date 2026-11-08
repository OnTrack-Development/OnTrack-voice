<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/WhatsAppNativeAgent.php';
require_once __DIR__.'/../saas/console/_core/WhatsAppJobSafety.php';
if(is_dir('/home/appontrack')&&!getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('isolated_fixture_required');
function wa67(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('V67_WA_FAIL '.$label);}
seed_console();
$site=one("SELECT id FROM sites WHERE account_id=4 AND status='active' ORDER BY id LIMIT 1");
$agent=one("SELECT id FROM agents WHERE account_id=4 AND site_id=? LIMIT 1",[$site['id']]);
if(!published_agent_flow(4,(int)$site['id'],(int)$agent['id']))
    publish_agent_flow(4,(int)$agent['id'],1);
$secret=bin2hex(random_bytes(32));
$channel=wa_create_existing_connection(4,(int)$site['id'],(int)$agent['id'],
    'V67 existing module','https://services.ontrackegy.com/modules/addons/whatsapp_notifications/webhook.php',
    'existing-verify-token-v67-12345',bin2hex(random_bytes(18)),$secret);
$c=wa_connection($channel,4);
wa67(!wa_existing_outbound_ready($c),'incoming alone cannot authorize outbound');
$disabled=false;
try{wa_native_toggle(4,$channel,true);}catch(RuntimeException){$disabled=true;}
wa67($disabled,'cannot falsely activate AI on receive-only channel');
$event=['version'=>1,'channel'=>'whatsapp','event'=>'message.received',
  'phone_number_id'=>'1099000000777','message'=>[
   'id'=>'wamid.v67_in_001','from'=>'201002223344','name'=>'Owner','text'=>'صباح الخير']];
$c=wa_relay_bind_existing_phone($c,$event);
wa67($c['state']==='relay_receiving','verified inbound has own state');
$transport=static function(string $url,string $ts,string $signature,string $raw)use($secret):array {
    wa67(str_contains($url,'whatsapp_notifications/ontrack_outbound.php'),'pinned existing addon endpoint');
    wa67(hash_equals('sha256='.hash_hmac('sha256',$ts."\n".$raw,$secret),$signature),
        'outbound contract signed with existing encrypted relay secret');
    $payload=json_decode($raw,true);
    if(($payload['action']??'')==='capabilities')
        return ['status'=>200,'body'=>json_encode(['channel'=>'whatsapp',
            'phone_number_id'=>$payload['phone_number_id'],
            'capability'=>'ontrack-whatsapp-existing-outbound-v1',
            'send_supported'=>true],JSON_THROW_ON_ERROR)];
    wa67(($payload['action']??'')==='send_message','sender accepts only explicit send contract');
    wa67(($payload['message']['messaging_product']??'')==='whatsapp','original Meta contract passed to source module');
    return ['status'=>200,'body'=>json_encode(['channel'=>'whatsapp',
        'phone_number_id'=>$payload['phone_number_id'],'accepted'=>true,
        'provider_message_id'=>'wamid.v67_sent_001'],JSON_THROW_ON_ERROR)];
};
wa67(wa_existing_outbound_probe(4,$channel,$transport)['verified'],'verified real outbound capability handshake');
$c=wa_connection($channel,4);
wa67(wa_existing_outbound_ready($c),'existing adapter now ready');
$res=wa_meta_send($c,'201002223344','أهلاً بيك',$transport);
wa67($res['provider_message_id']==='wamid.v67_sent_001','source module acknowledged outbound provider id');
$ingest=wa_webhook_ingest($c,wa_relay_normalize($c,$event));
wa67($ingest['accepted']===1,'existing signed incoming message enters queue');
$id=(int)$ingest['conversation_ids'][0];
$job=one('SELECT * FROM wa_ai_jobs WHERE connection_id=? AND conversation_id=?',[$channel,$id]);
wa67($job!==null,'durable job');
execute("UPDATE wa_ai_jobs SET status='processing',attempts=1,updated_at='2000-01-01 00:00:00'
    WHERE id=?",[$job['id']]);
$rec=wa_recover_stale_jobs();
wa67($rec['retried']===1&&
    one('SELECT status FROM wa_ai_jobs WHERE id=?',[$job['id']])['status']==='pending',
    'abandoned pre-send model job can retry once');
execute("UPDATE wa_ai_jobs SET status='processing',attempts=2,updated_at='2000-01-01 00:00:00'
    WHERE id=?",[$job['id']]);
$rec=wa_recover_stale_jobs();
wa67($rec['failed']===1&&
    one('SELECT status FROM wa_ai_jobs WHERE id=?',[$job['id']])['status']==='failed',
    'exhausted model job marked failed not endlessly retry');
execute("UPDATE wa_ai_jobs SET status='sending',updated_at='2000-01-01 00:00:00'
    WHERE id=?",[$job['id']]);
$rec=wa_recover_stale_jobs();
wa67($rec['review_required']===1&&
    one('SELECT status FROM wa_ai_jobs WHERE id=?',[$job['id']])['status']==='needs_review',
    'uncertain external send never retried');
wa67(wa_recover_stale_jobs()['retried']===0,'recovery idempotent');
execute("UPDATE wa_ai_jobs SET status='processing',attempts=1,updated_at=? WHERE id=?",
    [now(),$job['id']]);
$sent=wa_job_send_once($job,'أهلا',$transport,[],true);
wa67($sent['status']==='accepted_not_delivered','existing channel sends AI reply through verified module');
wa67(wa_job_send_once($job,'duplicate',$transport,[],true)['status']!=='accepted_not_delivered',
    'cannot double-send committed job');
$job2=insert("INSERT INTO wa_ai_jobs
    (account_id,conversation_id,connection_id,source_message_id,generation,status,created_at,updated_at)
    VALUES (?,?,?,?,?,'processing',?,?)",[
    4,$id,$channel,'wamid.v67_in_002',(int)wa_conversation($id,4)['generation'],now(),now()]);
$row=one('SELECT * FROM wa_ai_jobs WHERE id=?',[$job2]);
$timeout=wa_job_send_once($row,'unknown',static function():array{
    throw new RuntimeException('transport_timeout');
},[],true);
wa67($timeout['status']==='needs_review' &&
    one('SELECT status FROM wa_ai_jobs WHERE id=?',[$job2])['status']==='needs_review',
    'network uncertainty persists without automatic retry');
echo "V67_WA_SIGNED_EXISTING_OUTBOUND_STALE_RECOVERY_NO_DUPLICATE_PASS\n";
