<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/EmailDelivery.php';
require_once __DIR__.'/../saas/console/_core/OperationalOutbox.php';
require_once __DIR__.'/../saas/console/_core/Signup.php';
function worker68(bool $ok,string $why):void{
    if(!$ok)throw new RuntimeException('V68_WORKERS_FAIL '.$why);
}
seed_console();
operational_outbox_init();
email_delivery_init();
$before=(int)one("SELECT COUNT(*) n FROM operational_event_receipts")['n'];
$outboxId=insert("INSERT INTO outbox(account_id,kind,payload,status,created_at)
 VALUES (4,'subscription.activated',?,'pending',?)",[
 json_encode(['order_id'=>99991,'test'=>true],JSON_THROW_ON_ERROR),now()]);
$first=operational_outbox_process($outboxId);
worker68($first['status']==='delivered_local','local event delivered to account ledger');
worker68(operational_outbox_process($outboxId)['status']==='not_pending',
 'outbox replay no duplicate');
worker68((int)one('SELECT COUNT(*) n FROM operational_event_receipts')['n']===$before+1,
 'one durable local receipt');
$bad=insert("INSERT INTO outbox(account_id,kind,payload,status,created_at)
 VALUES (4,'subscription.activated','not-json','pending',?)",[now()]);
worker68(operational_outbox_process($bad)['status']==='failed',
 'invalid event is not marked as delivered');
worker68((int)one("SELECT attempts FROM outbox WHERE id=?",[$bad])['attempts']===1,
 'failed attempt counted');
$old=insert("INSERT INTO outbox(account_id,kind,payload,status,attempts,created_at)
 VALUES (4,'payment.received_pending_claim',?,'processing',2,?)",[
 json_encode(['order_id'=>99992],JSON_THROW_ON_ERROR),'2000-01-01 00:00:00']);
worker68(operational_outbox_recover()>0,'orphan job recovered after worker death');
worker68(operational_outbox_process($old)['status']==='delivered_local',
 'recovered job processed exactly once');
worker68(operational_outbox_status(4)['states']['delivered_local']>=2,
 'account has inspectable delivery states');
$mail=insert("INSERT INTO operational_email_jobs
 (purpose,recipient_hash,payload_enc,status,attempts,retry_at,expires_at,created_at,updated_at)
 VALUES('signup',?,?,'pending',0,?,?,?,?)",[
 hash('sha256','v68@example.test'),
 seal_secret(json_encode(['to'=>'v68@example.test','subject'=>'V68 proof',
   'body'=>'Token link only'],JSON_THROW_ON_ERROR)),now(),
 gmdate('Y-m-d H:i:s',time()+1800),now(),now()]);
$calls=0;
$transport=static function(string $to,string $subject,string $body,array $cfg)use(&$calls):void{
    $calls++;
    worker68($to==='v68@example.test'&&$subject==='V68 proof'&&
      str_contains($body,'Token'),'mail passed to configured transport without logging secret');
    if($calls===1)throw new RuntimeException('smtp_temporary');
};
$pending=email_job_process($mail,$transport);
worker68($pending['status']==='pending','temporary SMTP failure becomes retryable');
worker68((int)one('SELECT attempts FROM operational_email_jobs WHERE id=?',[$mail])['attempts']===1,
 'mail failures audited with attempt count');
execute("UPDATE operational_email_jobs SET retry_at=? WHERE id=?",['2000-01-01 00:00:00',$mail]);
$sent=email_job_process($mail,$transport);
worker68($sent['status']==='accepted'&&$calls===2,
 'bounded retry eventually accepted by SMTP mock');
worker68(one("SELECT payload_enc FROM operational_email_jobs WHERE id=?",[$mail])['payload_enc']===null,
 'authorization token erased after acceptance');
worker68(email_job_process($mail,$transport)['status']==='not_due',
 'accepted email never sent twice');
$stuck=insert("INSERT INTO operational_email_jobs
 (purpose,recipient_hash,payload_enc,status,attempts,retry_at,expires_at,created_at,updated_at)
 VALUES('password_reset',?,?,'processing',1,?,?,?,'2000-01-01 00:00:00')",[
 hash('sha256','stuck@example.test'),seal_secret('{}'),now(),
 gmdate('Y-m-d H:i:s',time()+1800),now()]);
email_queue_batch(5,$transport);
worker68((string)one('SELECT status FROM operational_email_jobs WHERE id=?',[$stuck])['status']==='needs_review',
 'uncertain in-flight SMTP delivery never blindly retries');
$testMail='v68-signup-'.bin2hex(random_bytes(5)).'@example.test';
$proof='';
signup_stage('V68 Mail','Proof Testing',$testMail,'Strong2026Password',
 static function($email,$token)use(&$proof){$proof=$token;return true;});
worker68(strlen($proof)===64 && signup_confirm($proof)>0,
 'verified signup still creates account after email proof');
$unknown='absent-'.bin2hex(random_bytes(5)).'@example.test';
password_reset_stage($unknown,static function(){throw new RuntimeException('must_not_send');});
worker68(true,'password reset remains enumeration-safe');
echo "V68_OUTBOX_RECOVERY_SMTP_RETRY_SECRET_ERASURE_AND_SIGNUP_PASS\n";
