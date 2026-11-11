<?php
declare(strict_types=1);
require __DIR__.'/../saas/console/database/Seed.php';
require __DIR__.'/../saas/console/_core/BillingUsage.php';
require __DIR__.'/../saas/console/_core/SubscriptionPlans.php';
// GitHub isolated fixtures only. Never use a production account or call.
if(PHP_SAPI!=='cli'||!preg_match('/^saasall_[0-9_]+$/D',(string)(getenv('ONTRACK_CI_NAMESPACE')?:'')))
    throw new RuntimeException('isolated_usage_fixture_only');
function usage_check(bool $ok,string $reason):void {
    if(!$ok)throw new RuntimeException('USAGE_PERIOD_FAIL: '.$reason);
}
function usage_fixture_call(int $account,int $site,int $binding,int $slot,string $status,int $seconds,int $demo,string $created):string {
    $raw=bin2hex(random_bytes(16));
    $call=substr($raw,0,8).'-'.substr($raw,8,4).'-4'.substr($raw,13,3).'-9'.
          substr($raw,17,3).'-'.substr($raw,20,12);
    insert("INSERT INTO calls(account_id,site_id,device_binding_id,sim_slot,external_call_id,
        phone,name,duration_seconds,status,is_demo,created_at)
        VALUES(?,?,?,?,?,'','',?,?,?,?)",
        [$account,$site,$binding,$slot,$call,$seconds,$status,$demo,$created]);
    return $call;
}
seed_console();
$account=4;$site=1;$slot=0;$agentId=(int)one('SELECT id FROM agents WHERE account_id=4 AND site_id=1 LIMIT 1')['id'];
$binding=insert("INSERT INTO device_bindings
 (account_id,site_id,agent_id,installation_id,sim_slot,slot_claim,device_name,token_hash,status,created_at)
 VALUES (?,?,?,?,?,?,?,?,'active',?)",
 [$account,$site,$agentId,'usage-period-ci-fixture-00001',$slot,
 hash('sha256','usage-period-ci-fixture-00001|0'),'CI synthetic SIM',
 hash('sha256','fake-test-token-do-not-use'),now()]);
