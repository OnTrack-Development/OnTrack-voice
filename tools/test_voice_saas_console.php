<?php
declare(strict_types=1);
require __DIR__.'/../saas/console/database/Seed.php';
function ok(bool $v,string $label):void {if(!$v)throw new RuntimeException('FAIL '.$label);echo "PASS $label\n";}
$seed=seed_console();
ok(in_array($seed['status'],['seeded','already_seeded'],true),'schema + demo seed');
ok((int)one('SELECT COUNT(*) n FROM accounts')['n']>=7,'seven tenant demo entities');
ok((int)one('SELECT COUNT(*) n FROM agents')['n']>=5,'site-separated AI agents');
$admin=one("SELECT * FROM users WHERE email='admin@ontrackegy.com'");
ok((bool)$admin,'admin exists');
ok((password_get_info($admin['password_hash'])['algoName']??'unknown')!=='unknown','admin has secure password hash');
ok(!password_verify('OnTrack@Admin-2026-X7qM',$admin['password_hash']),'known demo password rejected');
$_SESSION=['uid'=>(int)$admin['id'],'account_id'=>1];
ok((mine()['kind']??'')==='platform','admin membership');
ok(has_platform(),'admin platform rights');
$reseller=one("SELECT * FROM users WHERE email='reseller@delta.test'");
$_SESSION=['uid'=>(int)$reseller['id'],'account_id'=>2];
ok((mine()['kind']??'')==='reseller','reseller membership');
ok(is_reseller_child(2,5),'reseller owns child');
ok(!is_reseller_child(2,7),'reseller cannot read other child');
ok(support_access(5),'reseller support scope own customer');
ok(!support_access(7),'reseller support denied cross tenant');
$account=account(5);
ok(brand($account)['name']==='دلتا فويس','reseller white-label inherited');
$direct=account(4);
ok(brand($direct)['name']==='OnTrack Voice','direct OnTrack identity');
require_once __DIR__.'/../saas/console/_core/TenantVoiceSession.php';
ok(tenant_voice_entitled(['account_id'=>1]),'active platform admin works without recurring trial or subscription');
ok(tenant_voice_entitled(['account_id'=>4]),'active customer subscription authorizes voice');
execute("UPDATE subscriptions SET status='suspended' WHERE account_id=4");
ok(!tenant_voice_entitled(['account_id'=>4]),'suspended subscription blocks voice');
execute("UPDATE subscriptions SET status='active',ends_at=? WHERE account_id=4",[gmdate('Y-m-d H:i:s',time()-60)]);
ok(!tenant_voice_entitled(['account_id'=>4]),'expired subscription blocks voice');
execute("UPDATE subscriptions SET ends_at=? WHERE account_id=4",[gmdate('Y-m-d H:i:s',time()+86400*30)]);
execute("UPDATE accounts SET status='suspended' WHERE id=1");
ok(!tenant_voice_entitled(['account_id'=>1]),'suspended platform account blocks voice');
execute("UPDATE accounts SET status='active' WHERE id=1");
ok(tenant_voice_entitled(['account_id'=>1]),'platform restored after account reactivation');
execute("DELETE FROM subscriptions WHERE account_id=4 AND plan_id!=4");
ok((int)site_limit(4,'site_limit')===4,'direct business plan permits multiple sites');
ok(owned_site(3,5)!==null,'site belongs to right account');
ok(owned_site(1,5)===null,'site isolation denied');
ok((int)one("SELECT COUNT(*) n FROM voices WHERE ready=0 AND provider='chatterbox_multilingual'")['n']===3,'unready AYO voices cannot be offered');
ok((int)one('SELECT COUNT(*) n FROM orders WHERE status=?',['pending_manual_review'])['n']===1,'manual payment pending');
require_once __DIR__.'/../saas/console/_core/VoiceCatalog.php';
ok(count(google_live_voices())===30,'official Google Live catalog contains 30 voice options');
ok(sync_google_live_voices()===30,'Google catalogue sync');
ok((int)one("SELECT COUNT(*) n FROM voices WHERE provider='gemini_live'")['n']>=30,'Google voices persisted');
$secret='otv_'.bin2hex(random_bytes(32));
$sealed=seal_secret($secret);
ok(!str_contains($sealed,$secret),'secret encrypted at rest');
ok(unseal_secret($sealed)===$secret,'authorized secret decrypts');
$cols=all("PRAGMA table_info(api_keys)");
ok(in_array('secret_enc',array_column($cols,'name'),true),'existing DB migrated with key vault column');
$_SESSION=['uid'=>(int)$admin['id'],'account_id'=>1];
ok(support_mode(0)==='full','default support access is full');
execute("INSERT OR REPLACE INTO support_policies(account_id,access_mode) VALUES (2,'read_only')");
$_SESSION['support']=['account_id'=>5,'origin_id'=>1,'expires'=>time()+900];
ok((mine()['role']??'')==='owner','platform full support can manage customer');
unset($_SESSION['support']);
$_SESSION=['uid'=>(int)$reseller['id'],'account_id'=>2,
           'support'=>['account_id'=>5,'origin_id'=>2,'expires'=>time()+900]];
