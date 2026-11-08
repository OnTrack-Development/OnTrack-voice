<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/CustomerAccounts.php';
require_once __DIR__.'/../saas/console/_core/BillingInvoices.php';
require_once __DIR__.'/../saas/console/_core/TenantVoiceSession.php';
if(is_dir('/home/appontrack')&&!getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('isolated_customer_fixture_only');
function test_customer(bool $ok,string $name):void {
    if(!$ok)throw new RuntimeException('CUSTOMER_V59_FAIL '.$name);
}
seed_console();
$admin=['kind'=>'platform','account_id'=>1,'role'=>'owner'];
$reseller=['kind'=>'reseller','account_id'=>2,'role'=>'owner'];
$foreign=['kind'=>'reseller','account_id'=>3,'role'=>'owner'];
$customer=customer_accounts_scope($reseller,5);
test_customer((int)$customer['parent_id']===2,'reseller child permitted');
$denied=false;
try{customer_accounts_scope($foreign,5);}catch(InvalidArgumentException){$denied=true;}
test_customer($denied,'foreign reseller cannot access customer');
$denied=false;
try{customer_accounts_scope($reseller,2);}catch(InvalidArgumentException){$denied=true;}
test_customer($denied,'self account not managed as customer');
$denied=false;
try{customer_accounts_scope($reseller,4);}catch(InvalidArgumentException){$denied=true;}
test_customer($denied,'direct customer not managed by reseller');
customer_accounts_update($reseller,5,[
   'name'=>'صيدلية اختبار محدثة','contact_name'=>'الشخص المسؤول','contact_email'=>'test-contact@example.com',
   'phone'=>'+201009998888','address'=>'القاهرة - العنوان','notes'=>'عميل يتحدث العربية'
]);
$profile=customer_accounts_profile(5);
test_customer($profile['phone']==='+201009998888' &&
    $profile['contact_name']==='الشخص المسؤول' &&
    account(5)['name']==='صيدلية اختبار محدثة','profile persisted');
$denied=false;
try{customer_accounts_update($foreign,5,['name'=>'محاولة خاطئة']);}catch(InvalidArgumentException){$denied=true;}
test_customer($denied,'foreign reseller cannot modify profile');
$denied=false;
try{customer_accounts_update($reseller,5,['name'=>'','phone'=>'x']);}catch(InvalidArgumentException){$denied=true;}
test_customer($denied,'blank name rejected');
$login=one("SELECT u.id,u.email FROM memberships m JOIN users u ON u.id=m.user_id
   WHERE m.account_id=? AND m.role='owner'",[5]);
test_customer($login!==null,'owner exists');
customer_accounts_change_login($reseller,5,'new-customer-owner@ontrack.test');
$changed=one('SELECT email FROM users WHERE id=?',[$login['id']]);
test_customer($changed['email']==='new-customer-owner@ontrack.test','owner login changed');
$denied=false;
try{customer_accounts_change_login($foreign,5,'attack@example.invalid');}
catch(InvalidArgumentException){$denied=true;}
test_customer($denied,'owner login tenant isolation');
$denied=false;
try{customer_accounts_change_login($reseller,5,'admin@ontrackegy.com');}
catch(InvalidArgumentException){$denied=true;}
test_customer($denied,'existing owner email unique');
customer_accounts_status($reseller,5,'suspended');
test_customer(account(5)['status']==='suspended' && site_limit(5,'call_quota')===0,'suspend blocks entitlement');
customer_accounts_status($reseller,5,'active');
test_customer(account(5)['status']==='active' && site_limit(5,'call_quota')>0,'reactivation restores entitlement');
// A reseller suspension must pause descendants without erasing their paid
// subscriptions, account state, or manually suspending children.
$childSubscription=one("SELECT id,ends_at,status FROM subscriptions
    WHERE account_id=5 AND status='active' ORDER BY id DESC LIMIT 1");
test_customer($childSubscription!==null && tenant_voice_entitled(['account_id'=>5]),
    'precondition: paid child has valid live voice entitlement');
customer_accounts_status($admin,2,'suspended');
test_customer(account(2)['status']==='suspended' &&
    account(5)['status']==='active' && site_limit(5,'call_quota')===0,
    'suspending reseller pauses active child without rewriting child status');
test_customer(!tenant_voice_entitled(['account_id'=>5]),
    'suspended reseller blocks child SIM voice session');
$denied=false;
try{customer_accounts_status($reseller,5,'active');}catch(InvalidArgumentException){$denied=true;}
test_customer($denied,'cannot bypass inactive parent by reactivating child');
test_customer(one('SELECT id,ends_at,status FROM subscriptions WHERE id=?',
    [$childSubscription['id']])===$childSubscription,
    'parent suspension preserves paid child subscription row');
customer_accounts_status($admin,2,'active');
test_customer(site_limit(5,'call_quota')>0 &&
    tenant_voice_entitled(['account_id'=>5]),
    'reseller restoration resumes only still eligible child services');
$archive=customer_accounts_delete($reseller,5);
test_customer($archive==='archived' && account(5)['status']==='archived',
    'used customer is archived, not deleted');
test_customer(site_limit(5,'call_quota')===0,'archived customer loses entitlement');
test_customer((int)one('SELECT COUNT(*) n FROM subscriptions WHERE account_id=?',[5])['n']>=1,
    'subscriptions not erased on archive');
$denied=false;
try{customer_accounts_status($reseller,5,'active');}catch(InvalidArgumentException){$denied=true;}
test_customer($denied,'archived accounts cannot silently reactivate');
customer_accounts_restore($reseller,5);
test_customer(account(5)['status']==='suspended','restore defaults suspended');
customer_accounts_status($reseller,5,'active');
test_customer(account(5)['status']==='active','explicit reactivation works');
$denied=false;
try{customer_accounts_delete($reseller,7);}catch(InvalidArgumentException){$denied=true;}
test_customer($denied,'cannot delete foreign reseller customer');
// Newly created empty customer can be deleted completely while audit and users stay intact.
$newId=insert("INSERT INTO accounts(kind,parent_id,name,slug,status,created_at)
  VALUES ('customer',?,'Disposable user',?,'active',?)",[2,'delete-fixture-'.bin2hex(random_bytes(3)),now()]);
$loginId=insert("INSERT INTO users(name,email,password_hash,status,created_at)
  VALUES (?,?,?,'active',?)",['Disposable Owner','purge-fixture-'.bin2hex(random_bytes(4)).'@example.invalid',
     hash_password('temporary-fixture-unused-password'),now()]);
insert("INSERT INTO memberships(user_id,account_id,role,created_at) VALUES (?,?,'owner',?)",
    [$loginId,$newId,now()]);
customer_accounts_update($reseller,$newId,[
   'name'=>'Disposable user','contact_name'=>'Contact','contact_email'=>'','phone'=>'',
   'address'=>'','notes'=>''
]);
test_customer(customer_accounts_delete($reseller,$newId)==='deleted','empty customer is permanently deleted');
test_customer(account($newId)===null,'empty account row removed');
test_customer((int)one('SELECT COUNT(*) n FROM memberships WHERE account_id=?',[$newId])['n']===0,
    'deleted account cannot log in');
test_customer((string)one('SELECT status FROM users WHERE id=?',[$loginId])['status']==='suspended',
    'orphaned owner login disabled');
test_customer(customer_accounts_metrics(5)['counts']['subscriptions']>=1,
    'customer summary preserves subscription history');
echo "CUSTOMER_V59_SCOPE_PROFILE_LOGIN_STATUS_ARCHIVE_DELETE_RESTORE_PASS\n";
