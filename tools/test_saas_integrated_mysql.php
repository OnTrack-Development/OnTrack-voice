<?php
declare(strict_types=1);
/**
 * MariaDB/MySQL real PDO regression; isolated GitHub Actions service only.
 * Never run against /home/appontrack or a production database.
 */
if(PHP_SAPI!=='cli' || getenv('ONTRACK_CI_MYSQL_ENABLED')!=='1'
   || is_dir('/home/appontrack')
   || !preg_match('/^saasmysql_[A-Za-z0-9_]{8,55}$/D',
       (string)(getenv('ONTRACK_CI_NAMESPACE')?:''))) {
    throw new RuntimeException('mysql_fixture_only');
}
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/SubscriptionPlans.php';
require_once __DIR__.'/../saas/console/_core/DevicePairing.php';
require_once __DIR__.'/../saas/console/_core/TenantDeviceContract.php';
require_once __DIR__.'/../saas/console/_core/TenantWorkspace.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
require_once __DIR__.'/../saas/console/_core/VoiceKnowledgeWeb.php';

function mysql_qa(bool $pass,string $case):void {
    if(!$pass)throw new RuntimeException('SAAS_MYSQL_REAL_PDO_FAIL '.$case);
}
$host=(string)getenv('SAAS_CI_MYSQL_HOST');
$name=(string)getenv('SAAS_CI_MYSQL_DB');
$user=(string)getenv('SAAS_CI_MYSQL_USER');
$password=(string)getenv('SAAS_CI_MYSQL_PASSWORD');
mysql_qa($host==='127.0.0.1' && $name==='ontrack_saas_ci'
    && $user==='ontrack_ci' && $password!=='' &&
    strlen($password)>12,'restricted to ephemeral GitHub service');
$cfg=['driver'=>'mysql','host'=>$host,'port'=>3306,'database'=>$name,
      'user'=>$user,'password'=>$password];
$path=private_path().'/database.json';
mysql_qa(file_put_contents($path,json_encode($cfg,JSON_THROW_ON_ERROR),LOCK_EX)!==false,
    'private mysql test configuration written');
chmod($path,0600);
unset($cfg,$password);
$seed=seed_console();
mysql_qa(driver()==='mysql' && $seed['status']==='seeded','real MySQL schema and migrations seeded');
mysql_qa((int)one('SELECT COUNT(*) n FROM accounts')['n']===7,'seven separated tenant accounts');
mysql_qa((int)one('SELECT COUNT(*) n FROM plans')['n']>=4,'existing plans preserved');
// Service-layer staff grants must obey exact-tenant admin roles on real PDO.
require_once __DIR__.'/../saas/console/_core/StaffInvitations.php';
$platformOwner=(int)one("SELECT user_id FROM memberships
    WHERE account_id=1 AND role='owner' ORDER BY id LIMIT 1")['user_id'];
$resellerOwner=(int)one("SELECT user_id FROM memberships
    WHERE account_id=2 AND role='owner' ORDER BY id LIMIT 1")['user_id'];
$foreignInviterDenied=false;
try{
    create_staff_invitation(1,'Cross tenant denial','mysql-no-invites@example.test',
        'viewer',$resellerOwner);
}catch(InvalidArgumentException $error){
    $foreignInviterDenied=$error->getMessage()==='invalid_inviter';
}
mysql_qa($foreignInviterDenied,'real MySQL foreign inviter cannot create platform membership');
$inviteId=create_staff_invitation(1,'MySQL only invitation','mysql-invite@example.test',
    'viewer',$platformOwner);
mysql_qa($inviteId>0 &&
    (int)one("SELECT COUNT(*) n FROM staff_invitations WHERE id=? AND status='pending'",
        [$inviteId])['n']===1,
    'real MySQL account-locked owner invitation remains pending and creates no membership');

// MySQL 8 rejected the historical TEXT+UNIQUE external_id schema: verify
// real event deduplication works without truncating accepted 128-char IDs.
$seedIntegrationId=insert("INSERT INTO integrations
    (account_id,name,provider,token_hash,token_prefix,status,created_at)
    VALUES (?,?,?,?,?,'active',?)",[
    4,'MySQL fixture webhook','webhook',hash('sha256','mysql-ci-hook-fixture'),
    'mysql-ci-fixture',now()
]);
mysql_qa($seedIntegrationId>0,'isolated webhook integration fixture created');
$eventId=str_repeat('e',128);
$event=insert("INSERT INTO events(integration_id,external_id,event_type,payload,status,created_at)
    VALUES (?,?,?,?,?,?)",[
    $seedIntegrationId,$eventId,'knowledge.snapshot','{}','received',now()
]);
mysql_qa($event>0,'128-character legitimate webhook event is indexable');
$dedup=false;
try {
    insert("INSERT INTO events(integration_id,external_id,event_type,payload,status,created_at)
        VALUES (?,?,?,?,?,?)",[
        $seedIntegrationId,$eventId,'knowledge.snapshot','{}','received',now()
    ]);
} catch(PDOException $error) {
    $dedup=(string)$error->getCode()==='23000';
}
mysql_qa($dedup,'duplicate webhook is rejected by MySQL unique key');

