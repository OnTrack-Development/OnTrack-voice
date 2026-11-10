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
// Keep the call's timestamp clearly earlier than the later plan switch.
$at=gmdate('Y-m-d H:i:s',time()-120);
execute("UPDATE subscriptions SET starts_at=? WHERE account_id=? AND status='active'",
    [gmdate('Y-m-d H:i:s',time()-3600),$account]);
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
echo "SAAS_USAGE_PERIOD_EXACT_ONCE_NO_ANSWER_DEMO_REORDERED_CROSS_TENANT_PASS\n";