ok((mine()['role']??'')==='support_viewer','reseller can be configured read-only');
ok(!allowed('agents.write'),'read-only support cannot modify agent');
execute("UPDATE support_policies SET access_mode='full' WHERE account_id=2");
ok((mine()['role']??'')==='owner' && allowed('agents.write'),'reseller support full mode permits owner actions in own customer');
$_SESSION['support']['account_id']=7;
ok((mine()['account_id']??0)!==7,'cross-reseller support session rejected');
unset($_SESSION['support']);
// A signed payment event must never claim an existing OnTrack identity by email alone.
require_once __DIR__.'/../saas/console/_core/OrderClaim.php';
$priorUsers=(int)one('SELECT COUNT(*) n FROM users')['n'];
$priorAccounts=(int)one('SELECT COUNT(*) n FROM accounts')['n'];
$priorMembers=(int)one('SELECT COUNT(*) n FROM memberships')['n'];
$priorSubscriptions=(int)one('SELECT COUNT(*) n FROM subscriptions')['n'];
$intId=insert("INSERT INTO integrations(account_id,name,provider,token_hash,token_prefix,status,created_at) VALUES (?,?,?,?,?,'active',?)",
    [2,'test-identity-link','whmcs',hash('sha256',random_bytes(32)),'test',now()]);
$orderId=insert("INSERT INTO orders(account_id,external_order_id,integration_id,plan_id,customer_name,customer_email,amount_minor,status,created_at) VALUES (?,?,?,?,?,?,?,'pending_payment',?)",
    [2,'identity-verified-order-1001',$intId,3,'Faked external display name','admin@ontrackegy.com',500,now()]);
