<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/VoiceKnowledgeWeb.php';
if(is_dir('/home/appontrack'))throw new RuntimeException('CI fixture only');
function kb_web_check(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('KB_WEB_FAIL '.$label);
}
seed_console();
$site=(int)one("SELECT id FROM sites WHERE account_id=4 AND status='active' LIMIT 1")['id'];
$agents=all("SELECT id FROM agents WHERE account_id=4 AND site_id=? AND status!='archived' LIMIT 2",[$site]);
$agent=(int)$agents[0]['id'];
$other=(int)one("SELECT id FROM agents WHERE account_id=5 AND status!='archived' LIMIT 1")['id'];
$bad=false;try{voice_kb_web_add(4,$site,'No','https://127.0.0.1/internal');}
catch(InvalidArgumentException){$bad=true;}
kb_web_check($bad,'localhost SSRF denied');
$bad=false;try{voice_kb_web_add(4,$site,'No','https://example.com/../secrets');}
catch(InvalidArgumentException){$bad=true;}
kb_web_check($bad,'traversal rejected');
$bad=false;try{voice_kb_web_add(5,$site,'No','https://example.com/faq');}
catch(InvalidArgumentException){$bad=true;}
kb_web_check($bad,'foreign site denied');
$folder=voice_knowledge_folder_add(4,$site,'Knowledge web sources');
$link=voice_kb_web_add(4,$site,'Clinic public policy','https://example.com/help',$folder,'public',24);
kb_web_check($link===voice_kb_web_add(4,$site,'Clinic public policy','https://example.com/help',$folder,'public',24),'link dedupe');
$payload='Clinic policy is to take reservations only after a confirmed appointment identifier from API; opening hours are nine to five.';
$sync=voice_kb_web_sync(4,$site,$link,static fn(string $url):string=>$payload);
kb_web_check($sync['status']==='review','new link is untrusted until owner approval');
$source=(int)$sync['source_id'];
kb_web_check(!str_contains(voice_kb_agent_context(4,$site,$agent),'Clinic policy'),'pre-approval hidden');
$bad=false;try{voice_kb_web_sync(5,$site,$link,static fn()=> 'secret');}
catch(InvalidArgumentException){$bad=true;}
kb_web_check($bad,'link tenant boundary');
kb_web_check(voice_knowledge_approve(4,$site,$source),'approved by owner');
kb_web_check((string)one('SELECT status FROM voice_kb_web_links WHERE id=?',[$link])['status']==='current',
    'approved link is current, not waiting for review');
kb_web_check(str_contains(voice_kb_agent_context(4,$site,$agent),'Clinic policy'),'approved visible');
voice_kb_scope_set(4,$site,$source,'agent',$agent,900,3);
kb_web_check(str_contains(voice_kb_agent_context(4,$site,$agent),'Clinic policy'),'allowlisted agent sees facts');
kb_web_check(!str_contains(voice_kb_agent_context(4,$site,$other),'Clinic policy'),'unrelated agent sees no facts');
$bad=false;try{voice_kb_scope_set(4,$site,$source,'agent',$other,500);}
catch(InvalidArgumentException){$bad=true;}
kb_web_check($bad,'foreign agent scope denied');
$again=voice_kb_web_sync(4,$site,$link,static fn()=> 'dummy text not used');
kb_web_check($again['status']==='review','changed content requires review');
$newSource=(int)$again['source_id'];
$pendingAgain=voice_kb_web_sync(4,$site,$link,static fn()=> 'dummy text not used');
kb_web_check($pendingAgain['status']==='review'&&(int)$pendingAgain['source_id']===$newSource,
    'refetch of pending review cannot silently approve');
kb_web_check((string)one('SELECT status FROM voice_kb_web_links WHERE id=?',[$link])['status']==='review',
    'pending review remains visibly pending');
$newMeta=one('SELECT scope_mode,priority,expires_at FROM voice_kb_meta WHERE source_id=?',[$newSource]);
kb_web_check($newMeta['scope_mode']==='agent'&&(int)$newMeta['priority']===900&&
    $newMeta['expires_at']!==null,'refresh inherits agent scope priority and expiry');
kb_web_check((bool)one('SELECT agent_id FROM voice_kb_agent_scope WHERE source_id=? AND agent_id=?',
    [$newSource,$agent]),'refresh retains scoped agent allowlist');
kb_web_check(!str_contains(voice_kb_agent_context(4,$site,$agent),'dummy text'),'unapproved change hidden');
kb_web_check(str_contains(voice_kb_agent_context(4,$site,$agent),'Clinic policy'),
    'old approved facts kept during review');
kb_web_check(voice_knowledge_approve(4,$site,$newSource),'new text approved');
kb_web_check((string)one('SELECT status FROM voice_knowledge_sources WHERE id=?',[$source])['status']==='archived',
    'approval archives only prior version from same URL');
kb_web_check(!str_contains(voice_kb_agent_context(4,$site,$agent),'Clinic policy'),
    'previous version removed from agent context');
