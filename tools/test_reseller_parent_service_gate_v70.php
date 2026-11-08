<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/CustomerAccounts.php';
require_once __DIR__.'/../saas/console/_core/DevicePairing.php';
require_once __DIR__.'/../saas/console/_core/TenantVoiceSession.php';
if(is_dir('/home/appontrack') && !getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('v70_requires_isolated_database');
function v70_check(bool $ok,string $reason):void {
    if(!$ok)throw new RuntimeException('V70_RESELLER_GATE_FAILED '.$reason);
}
seed_console();
$admin=['kind'=>'platform','account_id'=>1,'role'=>'owner'];
$reseller=['kind'=>'reseller','account_id'=>2,'role'=>'owner'];
$child=account(5);
$site=one("SELECT id FROM sites WHERE account_id=5 AND status='active' ORDER BY id LIMIT 1");
$agent=$site?one("SELECT id FROM agents WHERE account_id=5 AND site_id=? AND status!='archived' ORDER BY id LIMIT 1",[$site['id']]):null;
$actor=one("SELECT user_id FROM memberships WHERE account_id=5 AND role='owner' LIMIT 1");
v70_check($child!==null && $site!==null && $agent!==null && $actor!==null,
    'fixture must have a reseller child, site, agent and owner');
$childId=(int)$child['id'];
$siteId=(int)$site['id'];
$agentId=(int)$agent['id'];
$userId=(int)$actor['user_id'];
v70_check(account_service_active($child) && site_limit($childId,'call_quota')>0 &&
    tenant_voice_entitled(['account_id'=>$childId]),'paid child initially active');
$before=one("SELECT id,status,starts_at,ends_at FROM subscriptions
    WHERE account_id=? AND status='active' ORDER BY id DESC LIMIT 1",[$childId]);
v70_check($before!==null,'valid existing paid subscription');
$code=device_pairing_create($childId,$siteId,$agentId,$userId,0);
$binding=device_pairing_redeem($code,'v70-suspension-fixture-device-001',0,'Fixture SIM');
$token=(string)$binding['device_token'];
v70_check(device_binding_authorize($token)!==null,'active child bearer accepted');
$stale=device_pairing_create($childId,$siteId,$agentId,$userId,1);
customer_accounts_status($admin,2,'suspended');
v70_check(account(2)['status']==='suspended' && account(5)['status']==='active',
    'parent suspended without mutating child account');
v70_check(!account_service_active(account($childId)) &&
    !tenant_voice_entitled(['account_id'=>$childId]) && site_limit($childId,'call_quota')===0,
    'live voice and metered entitlements denied immediately');
v70_check(device_binding_authorize($token)===null,
    'previously issued active device bearer rejected while reseller suspended');
$blocked=false;
try{device_pairing_create($childId,$siteId,$agentId,$userId,0);}
catch(InvalidArgumentException){$blocked=true;}
v70_check($blocked,'new six-digit pairing denied during reseller suspension');
$blocked=false;
try{device_pairing_redeem($stale,'v70-suspension-fixture-device-002',1,'Fixture SIM 2');}
catch(InvalidArgumentException){$blocked=true;}
v70_check($blocked,'pending six-digit code cannot evade parent suspension');
try{
    device_binding_profile(['account_id'=>$childId,'site_id'=>$siteId,'agent_id'=>$agentId,
        'id'=>$binding['device_id'],'sim_slot'=>0]);
    throw new RuntimeException('V70_RESELLER_GATE_FAILED stale profile returned');
}catch(RuntimeException $e){
    v70_check($e->getMessage()==='device_profile_unavailable',
        'stale cached binding cannot return suspended parent agent profile');
}
$after=one("SELECT id,status,starts_at,ends_at FROM subscriptions WHERE id=?",[$before['id']]);
v70_check($after==$before,'parent suspension did not mutate billing dates or status');
customer_accounts_status($admin,2,'active');
v70_check(account_service_active(account($childId)) &&
    device_binding_authorize($token)!==null &&
    tenant_voice_entitled(['account_id'=>$childId]),
    'parent restoration resumes same signed device bearer and paid term');
customer_accounts_status($reseller,$childId,'suspended');
v70_check(!account_service_active(account($childId)) && device_binding_authorize($token)===null,
    'explicit child suspension overrides active parent');
customer_accounts_status($reseller,$childId,'active');
v70_check(device_binding_authorize($token)!==null,'explicit child reactivation restores valid bearer');
$api=file_get_contents(__DIR__.'/../saas/console/api/v1/index.php');
v70_check(str_contains($api,'if(!account_service_active($a))'),
    'real API bearer handler checks reseller ancestry before read and write');
echo "V70_RESELLER_PARENT_ACCOUNT_API_DEVICE_AND_VOICE_GATE_PASS\n";