$payOrder=one('SELECT * FROM orders WHERE id=?',[$orderId]);
$payment=record_verified_external_payment($payOrder,'verified_payment_1001');
ok($payment['status']==='paid_pending_claim' && $payment['claim_required']===true,'verified payment recorded but requires identity claim');
ok((int)one('SELECT COUNT(*) n FROM users')['n']===$priorUsers,'email collision did not create or select login user');
ok((int)one('SELECT COUNT(*) n FROM accounts')['n']===$priorAccounts,'payment did not provision account before identity proof');
ok((int)one('SELECT COUNT(*) n FROM memberships')['n']===$priorMembers,'email collision cannot grant membership');
ok((int)one('SELECT COUNT(*) n FROM subscriptions')['n']===$priorSubscriptions,'unclaimed paid order cannot grant subscription');
$paymentReplay=record_verified_external_payment(one('SELECT * FROM orders WHERE id=?',[$orderId]),'verified_payment_1001');
ok($paymentReplay['duplicate']===true,'payment replay is idempotent');
ok((int)one('SELECT COUNT(*) n FROM outbox WHERE integration_id=? AND kind=?',[$intId,'payment.received_pending_claim'])['n']===1,'payment outbox created exactly once');
$conflict=false;
try{record_verified_external_payment(one('SELECT * FROM orders WHERE id=?',[$orderId]),'verified_payment_1002');}
catch(InvalidArgumentException $ex){$conflict=$ex->getMessage()==='payment_reference_conflict';}
ok($conflict,'different payment reference on paid order is rejected');
// Staff invitations must not silently attach an existing user by email.
require_once __DIR__.'/../saas/console/_core/StaffInvitations.php';
$inviteCols=driver()==='sqlite'?array_column(all('PRAGMA table_info(staff_invitations)'),'name'):[];
ok(driver()!=='sqlite'||in_array('accepted_by',$inviteCols,true),'v4 staff invitations migration');
execute("DELETE FROM memberships WHERE account_id=2 AND user_id=?", [(int)$admin['id']]);
execute("DELETE FROM staff_invitations WHERE account_id=2");
$usersBefore=(int)one('SELECT COUNT(*) n FROM users')['n'];
$membersBefore=(int)one('SELECT COUNT(*) n FROM memberships')['n'];
$inviteId=create_staff_invitation(2,'Targeted Admin','ADMIN@ONTRACKEGY.COM','manager',(int)$reseller['id']);
ok((int)one('SELECT COUNT(*) n FROM users')['n']===$usersBefore,'staff invitation does not create unverified user');
ok((int)one('SELECT COUNT(*) n FROM memberships')['n']===$membersBefore,'staff invitation does not grant existing user membership');
ok(count(pending_staff_invitations_for_user((int)$admin['id']))===1,'only invited logged-in identity sees invitation');
ok(count(pending_staff_invitations_for_user((int)$reseller['id']))===0,'unrelated identity cannot view invitation');
$wrongUser=false;
try{accept_staff_invitation($inviteId,(int)$reseller['id']);}
catch(InvalidArgumentException $e){$wrongUser=$e->getMessage()==='invitation_not_available';}
ok($wrongUser,'wrong authenticated user cannot claim invite');
$duplicateInvite=false;
try{create_staff_invitation(2,'Targeted Admin','admin@ontrackegy.com','finance',(int)$reseller['id']);}
catch(InvalidArgumentException $e){$duplicateInvite=$e->getMessage()==='invitation_already_pending';}
ok($duplicateInvite,'duplicate pending invite rejected');
$_SESSION=['uid'=>(int)$admin['id'],'account_id'=>1,'support'=>['account_id'=>5,'origin_id'=>1,'expires'=>time()+900]];
$supportAccept=false;
try{accept_staff_invitation($inviteId,(int)$admin['id']);}
catch(InvalidArgumentException $e){$supportAccept=$e->getMessage()==='support_session_forbidden';}
ok($supportAccept,'support session cannot accept an invitation');
unset($_SESSION['support']);
$joined=accept_staff_invitation($inviteId,(int)$admin['id']);
ok($joined===2,'authenticated invited user can accept own invite');
ok((int)one('SELECT COUNT(*) n FROM memberships WHERE account_id=2 AND user_id=?',[$admin['id']])['n']===1,'accepted invitation creates exactly one scoped membership');
ok((string)one('SELECT role FROM memberships WHERE account_id=2 AND user_id=?',[$admin['id']])['role']==='manager','invited role is enforced');
$replayDenied=false;
try{accept_staff_invitation($inviteId,(int)$admin['id']);}
catch(InvalidArgumentException $e){$replayDenied=$e->getMessage()==='invitation_not_available';}
ok($replayDenied,'invitation replay cannot create another membership');
$newInvite=create_staff_invitation(2,'New Unregistered','future-staff@example.invalid','viewer',(int)$reseller['id']);
ok((int)one('SELECT COUNT(*) n FROM users')['n']===$usersBefore,'unregistered invited email remains pending without login');
$crossCancel=false;
try{cancel_staff_invitation($newInvite,3);}
catch(InvalidArgumentException $e){$crossCancel=$e->getMessage()==='invitation_not_available';}
ok($crossCancel,'another tenant cannot cancel invitation');
cancel_staff_invitation($newInvite,2);
ok((string)one('SELECT status FROM staff_invitations WHERE id=?',[$newInvite])['status']==='cancelled','authorized cancellation revokes invitation');
$expiredId=create_staff_invitation(2,'Expired','support@ontrack.test','viewer',(int)$reseller['id']);
execute("UPDATE staff_invitations SET expires_at='2000-01-01 00:00:00' WHERE id=?",[$expiredId]);
$expiredDenied=false;
$supportUser=one("SELECT id FROM users WHERE email='support@ontrack.test'");
try{accept_staff_invitation($expiredId,(int)$supportUser['id']);}
catch(InvalidArgumentException $e){$expiredDenied=$e->getMessage()==='invitation_not_available';}
ok($expiredDenied,'expired invite cannot be accepted');
$reissued=create_staff_invitation(2,'Reissued','support@ontrack.test','viewer',(int)$reseller['id']);
ok($reissued===$expiredId,'expired invitation may be reissued without duplicate rows');
require_once __DIR__.'/test_voice_api_http.php';
require_once __DIR__.'/test_voice_dnc_ledger.php';
test_voice_dnc_ledger();
test_voice_api_http();
require_once __DIR__.'/test_voice_dnc_hooks_http.php';
test_voice_dnc_hooks_http();
require_once __DIR__.'/test_voice_dnc_actions.php';
test_voice_dnc_actions();
require_once __DIR__.'/test_tenant_device_pairing.php';
test_tenant_device_pairing();
require_once __DIR__.'/test_agent_flow_preview.php';
test_agent_flow_preview();
require_once __DIR__.'/test_agent_flow_preview_http.php';
test_agent_flow_preview_http();
require_once __DIR__.'/test_published_agent_flow.php';
test_published_agent_flow();
require_once __DIR__.'/test_console_checkout.php';
test_checkout();
require_once __DIR__.'/test_verified_signup.php';
test_signup();
require_once __DIR__.'/test_verified_checkout_settlement.php';
test_verified_checkout_settlement();
echo "VOICE_SAAS_SMOKE_PASS\n";
