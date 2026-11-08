<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||is_dir('/home/appontrack'))
    throw new RuntimeException('CI_ONLY: never touch hosted customer subscriptions');
if(!getenv('ONTRACK_CI_NAMESPACE'))
    putenv('ONTRACK_CI_NAMESPACE=plans'.bin2hex(random_bytes(8)));
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/SubscriptionPlans.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
seed_console();
function pcheck(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('PLAN_GRANT_TEST_FAIL: '.$label);
}
$_SESSION=['uid'=>(int)one("SELECT id FROM users WHERE email='admin@ontrackegy.com'")['id'],
    'account_id'=>1,'console_view'=>'admin'];
$platform=mine();
pcheck($platform['kind']==='platform','platform owner fixture');
$reseller=account(2);
$customer=account(5);
$freeReseller=one("SELECT * FROM plans WHERE kind='reseller' AND status='active' ORDER BY id LIMIT 1");
$freeUser=one("SELECT * FROM plans WHERE kind='user' AND status='active' ORDER BY id LIMIT 1");
pcheck(subscription_admin_grant_allowed($platform,$reseller,$freeReseller),'admin can grant reseller plan');
pcheck(subscription_admin_grant_allowed($platform,$customer,$freeUser),'admin can grant customer plan');
pcheck(!subscription_admin_grant_allowed($platform,$reseller,$freeUser),'cross kind reseller/user forbidden');
pcheck(!subscription_admin_grant_allowed($platform,$customer,$freeReseller),'cross kind customer/reseller forbidden');
pcheck(!subscription_admin_grant_allowed($platform,$reseller,$freeReseller,true),'support impersonation forbidden');
pcheck(!subscription_admin_grant_allowed($platform,$reseller,$freeReseller,false,'user'),'admin in own user view forbidden');
$nonAdmin=$platform;$nonAdmin['role']='viewer';
pcheck(!subscription_admin_grant_allowed($nonAdmin,$reseller,$freeReseller),'non-admin forbidden');
$pricedReseller=insert("INSERT INTO plans(kind,name,call_quota,site_limit,agent_limit,staff_limit,reseller_customers,price_minor,status,created_at) VALUES ('reseller','Paid reseller fixture',12000,10,20,5,10,29900,'active',?)",[now()]);
$pricedUser=insert("INSERT INTO plans(kind,name,call_quota,site_limit,agent_limit,staff_limit,reseller_customers,price_minor,status,created_at) VALUES ('user','Paid user fixture',1500,2,3,2,0,4900,'active',?)",[now()]);
$rp=one('SELECT * FROM plans WHERE id=?',[$pricedReseller]);
$up=one('SELECT * FROM plans WHERE id=?',[$pricedUser]);
pcheck(subscription_admin_grant_allowed($platform,$reseller,$rp),'admin can grant priced reseller plan without charging');
pcheck(subscription_admin_grant_allowed($platform,$customer,$up),'admin can grant priced customer plan without charging');
pcheck(!subscription_self_free_allowed($reseller,$rp),'priced reseller cannot self-activate');
$ownedReseller=$reseller;$ownedReseller['role']='owner';
pcheck(subscription_self_free_allowed($ownedReseller,$freeReseller),'reseller owner can choose free plan');
pcheck(!subscription_self_free_allowed($ownedReseller,$rp),'reseller paid plan cannot bypass checkout');
pcheck(!subscription_self_free_allowed($ownedReseller,$freeUser),'reseller cannot choose customer plan');
pcheck(!subscription_self_free_allowed($ownedReseller,$freeReseller,true),'support cannot self activate');
$before=one("SELECT s.id,s.ends_at FROM subscriptions s WHERE s.account_id=2 AND s.status='active' AND s.starts_at<=? AND s.ends_at>? ORDER BY s.id DESC LIMIT 1",[now(),now()]);
pcheck((bool)$before,'reseller starts on active fixture');
$ordersBefore=(int)one('SELECT COUNT(*) n FROM orders')['n'];
$changed=subscription_assign_demo(2,(int)$rp['id']);
pcheck($changed['changed'],'admin grant changes reseller plan');
pcheck((int)site_limit(2,'call_quota')===12000,'reseller entitlement effective immediately');
$new=one('SELECT plan_id,status,ends_at FROM subscriptions WHERE id=?',[$changed['subscription_id']]);
pcheck((int)$new['plan_id']===(int)$rp['id']&&$new['status']==='active','new reseller plan activated');
pcheck($new['ends_at']===$before['ends_at'],'paid or free remaining period preserved on change');
$repeated=subscription_assign_demo(2,(int)$rp['id']);
pcheck(!$repeated['changed']&&$repeated['subscription_id']===$changed['subscription_id'],'repeat request idempotent');
pcheck((int)one('SELECT COUNT(*) n FROM orders')['n']===$ordersBefore,'administrative grant creates no paid orders or fake receipts');
// New reseller without subscription receives a 30 day manual trial/grant.
$newReseller=insert("INSERT INTO accounts(kind,parent_id,name,slug,status,brand_name,created_at) VALUES ('reseller',NULL,'New empty reseller','new-reseller-plan-test','active','New empty reseller',?)",[now()]);
pcheck(site_limit($newReseller,'call_quota')===0,'new reseller initially has zero quota');
$created=subscription_assign_demo($newReseller,(int)$rp['id']);
pcheck($created['changed']&&site_limit($newReseller,'call_quota')===12000,'new reseller admin grant works without payment');
pcheck((int)one('SELECT COUNT(*) n FROM orders')['n']===$ordersBefore,'new grant still no payment record');
ob_start();render_accounts($platform);$accounts=(string)ob_get_clean();
pcheck(str_contains($accounts,"name=\"action\" value=\"admin_grant_plan\""),'per-account admin grant form exists');
pcheck(str_contains($accounts,'تعيين / تغيير الباقة الآن'),'inline account plan editor visible');
pcheck(str_contains($accounts,'confirm_admin_grant'),'grant requires positive confirmation');
pcheck(str_contains($accounts,'Paid reseller fixture'),'reseller plan listed for admin selection');
$_SESSION['console_view']='user';
ob_start();render_accounts($platform);$preview=(string)ob_get_clean();
pcheck(!str_contains($preview,'admin_grant_plan'),'grant hidden from user presentation');
$_SESSION=['uid'=>(int)one("SELECT id FROM users WHERE email='reseller@delta.test'")['id'],
    'account_id'=>2,'console_view'=>'admin'];
$a=mine();
ob_start();render_billing($a);$billing=(string)ob_get_clean();
pcheck(str_contains($billing,'Paid reseller fixture'),'reseller may select paid plan through checkout');
pcheck(str_contains($billing,'اختيار باقة مجانية وتفعيلها الآن'),'reseller sees free plan selection');
pcheck(str_contains($billing,'self_select_free_plan'),'free plan has own action');
pcheck(str_contains($billing,'checkout_plan'),'paid plan uses verified payment');
pcheck(!str_contains($billing,'admin_grant_plan'),'reseller cannot act as platform administrator');
echo "ADMIN_DIRECT_RESELLER_AND_CUSTOMER_PLAN_GRANTS_PASS\n";