$b=['id'=>$binding,'account_id'=>$account,'site_id'=>$site,'sim_slot'=>$slot];
// Align the seeded 30-day billing window before simulating an early
// customer plan switch; all timestamps are synthetic and UTC.
$periodStart=gmdate('Y-m-d H:i:s',time()-3600);
$periodEnd=gmdate('Y-m-d H:i:s',time()-3600+30*86400);
$at=gmdate('Y-m-d H:i:s',time()-120);
execute("UPDATE subscriptions SET starts_at=?,ends_at=?
    WHERE account_id=? AND status='active'",[$periodStart,$periodEnd,$account]);
// Deterministic boundary probes: two early paid renewals do not reset quota
// until the prior 30-day cycle expires; an initial 15-day trial is clamped.
usage_check(voice_usage_cycle_start('2026-03-02 00:00:00',
    '2026-01-01 00:00:00',strtotime('2026-01-15 12:00:00 UTC'))==='2026-01-01 00:00:00',
    'paid early renewal retains original cycle until previous expiry');
usage_check(voice_usage_cycle_start('2026-03-02 00:00:00',
    '2026-01-01 00:00:00',strtotime('2026-01-31 00:00:00 UTC'))==='2026-01-31 00:00:00',
    'metering quota resets only when next 30-day term begins');
usage_check(voice_usage_cycle_start('2026-04-01 00:00:00',
    '2026-01-01 00:00:00',strtotime('2026-02-15 00:00:00 UTC'))==='2026-01-31 00:00:00',
    'second early renewal cannot create third free cycle');
usage_check(voice_usage_cycle_start('2026-02-15 00:00:00',
    '2026-01-01 00:00:00',strtotime('2026-01-10 00:00:00 UTC'))==='2026-01-01 00:00:00',
    'short first term cannot count events earlier than actual subscription start');
$summary=voice_usage_summary($account);
usage_check($summary['active']===true,'seed customer subscribed');
$baseline=$summary['used'];
$answered=usage_fixture_call($account,$site,$binding,$slot,'active',0,0,$at);
// A forged/orphan active callback cannot plant evidence for a future call.
$orphan='11111111-2222-4333-8444-555555555555';
usage_check(!voice_usage_update($b,$orphan,'active'),'orphan active callback rejected');
usage_check(!one('SELECT call_id FROM voice_call_answer_evidence WHERE device_binding_id=? AND call_id=?',
    [$binding,$orphan]),'orphan callback must not persist answer evidence');
usage_check(!voice_usage_update($b,$answered,'active'),'active alone never charges');
execute("UPDATE calls SET status='ended', duration_seconds=19 WHERE external_call_id=?",[$answered]);
usage_check(voice_usage_update($b,$answered,'ended'),'ended with answer evidence charges once');
usage_check(!voice_usage_update($b,$answered,'ended'),'duplicate terminal event must not charge');
usage_check(!voice_usage_update($b,$answered,'active'),'late answered callback must not double bill');
$ref='voice-'.hash('sha256',"$account:$site:$binding:$slot:$answered");
$ledger=one('SELECT created_at,units FROM usage_ledger WHERE reference=?',[$ref]);
usage_check((int)$ledger['units']===1&&$ledger['created_at']===$at,'ledger uses call start as usage period');
usage_check(voice_usage_summary($account)['used']===$baseline+1,'summary reconciles exactly one call');
$missed=usage_fixture_call($account,$site,$binding,$slot,'missed',0,0,$at);
usage_check(!voice_usage_update($b,$missed,'ended'),'missed never charges');
$demo=usage_fixture_call($account,$site,$binding,$slot,'ended',22,1,$at);
voice_usage_update($b,$demo,'active');
usage_check(!voice_usage_update($b,$demo,'ended'),'demo never charges');
$noAnswer=usage_fixture_call($account,$site,$binding,$slot,'ended',22,0,$at);
usage_check(!voice_usage_update($b,$noAnswer,'ended'),'ended without answer evidence must not charge');
$historic=usage_fixture_call($account,$site,$binding,$slot,'ended',22,0,'2020-01-01 00:00:00');
voice_usage_update($b,$historic,'active');
usage_check(!voice_usage_update($b,$historic,'ended'),'old unlicensed calls cannot be charged retroactively');
$reordered=usage_fixture_call($account,$site,$binding,$slot,'ended',11,0,$at);
usage_check(!voice_usage_update($b,$reordered,'ended'),'ended first does not bill without evidence');
usage_check(voice_usage_update($b,$reordered,'active'),'late verified active bills ended call once');
usage_check(!voice_usage_update($b,$reordered,'ended'),'duplicate reordered events still exact once');
usage_check(voice_usage_summary($account)['used']===$baseline+2,'all excluded events remain excluded');
$foreign=['id'=>$binding,'account_id'=>5,'site_id'=>3,'sim_slot'=>1];
usage_check(!voice_usage_update($foreign,$answered,'ended'),'foreign scope cannot charge');
// Switching a plan mid-term must not reset the used count. A terminal
// callback delivered later over cellular belongs to the previous paid term.
$oldSub=one("SELECT s.id,s.plan_id,s.ends_at FROM subscriptions s
    WHERE s.account_id=? AND s.status='active' ORDER BY s.id DESC LIMIT 1",[$account]);
$plan=one("SELECT id FROM plans WHERE kind='user' AND status='active' AND id!=?
    ORDER BY id ASC LIMIT 1",[(int)$oldSub['plan_id']]);
usage_check((bool)$plan,'second compatible plan available');
$delayed=usage_fixture_call($account,$site,$binding,$slot,'active',0,0,$at);
usage_check(!voice_usage_update($b,$delayed,'active'),'pending old call not yet charged');
$assigned=subscription_assign_demo($account,(int)$plan['id']);
usage_check($assigned['changed']===true,'same-period plan reassignment applies');
usage_check(one("SELECT status FROM subscriptions WHERE id=?",[$oldSub['id']])['status']==='replaced',
    'old plan retained as historical replaced evidence');
$carried=voice_usage_summary($account);
usage_check($carried['used']===$baseline+2,'same term plan change cannot reset used quota');
execute("UPDATE calls SET status='ended',duration_seconds=17 WHERE external_call_id=?",[$delayed]);
usage_check(voice_usage_update($b,$delayed,'ended'),
    'delayed callback remains billable against historical replaced plan');
usage_check(!voice_usage_update($b,$delayed,'ended'),
    'historical delayed callback is idempotent');
$after=voice_usage_summary($account);
usage_check($after['used']===$baseline+3 &&
    $after['remaining']===max(0,$after['quota']-$after['used']),
    'quota across replaced subscription reconciles and remaining is correct');
// Match the existing *verified checkout* persistence contract exactly, but
// without gateway requests, charges or external callbacks. An early paid
// renewal extends the end by 30 days, and replaces the active row immediately.
$preRenewal=one("SELECT plan_id,ends_at FROM subscriptions WHERE account_id=?
    AND status='active' ORDER BY id DESC LIMIT 1",[$account]);
$paidEnd=gmdate('Y-m-d H:i:s',strtotime((string)$preRenewal['ends_at'].' UTC')+30*86400);
execute("UPDATE subscriptions SET status='replaced'
    WHERE account_id=? AND status='active'",[$account]);
insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at)
    VALUES (?,?,'active',?,?)",[$account,$preRenewal['plan_id'],now(),$paidEnd]);
$early=voice_usage_summary($account);
usage_check($early['used']===$baseline+3,
    'early paid renewal cannot reset monthly used calls before old cycle expiry');
$secondEnd=gmdate('Y-m-d H:i:s',strtotime($paidEnd.' UTC')+30*86400);
execute("UPDATE subscriptions SET status='replaced'
    WHERE account_id=? AND status='active'",[$account]);
insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at)
    VALUES (?,?,'active',?,?)",[$account,$preRenewal['plan_id'],now(),$secondEnd]);
$twice=voice_usage_summary($account);
usage_check($twice['used']===$baseline+3 &&
    $twice['remaining']===max(0,$twice['quota']-$twice['used']),
    'multiple early renewals cannot grant quota repeatedly in current term');
// Overages remain informational until a verified settlement; never
// auto-charge on an out-of-order cellular callback.
$activePlan=one("SELECT plan_id FROM subscriptions WHERE account_id=?
    AND status='active' ORDER BY id DESC LIMIT 1",[$account]);
$limit=max(0,(int)$twice['used']-1);
execute("UPDATE plans SET call_quota=?,overage_minor=? WHERE id=?",
    [$limit,125,(int)$activePlan['plan_id']]);
$excess=voice_usage_summary($account);
usage_check($excess['used']===$twice['used'] &&
    $excess['overage']===1 && $excess['remaining']===0 &&
    $excess['estimate_minor']===125,
    'overage amounts reconcile in minor units without automatic billing');
usage_check(!one("SELECT id FROM usage_ledger WHERE account_id=?
    AND kind='overage_charge'",[$account]),
    'overage is not collected automatically from the voice callback');
echo "SAAS_USAGE_PERIOD_EXACT_ONCE_NO_ANSWER_DEMO_REORDERED_CROSS_TENANT_PASS\n";
