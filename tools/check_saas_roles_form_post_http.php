<?php
declare(strict_types=1);
/** Read-only evidence after browser form POSTs in the same disposable namespace. */
$ns=(string)getenv('ONTRACK_CI_NAMESPACE');
if(PHP_SAPI!=='cli'||is_dir('/home/appontrack')||
   getenv('GITHUB_ACTIONS')!=='true'||getenv('CI')!=='true'||
   !preg_match('/^ui_[0-9]+_[0-9]+$/D',$ns))
    throw new RuntimeException('isolated_browser_ci_only');
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/PublishedFlow.php';
function browser_post_ok(bool $ok,string $reason):void{
    if(!$ok)throw new RuntimeException('SAAS_HTTP_FORM_STATE_FAIL '.$reason);
}
browser_post_ok(seed_console()['status']==='already_seeded',
    'browser fixture already present and unchanged');
$active=one("SELECT s.id,s.ends_at,s.plan_id FROM subscriptions s
    WHERE s.account_id=2 AND s.status='active' AND s.starts_at<=? AND s.ends_at>?
    ORDER BY s.id DESC LIMIT 1",[now(),now()]);
$old=one("SELECT ends_at,status FROM subscriptions
    WHERE account_id=2 AND plan_id=1 ORDER BY id DESC LIMIT 1");
browser_post_ok($active!==null&&(int)$active['plan_id']===2,
    'real reseller plan assignment persisted to database');
browser_post_ok($old!==null&&$old['status']==='replaced' &&
    $old['ends_at']===$active['ends_at'],
    'the old reseller subscription history and expiration are preserved');
browser_post_ok((int)one("SELECT COUNT(*) n FROM subscriptions
    WHERE account_id=2 AND status='active'")['n']===1,
    'exactly one reseller subscription remains active');
$policy=answer_policy_for_agent(4,1);
browser_post_ok($policy['auto_answer']===true &&
    (int)$policy['answer_delay_seconds']===5,
    'five-second auto-answer stored by authenticated owner form');
$published=published_agent_flow(4,1,1);
browser_post_ok($published!==null&&(int)$published['revision']>=1,
    'owner did publish a real agent revision');
$p=answer_policy_normalize(json_decode((string)$published['answer_policy_json'],true));
browser_post_ok($p['auto_answer']===true &&
    (int)$p['answer_delay_seconds']===5,
    'five-second auto-answer present in published SIM payload');
$agent=one("SELECT voice,instructions,knowledge FROM agents WHERE id=1 AND account_id=4");
browser_post_ok($agent!==null && $published['voice']===$agent['voice'] &&
    $published['instructions']===$agent['instructions'] &&
    $published['knowledge']===$agent['knowledge'],
    'quick form did not reset voice, instructions or knowledge');
$draft=one("SELECT id,status FROM campaigns WHERE account_id=4 AND site_id=1
    AND name='Browser safety campaign - no calls' ORDER BY id DESC LIMIT 1");
browser_post_ok($draft!==null&&$draft['status']==='draft',
    'real campaign create form saved only draft without dialing');
browser_post_ok((string)one("SELECT status FROM campaigns
    WHERE id=1 AND account_id=4")['status']==='paused',
    'real pause form persisted and unsafe running state was rejected');
browser_post_ok((int)one("SELECT COUNT(*) n FROM campaigns
    WHERE account_id=4 AND status NOT IN ('draft','paused','archived')")['n']===0,
    'no campaign was armed or running through test forms');
browser_post_ok((int)one("SELECT COUNT(*) n FROM calls
    WHERE account_id=4")['n']===2,
    'forms did not create an outbound telephone call');
$contact=one("SELECT id,dnc,name FROM contacts
    WHERE account_id=4 AND phone='+99900000123'");
browser_post_ok($contact!==null && (int)$contact['dnc']===1 &&
    $contact['name']==='Synthetic DNC opt-out',
    'only opted-out consented synthetic contact saved');
browser_post_ok((int)one("SELECT COUNT(*) n FROM contacts
    WHERE account_id=4 AND phone='+99900000123'")['n']===1,
    'unconsented contact was rejected without database write');
$manager=one("SELECT id,status,phone_e164 FROM human_escalation_contacts
    WHERE account_id=4 AND name='Synthetic manager NO DIAL' ORDER BY id DESC LIMIT 1");
browser_post_ok($manager!==null && $manager['status']==='active'
    && $manager['phone_e164']==='+99900000124',
    'manager contact saved by actual owner form with exact synthetic number');
$route=one('SELECT contact_id,enabled,delay_seconds FROM human_escalation_routes
    WHERE account_id=4 AND agent_id=1 ORDER BY id DESC LIMIT 1');
browser_post_ok($route!==null &&
    (int)$route['contact_id']===(int)$manager['id'] &&
    (int)$route['enabled']===0 && (int)$route['delay_seconds']===20,
    'manager route delay persisted and auto-dial remains explicitly disabled');
browser_post_ok((int)one('SELECT COUNT(*) n FROM human_escalation_jobs
    WHERE account_id=4')['n']===0,
    'no manager callback has been scheduled from a settings test');
echo "SAAS_REAL_HTTP_MANAGER_DISABLED_HANDOFF_FORM_PASS\n";
echo "SAAS_REAL_HTTP_CAMPAIGN_DNC_PERSISTED_NO_DIAL_PASS\n";
echo "SAAS_REAL_HTTP_FORM_POST_SQLITE_PUBLISHED_STATE_PASS\n";
