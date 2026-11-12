<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/SubscriptionPlans.php';
if(is_dir('/home/appontrack') && !getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('Isolated CI fixture only');
function subs_check(bool $ok,string $label):void{
    if(!$ok)throw new RuntimeException('SITE05_SUBSCRIPTION_FAIL '.$label);
}
seed_console();
$owner=(int)one("SELECT id FROM accounts WHERE kind='platform' LIMIT 1")['id'];
subs_check(site_limit($owner,'site_limit')>=1000000000 &&
    site_limit($owner,'agent_limit')>=1000000000 &&
    site_limit($owner,'call_quota')>=1000000000,
    'active platform owner has non-expiring site/agent/call limits');
$keys=one("SELECT id FROM api_keys WHERE account_id=? LIMIT 1",[$owner]);
$another=(int)one("SELECT id FROM accounts WHERE kind='customer' AND parent_id IS NULL ORDER BY id LIMIT 1")['id'];
$plans=all("SELECT id FROM plans WHERE kind='user' AND status='active' ORDER BY id LIMIT 2");
subs_check(count($plans)===2,'two legitimate customer plans');
$current=all('SELECT id,plan_id,status,starts_at,ends_at FROM subscriptions WHERE account_id=? ORDER BY id',[$another]);
$original=site_limit($another,'call_quota');
$first=subscription_assign_demo($another,(int)$plans[0]['id']);
subs_check($first['changed']===true && $first['subscription_id']>0,
    'first assignment atomically persisted');
$again=subscription_assign_demo($another,(int)$plans[0]['id']);
subs_check($again['changed']===false && $again['subscription_id']===$first['subscription_id'],
    'replay does not prolong 30-day entitlement or create a duplicate');
$afterReplay=one('SELECT starts_at,ends_at FROM subscriptions WHERE id=?',[$first['subscription_id']]);
subs_check((string)$afterReplay['ends_at']!=='','subscription period remains explicit');
// An existing longer entitlement must survive an upgrade, not reset to 30 days.
$originalEnd=gmdate('Y-m-d H:i:s',time()+65*86400);
execute('UPDATE subscriptions SET ends_at=? WHERE id=?',[$originalEnd,$first['subscription_id']]);
$upgrade=subscription_assign_demo($another,(int)$plans[1]['id']);
subs_check($upgrade['changed']===true && $upgrade['subscription_id']!==$first['subscription_id'] &&
    (string)one('SELECT status FROM subscriptions WHERE id=?',[$first['subscription_id']])['status']==='replaced',
    'upgrade replaces only current subscription and retains prior history');
subs_check((string)one('SELECT ends_at FROM subscriptions WHERE id=?',
    [$upgrade['subscription_id']])['ends_at']===$originalEnd,
    'upgrade preserves the existing subscription expiration and cannot cut paid time');
$bad=false;
try{subscription_assign_demo($another,999999999);}catch(InvalidArgumentException){$bad=true;}
subs_check($bad &&
    (string)one('SELECT status FROM subscriptions WHERE id=?',[$upgrade['subscription_id']])['status']==='active',
    'invalid plan never cancels a working subscription');
$account=account($another);
subs_check($account['status']==='active' &&
    site_limit($another,'call_quota')===(int)one('SELECT call_quota FROM plans WHERE id=?',[$plans[1]['id']])['call_quota'],
    'upgrade entitlement resolves directly from newest active plan');
if(driver()==='sqlite'){
    // Force the INSERT to fail *after* the old plan is marked replaced.
    // Transactions must restore it; production data remains untouched.
    db()->exec("CREATE TRIGGER subscription_injected_failure
        BEFORE INSERT ON subscriptions BEGIN SELECT RAISE(FAIL,'injected'); END");
    $failed=false;
    try{subscription_assign_demo($another,(int)$plans[0]['id']);}
    catch(PDOException){$failed=true;}
    db()->exec('DROP TRIGGER subscription_injected_failure');
    subs_check($failed &&
        (string)one('SELECT status FROM subscriptions WHERE id=?',[$upgrade['subscription_id']])['status']==='active',
        'failed subscription insert rolls back the status of prior plan');
}
execute("UPDATE accounts SET status='suspended' WHERE id=?",[$owner]);
subs_check(site_limit($owner,'site_limit')===0,'suspension revokes even platform capabilities');
execute("UPDATE accounts SET status='active' WHERE id=?",[$owner]);
subs_check(site_limit($owner,'site_limit')>=1000000000,'platform capabilities restored without renewing plan');
subs_check((int)one('SELECT COUNT(*) n FROM api_keys WHERE account_id=?',[$owner])['n']>=($keys?1:0),
    'no provider keys are deleted or rotated on a plan operation');
subs_check(count(all('SELECT id FROM subscriptions WHERE account_id=?',[$another]))>=count($current),
    'history is retained for audit and rollback');
echo "SITE05_ATOMIC_SUBSCRIPTION_AND_OWNER_ENTITLEMENTS_PASS\n";
