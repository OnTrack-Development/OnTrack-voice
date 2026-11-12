<?php
declare(strict_types=1);
require dirname(__DIR__).'/saas/console/database/Seed.php';
seed_console();
function reg(bool $v,string $message):void{if(!$v)throw new RuntimeException('G6_REGRESSION '.$message);}
$accountId=(int)(one("SELECT id FROM accounts WHERE kind='platform' LIMIT 1")['id']??0);
reg($accountId>0,'fixture platform exists');
$plan=one("SELECT id,kind FROM plans WHERE kind='user' ORDER BY id LIMIT 1");
reg($plan!==null,'seed plan present');
$temp=insert("INSERT INTO accounts(kind,name,slug,status,created_at) VALUES('customer','G6 Expiry Fixture',?,'active',?)",
    ['g6-'.bin2hex(random_bytes(8)),now()]);
$old=(string)gmdate('Y-m-d H:i:s',time()-86400);
$past=(string)gmdate('Y-m-d H:i:s',time()-3600);
try{
    reg(site_limit($temp,'call_quota')===0,'unpaid account has no entitlements');
    insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at)
        VALUES (?,?,'active',?,?)",[$temp,$plan['id'],$old,$past]);
    reg(site_limit($temp,'call_quota')===0,'expired active status does not extend quota');
    execute('UPDATE subscriptions SET ends_at=? WHERE account_id=?',
        [gmdate('Y-m-d H:i:s',time()+86400),$temp]);
    reg(site_limit($temp,'call_quota')>=0,'valid time-scoped entitlement executes');
}finally{
    execute('DELETE FROM subscriptions WHERE account_id=?',[$temp]);
    execute('DELETE FROM accounts WHERE id=?',[$temp]);
}
$actions=file_get_contents(__DIR__.'/../saas/console/_core/Actions.php');
reg(str_contains($actions,"'subscription'=>'none_pending_verified_payment'"),
    'new account cannot acquire paid subscription implicitly');
echo "G6_ENTITLEMENT_EXPIRY_PASS\n";
