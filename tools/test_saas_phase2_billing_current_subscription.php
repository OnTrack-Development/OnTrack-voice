<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
if(is_dir('/home/appontrack') && !getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('saas_phase2_fixture_only');
function billing2_ok(bool $condition,string $why):void {
    if(!$condition)throw new RuntimeException('SAAS_PHASE2_BILLING_FAIL '.$why);
}
seed_console();
$id=4;
$valid=one("SELECT s.id,s.plan_id,p.name FROM subscriptions s
    JOIN plans p ON p.id=s.plan_id WHERE s.account_id=? AND s.status='active'
    AND s.starts_at<=? AND s.ends_at>? ORDER BY s.id DESC LIMIT 1",[$id,now(),now()]);
billing2_ok($valid!==null,'seed customer has a valid current subscription');
$oldId=(int)$valid['id'];
$history=insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at)
    VALUES (?,?,'replaced',?,?)",[$id,(int)$valid['plan_id'],now(),
    gmdate('Y-m-d H:i:s',time()+31*86400)]);
billing2_ok($history>$oldId,'historical subscription is newest row');
$future=insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at)
    VALUES (?,?,'active',?,?)",[$id,(int)$valid['plan_id'],
    gmdate('Y-m-d H:i:s',time()+86400),gmdate('Y-m-d H:i:s',time()+32*86400)]);
billing2_ok($future>$history,'a future renewal does not preempt current plan');
ob_start();
render_billing(['account_id'=>$id,'kind'=>'customer','role'=>'viewer']);
$html=(string)ob_get_clean();
billing2_ok(str_contains($html,htmlspecialchars((string)$valid['name'],ENT_QUOTES,'UTF-8')),
    'billing shows existing current plan despite newer history');
billing2_ok(!str_contains($html,'لا توجد باقة نشطة'),
    'billing never falsely claims no plan when one is active');
$summary=voice_usage_summary($id);
billing2_ok($summary['active']===true,'usage agrees with active plan shown');
execute("UPDATE subscriptions SET status='replaced' WHERE id=?",[$oldId]);
ob_start();
render_billing(['account_id'=>$id,'kind'=>'customer','role'=>'viewer']);
$noActive=(string)ob_get_clean();
billing2_ok(str_contains($noActive,'لا توجد باقة نشطة'),
    'inactive history and future plan do not grant current entitlement');
billing2_ok(voice_usage_summary($id)['active']===false,
    'usage cannot count future subscription as currently active');
echo "SAAS_PHASE2_ACTIVE_SUBSCRIPTION_DISPLAY_AND_USAGE_PASS\n";
