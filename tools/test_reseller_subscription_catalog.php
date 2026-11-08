<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/SubscriptionPlans.php';
require_once __DIR__.'/../saas/console/_core/TenantVoiceSession.php';
if(is_dir('/home/appontrack')&&!getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('Isolated CI database only');
function ccheck(bool $v,string $msg):void {if(!$v)throw new RuntimeException('PLAN_CATALOG_FAIL '.$msg);}
seed_console();
$original=one("SELECT id FROM plans WHERE kind='user' AND status='active' ORDER BY id LIMIT 1");
ccheck((bool)$original,'global seed plan');
$fields=['name'=>'خاصة دلتا','call_quota'=>350,'site_limit'=>2,'agent_limit'=>2,
    'staff_limit'=>1,'reseller_customers'=>0,'price_minor'=>12000,
    'overage_minor'=>10,'google_key_limit'=>1,'assistant_key_limit'=>1];
$p=plan_catalog_save_reseller(2,$fields);
ccheck($p>0&&plan_catalog_owner($p)===2,'reseller owns its copy');
$plan=one('SELECT * FROM plans WHERE id=?',[$p]);
ccheck(plan_catalog_allowed($plan,account(5)),'parent child sees plan');
ccheck(!plan_catalog_allowed($plan,account(7)),'other reseller child blocked');
ccheck(!plan_catalog_allowed($plan,account(4)),'direct customer blocked');
ccheck(count(plan_catalog_reseller_owned(2))===1,'private list');
ccheck(count(plan_catalog_reseller_owned(3))===0,'other reseller isolated');
$blocked=false;
try{plan_catalog_save_reseller(3,$fields,$p);}catch(InvalidArgumentException){$blocked=true;}
ccheck($blocked,'cross reseller edit rejected');
$blocked=false;
try{subscription_assign_demo(7,$p);}catch(InvalidArgumentException){$blocked=true;}
ccheck($blocked,'cross reseller subscription rejected');
$blocked=false;
try{plan_catalog_dispose($p,3,'delete');}catch(InvalidArgumentException){$blocked=true;}
ccheck($blocked,'cross reseller delete rejected');
$fields['price_minor']=9900;
$before=plan_catalog_save_reseller(2,$fields,$p);
ccheck($before===$p&&(int)one('SELECT price_minor FROM plans WHERE id=?',[$p])['price_minor']===9900,
    'unreferenced in-place edit saves');
$grant=subscription_assign_demo(5,$p);
ccheck($grant['changed']&&$grant['subscription_id']>0,'own reseller child can activate');
$fields['name']='خاصة دلتا — معدلة';
$fields['price_minor']=15000;
$next=plan_catalog_save_reseller(2,$fields,$p);
ccheck($next!==$p,'used plan edit creates immutable new version');
ccheck((string)one('SELECT status FROM plans WHERE id=?',[$p])['status']==='archived',
    'prior edition archived');
ccheck((int)one('SELECT price_minor FROM plans WHERE id=?',[$p])['price_minor']===9900,
    'historical subscriber price not rewritten');
ccheck((int)one('SELECT plan_id FROM subscriptions WHERE id=?',[$grant['subscription_id']])['plan_id']===$p,
    'old subscription continues with original plan');

// A reseller editing an already-sold plan archives the catalog VERSION,
// not the active customer's paid entitlement. Exercise the real voice gate
// rather than checking only that subscription rows survived the edit.
$voiceBinding=['account_id'=>5];
ccheck(tenant_voice_entitled($voiceBinding),
    'archived historical plan still grants active subscribed SIM voice access');
ccheck(site_limit(5,'call_quota')===350,
    'archived plan keeps its sold call quota and tenant limits');
$activeUntil=(string)one('SELECT ends_at FROM subscriptions WHERE id=?',[$grant['subscription_id']])['ends_at'];
execute('UPDATE subscriptions SET ends_at=? WHERE id=?',
    [gmdate('Y-m-d H:i:s',time()-60),$grant['subscription_id']]);
ccheck(!tenant_voice_entitled($voiceBinding),
    'expired historical subscription never authorizes fresh voice sessions');
execute('UPDATE subscriptions SET ends_at=? WHERE id=?',[$activeUntil,$grant['subscription_id']]);
execute("UPDATE subscriptions SET status='cancelled' WHERE id=?",[$grant['subscription_id']]);
ccheck(!tenant_voice_entitled($voiceBinding),
    'cancelled historical subscription never authorizes fresh voice sessions');
execute("UPDATE subscriptions SET status='active' WHERE id=?",[$grant['subscription_id']]);
execute("UPDATE accounts SET status='suspended' WHERE id=5");
ccheck(!tenant_voice_entitled($voiceBinding),
    'suspended account cannot use historical subscription');
execute("UPDATE accounts SET status='active' WHERE id=5");
ccheck(tenant_voice_entitled($voiceBinding),
    'restored eligible account can use unexpired historical plan');

$duplicate=plan_catalog_save_reseller(2,$fields,$next);
ccheck($duplicate===$next,'same submission idempotent');
ccheck(plan_catalog_dispose($next,2,'delete')==='deleted','unused plan can be deleted');
ccheck(plan_catalog_dispose($p,2,'delete')==='archived','historical plan cannot be hard-deleted');
ccheck(plan_catalog_dispose($p,2,'restore')==='restored','reseller can restore plan');
$quote=subscription_change_quote(['price_minor'=>9900,'ends_at'=>gmdate('Y-m-d H:i:s',time()+7*86400)],
    ['price_minor'=>15000]);
ccheck($quote['direction']==='upgrade'&&$quote['full_period_difference_minor']===5100
    &&$quote['remaining_days']>=6
    &&$quote['estimated_remaining_difference_minor']>0
    &&$quote['estimated_remaining_difference_minor']<=5100,
    'upgrade quote, remaining time and illustrative prorated amount');
ccheck(subscription_schedule_end(5),'schedule cancellation at term');
ccheck(subscription_scheduled_end(5,$grant['subscription_id'])!==null,'intent stored');
ccheck(subscription_unschedule_end(5),'schedule revocable');
ccheck(subscription_scheduled_end(5,$grant['subscription_id'])===null,'intent deleted');
ccheck(subscription_cancel_now(5),'cancellation revokes entitlement');
ccheck(site_limit(5,'call_quota')===0,'cancelled subscription no quota');
$renew=subscription_renew_free(5,(int)$original['id']);
ccheck($renew['changed']&&$renew['subscription_id']>0,'free renewal activates');
$replayed=subscription_renew_free(5,(int)$original['id']);
ccheck(!$replayed['changed']&&$replayed['subscription_id']===$renew['subscription_id'],
    'no infinite free renewal extension');
ccheck(site_limit(5,'call_quota')>0,'renewal restores limits');
echo "PLAN_RESELLER_CATALOG_AND_SUBSCRIPTION_LIFECYCLE_PASS\n";
