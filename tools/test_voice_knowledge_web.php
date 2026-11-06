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
kb_web_check(!str_contains(voice_kb_agent_context(4,$site,$agent),'dummy text'),'unapproved change hidden');
kb_web_check(voice_knowledge_approve(4,$site,$newSource),'new text approved');
voice_kb_scope_set(4,$site,$newSource,'all',0,500,1);
kb_web_check(str_contains(voice_kb_agent_context(4,$site,$agent),'dummy text'),'approved new source visible');
execute('UPDATE voice_kb_meta SET expires_at=? WHERE source_id=?',
    [gmdate('Y-m-d H:i:s',time()-10),$newSource]);
kb_web_check(!str_contains(voice_kb_agent_context(4,$site,$agent),'dummy text'),'expired source excluded');
$unchanged=voice_kb_web_sync(4,$site,$link,static fn()=> 'dummy text not used');
kb_web_check($unchanged['status']==='unchanged','unchanged content recognized');
kb_web_check(!str_contains(voice_kb_agent_context(4,$site,$agent),'dummy text'),'sync does not silently extend owner approved TTL');
kb_web_check(!str_contains(voice_kb_agent_context(5,$site,$other),'Clinic policy'),'cross-tenant isolation');
echo "VOICE_KNOWLEDGE_WEB_AGENT_TTL_PASS\n";