kb_web_check(!str_contains(voice_kb_agent_context(4,$site,$agent+999999),'dummy text'),
    'new URL revision cannot widen the prior allowlist');
voice_kb_scope_set(4,$site,$newSource,'all',0,500,1);
kb_web_check(str_contains(voice_kb_agent_context(4,$site,$agent),'dummy text'),'approved new source visible');
execute('UPDATE voice_kb_meta SET expires_at=? WHERE source_id=?',
    [gmdate('Y-m-d H:i:s',time()-10),$newSource]);
kb_web_check(!str_contains(voice_kb_agent_context(4,$site,$agent),'dummy text'),'expired source excluded');
$unchanged=voice_kb_web_sync(4,$site,$link,static fn()=> 'dummy text not used');
kb_web_check($unchanged['status']==='unchanged','unchanged content recognized');
kb_web_check(!str_contains(voice_kb_agent_context(4,$site,$agent),'dummy text'),'sync does not silently extend owner approved TTL');
kb_web_check(!str_contains(voice_kb_agent_context(5,$site,$other),'Clinic policy'),'cross-tenant isolation');
execute('UPDATE voice_kb_web_links SET next_check_at=? WHERE id=? AND account_id=4',
    [gmdate('Y-m-d H:i:s',time()-5),$link]);
$due=voice_kb_sync_due(4,$site,3,static fn()=> 'dummy text not used');
kb_web_check($due['checked']===1&&$due['unchanged']===1,
    'manual bounded sync uses due timestamp and does not auto-approve');
$otherLink=voice_kb_web_add(4,$site,'Clinic public policy','https://example.org/help',$folder,'public',24);
$otherSync=voice_kb_web_sync(4,$site,$otherLink,static fn()=> 'dummy text not used');
kb_web_check($otherSync['status']==='review'&&(int)$otherSync['source_id']!==$newSource,
    'identical text from different URL has independent provenance');
kb_web_check(voice_knowledge_approve(4,$site,(int)$otherSync['source_id']),
    'independent URL revision approved');
kb_web_check((string)one('SELECT status FROM voice_knowledge_sources WHERE id=?',[$newSource])['status']==='active',
    'different URL cannot archive the first approved source');
$crossDue=false;
try{voice_kb_sync_due(5,$site,3,static fn()=> 'irrelevant');}
catch(InvalidArgumentException){$crossDue=true;}
kb_web_check($crossDue,'due sync cannot cross tenant');
// The scheduled worker processes separate accounts and keeps changes pending.
$tenantSite=(int)one("SELECT id FROM sites WHERE account_id=5 AND status='active' LIMIT 1")['id'];
kb_web_check(voice_kb_agent_context(4,$site,$agent+999999)==='',
    'unknown agent cannot read even all-scope knowledge');
kb_web_check(voice_kb_agent_context(4,$site,$other)==='',
    'cross-tenant agent cannot read any public knowledge');
$autoA=voice_kb_web_add(4,$site,'Auto knowledge A','https://example.com/auto-a',$folder,'public',1);
$autoB=voice_kb_web_add(5,$tenantSite,'Auto knowledge B','https://example.org/auto-b',0,'public',1);
$auto=voice_kb_sync_due_all(12,static fn(string $url):string=>
    'Public updated documentation must remain pending until owner review, source '.hash('sha256',$url));
kb_web_check($auto['checked']===2&&$auto['review']===2&&$auto['failed']===0,
    'scheduled scan handles two isolated accounts');
$autoSourceA=(int)one("SELECT source_id FROM voice_kb_web_links WHERE id=? AND account_id=4",[$autoA])['source_id'];
$autoSourceB=(int)one("SELECT source_id FROM voice_kb_web_links WHERE id=? AND account_id=5",[$autoB])['source_id'];
kb_web_check((string)one("SELECT status FROM voice_knowledge_sources WHERE id=?",[$autoSourceA])['status']==='review' &&
    (string)one("SELECT status FROM voice_knowledge_sources WHERE id=?",[$autoSourceB])['status']==='review',
    'background sync never auto approves foreign or local source');
$badLink=voice_kb_web_add(4,$site,'Unreachable','https://example.net/fail',$folder,'public',1);
$failed=voice_kb_sync_due_all(12,static function(string $url):string {
    throw new RuntimeException('test_network_down');
});
kb_web_check($failed['checked']===1&&$failed['failed']===1,
    'scheduled fetch fails closed');
$retry=one('SELECT status,next_check_at FROM voice_kb_web_links WHERE id=?',[$badLink]);
kb_web_check($retry['status']==='error' && $retry['next_check_at']>now(),
    'failed refresh receives retry backoff');
kb_web_check(voice_kb_sync_due_all(12,static fn()=> 'no network fetch required')['checked']===0,
    'failed link is not retried on every tick');
echo "VOICE_KNOWLEDGE_WEB_AGENT_TTL_PASS\n";