$owner=(int)one("SELECT user_id FROM memberships WHERE account_id=4 AND role='owner' LIMIT 1")['user_id'];
$agent=one("SELECT id,site_id FROM agents WHERE account_id=4 AND site_id=1 LIMIT 1");
mysql_qa(is_array($agent),'active seeded agent');
$agentId=(int)$agent['id'];$site=(int)$agent['site_id'];
$before=one("SELECT id,plan_id,ends_at FROM subscriptions WHERE account_id=4
    AND status='active' AND starts_at<=? AND ends_at>? ORDER BY id DESC LIMIT 1",
    [now(),now()]);
mysql_qa($before!==null,'active customer subscription exists');
$otherPlan=(int)one("SELECT id FROM plans WHERE kind='user' AND status='active'
    AND id<>? ORDER BY id LIMIT 1",[(int)$before['plan_id']])['id'];
$assigned=subscription_assign_demo(4,$otherPlan);
mysql_qa($assigned['changed']===true,'atomic demo plan transition on real InnoDB');
$again=subscription_assign_demo(4,$otherPlan);
mysql_qa($again['changed']===false &&
    $again['subscription_id']===$assigned['subscription_id'],'idempotent plan assignment');
$current=one('SELECT ends_at FROM subscriptions WHERE id=?',[$assigned['subscription_id']]);
mysql_qa($current['ends_at']===$before['ends_at'],
    'switching plan cannot reset the already granted period');
$old=one('SELECT status FROM subscriptions WHERE id=?',[$before['id']]);
mysql_qa($old['status']==='replaced','historical plan retained');
$published=publish_agent_flow(4,$agentId,$owner);
mysql_qa($published['revision']>=1,'agent Flow published on MySQL');
$publishAgain=publish_agent_flow(4,$agentId,$owner);
mysql_qa($publishAgain['duplicate']===true &&
    $publishAgain['revision']===$published['revision'],
    'repeated MySQL publish cannot allocate an extra immutable revision');
$pair=device_pairing_create(4,$site,$agentId,$owner,0);
$bound=device_pairing_redeem($pair,'mysql-test-phone-installation-001',0,'MySQL CI SIM');
$b=device_binding_authorize((string)$bound['device_token']);
mysql_qa($b!==null,'paired SIM authorized');
$heartbeat1=device_binding_heartbeat($b);
$heartbeat2=device_binding_heartbeat($b);
mysql_qa($heartbeat1['device_id']===$heartbeat2['device_id'] &&
    $heartbeat2['account_id']===4,'MySQL unchanged last_seen_at heartbeat remains valid');
$probe=tenant_device_contract_probe($b);
mysql_qa($probe['published_flow_ready']===true &&
    $probe['account_id']===4 && $probe['sim_slot']===0,
    'scoped published agent contract');
$kb=voice_knowledge_add(4,$site,'مواعيد التجربة','txt',
    'المواعيد: من التاسعة صباحا إلى الخامسة مساءً','public');
mysql_qa($kb>0,'public scoped knowledge inserted with MySQL');
$context=voice_kb_agent_context(4,$site,$agentId,1000,'مواعيد');
mysql_qa(str_contains($context,'المواعيد'),'approved knowledge available only to own agent');
$foreign=voice_kb_agent_context(5,$site,$agentId,1000,'مواعيد');
mysql_qa($foreign==='','foreign tenant cannot access approved knowledge');
$callId='ee11aa22-3344-4566-8899-aabbccddeeff';
$events=[
 ['call_id'=>$callId,'sequence'=>1,'phase'=>'ringing','duration_seconds'=>0],
 ['call_id'=>$callId,'sequence'=>2,'phase'=>'active','duration_seconds'=>4],
 ['call_id'=>$callId,'sequence'=>3,'phase'=>'ended','duration_seconds'=>21],
];
foreach($events as $e)mysql_qa(tenant_workspace_call_event($b,$e)['accepted']===true,
    'real DB call event accepted');
mysql_qa(tenant_workspace_call_event($b,$events[2])['duplicate']===true,
    'duplicate terminal event ignored');
mysql_qa((int)one("SELECT COUNT(*) n FROM usage_ledger WHERE account_id=4
    AND kind='connected_call'")['n']===1,'answered+ended call billed at most once');
$usageRef='voice-'.hash('sha256','4:'.$site.':'.$b['id'].':0:'.$callId);
$ledgerStart=one('SELECT created_at FROM usage_ledger WHERE reference=?',[$usageRef]);
$callStart=one('SELECT created_at FROM calls WHERE device_binding_id=? AND external_call_id=?',
    [$b['id'],$callId]);
mysql_qa($ledgerStart!==null && $callStart!==null &&
    $ledgerStart['created_at']===$callStart['created_at'],
    'real MySQL ledger uses original call start for subscription period');
$oldCall='bb22cc33-4455-4677-8899-aabbccddee01';
insert("INSERT INTO calls(account_id,site_id,device_binding_id,sim_slot,
    external_call_id,phone,name,duration_seconds,status,is_demo,created_at)
    VALUES(?,?,?,?,?,'','',?,?,0,?)",
    [4,$site,$b['id'],0,$oldCall,23,'ended','2020-01-01 00:00:00']);
voice_usage_update($b,$oldCall,'active');
mysql_qa(voice_usage_update($b,$oldCall,'ended')===false,
    'real MySQL old calls cannot be retroactively charged to current plan');
mysql_qa((int)one("SELECT COUNT(*) n FROM usage_ledger WHERE account_id=4
    AND kind='connected_call'")['n']===1,
    'real MySQL unlicensed historical call never increments current usage');
$brief=tenant_workspace_call_report($b,[
    'call_id'=>$callId,'summary'=>'ملخص مكالمة تجربة مؤكدة',
    'handoff_status'=>'requested','handoff_reason'=>'طلب موظف مسؤول']);
mysql_qa($brief['saved']===true,'call report persisted');
$history=tenant_workspace_calls($b);
$matching=array_values(array_filter($history['calls'],
    static fn(array $row):bool=>($row['call_id']??'')===$callId));
mysql_qa(count($matching)===1 &&
    $matching[0]['handoff_status']==='requested',
    'call report on authorized SIM only, independent of unrelated historical calls');
$st=he_contact_add(4,'مدير التجربة','+201001234567','manager');
mysql_qa($st>0,'authorized manager contact only');
he_route_configure(4,$agentId,$st,true,10);
// MySQL timestamps only have second precision; if routing is configured
// inside the same second as ringing, it is not provably "later". Force
// the historical scenario explicitly so the guard is deterministic.
execute("UPDATE human_escalation_routes SET updated_at=? WHERE account_id=? AND site_id=? AND agent_id=?",
    [gmdate('Y-m-d H:i:s',time()+60),4,$site,$agentId]);
$notification=he_schedule($b,$callId);
// Route created after call started: DO NOT queue retroactive manager call.
mysql_qa($notification['queued']===false &&
    $notification['reason']==='staff_route_configured_after_call',
    'no retroactive staff dialing');
mysql_qa((int)one('SELECT COUNT(*) n FROM human_escalation_jobs')['n']===0,
    'no staff dialing job is scheduled by fixture');
ob_start();render_billing(['account_id'=>4,'kind'=>'customer','role'=>'viewer']);
$html=(string)ob_get_clean();
mysql_qa(str_contains($html,'اشتراك الحساب') &&
    !str_contains($html,'لا توجد باقة نشطة'),
    'active plan rendered correctly with real MySQL');
require_once __DIR__.'/../saas/console/_core/Checkout.php';
require_once __DIR__.'/../saas/console/_core/KashierWebhook.php';
require_once __DIR__.'/../saas/console/_core/OrderClaim.php';
// Real InnoDB: paid renewal must add a term, never erase existing paid time.
// The synthetic signature is local only; no Kashier HTTP call or real charge.
$paidPlan=insert("INSERT INTO plans
    (kind,name,call_quota,site_limit,agent_limit,staff_limit,reseller_customers,
     price_minor,status,created_at)
    VALUES ('user','CI paid renewal fixture',800,2,2,3,0,2575,'active',?)",[now()]);
$priorPaidEnd=gmdate('Y-m-d H:i:s',time()+60*86400);
execute("UPDATE subscriptions SET ends_at=? WHERE account_id=4 AND status='active'",
    [$priorPaidEnd]);
$paymentRef='ovc_'.bin2hex(random_bytes(16));
$verifiedTx='TX_'.bin2hex(random_bytes(16));
$payOrder=insert("INSERT INTO orders
    (account_id,integration_id,external_order_id,plan_id,customer_name,customer_email,
     amount_minor,status,created_at)
    VALUES (?,NULL,?,?,?,?,?,'pending_payment',?)",
    [4,$paymentRef,$paidPlan,'CI synthetic card','pay-ci@example.invalid',2575,now()]);
execute('UPDATE plans SET price_minor=3500 WHERE id=?',[$paidPlan]);
$webhook=['event'=>'pay','data'=>[
    'merchantOrderId'=>$paymentRef,'amount'=>'25.75',
    'currency'=>'EGP','status'=>'SUCCESS','transactionId'=>$verifiedTx,
    'method'=>'card']];
$webhook['data']['signatureKeys']=array_keys($webhook['data']);
$signed=kashier_signature($webhook['data'],'CI-only-signing-key-not-real');
mysql_qa(is_string($signed) &&
    kashier_verified($webhook,$signed,'CI-only-signing-key-not-real'),
    'synthetic Kashier event signature valid before MySQL settlement');
$settled=kashier_settle($webhook,null);
mysql_qa($settled['status']==='active' && $settled['duplicate']===false,
    'price-change-after-checkout cannot strand a verified paid order');
$newPaid=one("SELECT plan_id,ends_at FROM subscriptions
    WHERE account_id=4 AND status='active' LIMIT 1");
mysql_qa((int)$newPaid['plan_id']===$paidPlan &&
    $newPaid['ends_at']===gmdate('Y-m-d H:i:s',strtotime($priorPaidEnd)+30*86400),
    'real InnoDB carries 60 paid days and credits an additional 30 days');
$duplicate=kashier_settle($webhook,null);
mysql_qa($duplicate['duplicate']===true,'signed MySQL payment replay is idempotent');
mysql_qa((int)one("SELECT COUNT(*) n FROM outbox
    WHERE account_id=4 AND kind='subscription.activated'")['n']===1,
    'exactly one paid subscription activation outbox event on InnoDB');
mysql_qa((int)one("SELECT COUNT(*) n FROM orders
    WHERE id=? AND status='active' AND amount_minor=2575",[$payOrder])['n']===1,
    'order keeps its original signed 2575 minor-unit charge despite new public price');
echo "SAAS_REAL_MYSQL_KASHIER_RENEWAL_ORDER_SNAPSHOT_PASS\n";
$refundBody=$webhook;
$refundBody['event']='refund';
$refundBody['data']['transactionId']='RF_'.bin2hex(random_bytes(16));
$refundSigned=kashier_signature($refundBody['data'],'CI-only-signing-key-not-real');
mysql_qa(kashier_verified($refundBody,(string)$refundSigned,
    'CI-only-signing-key-not-real'),
    'real MySQL refund fixture uses authenticated provider HMAC');
$refundRecorded=kashier_settle($refundBody,null);
mysql_qa($refundRecorded['status']==='adjustment_pending_review'
    && !$refundRecorded['duplicate'],
    'signed refund retained for finance review on real InnoDB');
mysql_qa(kashier_settle($refundBody,null)['duplicate']===true,
    'real InnoDB refund replay cannot create a second audit event');
mysql_qa((int)one("SELECT COUNT(*) n FROM kashier_payment_adjustments
    WHERE order_id=?",[$payOrder])['n']===1,
    'unique refund audit row persisted on real MySQL');
ob_start();
render_billing(['account_id'=>4,'kind'=>'customer','role'=>'owner']);
$financeHtml=(string)ob_get_clean();
mysql_qa(str_contains($financeHtml,'مراجعات الاسترداد')
    && str_contains($financeHtml,$paymentRef),
    'customer finance owner can see signed adjustment in Billing');
mysql_qa((string)one("SELECT status FROM orders WHERE id=?",[$payOrder])['status']==='active',
    'signed refund does not silently revoke existing paid account');
echo "SAAS_REAL_MYSQL_KASHIER_REFUND_REVIEW_AND_REPLAY_PASS\n";


echo "SAAS_REAL_MYSQL_MIGRATIONS_TENANCY_PLANS_FLOW_KB_SIM_CALLS_BILLING_PASS\n";
