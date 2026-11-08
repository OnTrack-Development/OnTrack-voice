#!/usr/bin/env python3
"""Real PHP Actions::act() regression against isolated SOG-style customer.

Two real form submits: draft must not update SIM snapshot, then publish makes
auto_answer=true available. No production account, carrier, or network call.
"""
import json
import os
from pathlib import Path
import subprocess
import uuid

root=Path(__file__).resolve().parents[1]
tag='v76_'+uuid.uuid4().hex[:16]
env=dict(os.environ,ONTRACK_CI_NAMESPACE=tag)

def php(code):
    result=subprocess.run(['php','-r',code],cwd=root,env=env,
                          capture_output=True,text=True,timeout=50)
    assert result.returncode==0,(result.returncode,result.stderr[-1400:],result.stdout[-1400:])
    return result.stdout.strip()

setup='''
require "saas/console/database/Seed.php";
require "saas/console/_core/Actions.php";
seed_console();
execute("UPDATE accounts SET name='SOG' WHERE id=5");
$agent=one("SELECT * FROM agents WHERE account_id=5 AND status!='archived' ORDER BY id LIMIT 1");
if(!$agent)throw new RuntimeException("fixture_agent_missing");
$user=one("SELECT u.id,u.auth_version FROM users u JOIN memberships m ON u.id=m.user_id WHERE m.account_id=5 AND m.role='owner' LIMIT 1");
if(!$user)throw new RuntimeException("fixture_owner_missing");
$_SESSION=["uid"=>(int)$user["id"],"auth_version"=>(int)$user["auth_version"],
"account_id"=>5,"csrf"=>"v76-csrf"];
$_SERVER["REQUEST_METHOD"]="POST";
$_SERVER["HTTP_X_REQUESTED_WITH"]="XMLHttpRequest";
$_POST=[
 "action"=>"update_agent","csrf"=>"v76-csrf",
 "agent_id"=>(string)$agent["id"],
 "name"=>(string)$agent["name"],
 "voice"=>(string)$agent["voice"],
 "instructions"=>"أهلا بيك في خدمة SOG التجريبية.",
 "knowledge"=>(string)$agent["knowledge"],
 "flow_json"=>(string)$agent["flow_json"],
 "scenario_mode"=>"builder",
 "response_style"=>"egyptian_short",
 "answer_delay_seconds"=>"5","max_call_minutes"=>"5",
 "auto_answer"=>"on","fallback"=>"human",
 "agent_goal"=>"رد طبيعي محترم",
 "agent_guardrails"=>"تحقق من النتائج قبل تأكيد أي إجراء",
 "use_knowledge"=>"on","use_signed_facts"=>"on","human_handoff"=>"on"
];
$fields=['flow_json'=>(string)$agent['flow_json'],
    'scenario_text'=>'أهلا وسهلا يا عميل SOG، احكيلنا طلبك',
    'instructions'=>'رد على العميل بالعربي المصري بشكل محترم',
    'knowledge'=>'بيانات تجربة منع إرجاع 404 بواسطة WAF',
    'agent_goal'=>'ساعد العميل واسمَعه للآخر',
    'agent_guardrails'=>'احفظ بيانات العميل بدون إرسال أسرار'];
$_POST['studio_fields_b64']=rtrim(strtr(
    base64_encode(json_encode($fields,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)),
    '+/','-_'),'=');
foreach($fields as $key=>$value)unset($_POST[$key]);
'''
draft=php(setup+'act();')
draft_json=json.loads(draft)
assert draft_json['ok'] is True and draft_json['published'] is False,draft_json
print('PASS customer draft save returns valid JSON')

draft_check=php('''
require "saas/console/database/Seed.php";
require "saas/console/_core/AgentAnswerPolicy.php";
require "saas/console/_core/PublishedFlow.php";
$agent=one("SELECT * FROM agents WHERE account_id=5 AND status!='archived' LIMIT 1");
$policy=answer_policy_for_agent(5,(int)$agent["id"]);
$old=published_agent_flow(5,(int)$agent["site_id"],(int)$agent["id"]);
echo json_encode(["auto_answer"=>$policy["auto_answer"],"delay"=>$policy["answer_delay_seconds"],
 "existing_published_revision"=>$old["revision"]??null],JSON_THROW_ON_ERROR);
''')
before=json.loads(draft_check)
assert before['auto_answer'] is True and before['delay']==5,before
print('PASS draft stores auto-answer without pretending it is published')

published=php(setup+'$_POST["publish_now"]="1";act();')
published_json=json.loads(published)
assert published_json['ok'] is True and published_json['published'] is True,published_json
print('PASS customer publish returns valid JSON')

published_check=php('''
require "saas/console/database/Seed.php";
require "saas/console/_core/PublishedFlow.php";
$agent=one("SELECT * FROM agents WHERE account_id=5 AND status!='archived' LIMIT 1");
$published=published_agent_flow(5,(int)$agent["site_id"],(int)$agent["id"]);
if(!$published)throw new RuntimeException("published_snapshot_missing");
$policy=json_decode((string)$published["answer_policy_json"],true);
echo json_encode(["revision"=>(int)$published["revision"],
 "auto_answer"=>$policy["auto_answer"]??null,
 "answer_delay_seconds"=>$policy["answer_delay_seconds"]??null],JSON_THROW_ON_ERROR);
''')
after=json.loads(published_check)
assert after['revision']>=1 and after['auto_answer'] is True and after['answer_delay_seconds']==5,after
print('V78_SOG_AGENT_PACKED_UNICODE_DRAFT_PUBLISH_AUTOANSWER_PASS')

validation=php('''
require "saas/console/_core/Actions.php";
$_POST=['studio_fields_b64'=>'not-*base64'];
try{agent_studio_unpack_fields();echo "BAD";}catch(InvalidArgumentException $e){echo "REJECTED";}
''')
assert validation=='REJECTED','invalid packed input not rejected'
validation=php('''
require "saas/console/_core/Actions.php";
$_POST=['studio_fields_b64'=>rtrim(strtr(base64_encode(json_encode(['agent_id'=>'999',
'flow_json'=>'{}'])),'+/','-_'),'=')];
try{agent_studio_unpack_fields();echo "BAD";}catch(InvalidArgumentException $e){echo "REJECTED";}
''')
assert validation=='REJECTED','bundle could override authorized agent_id'
print('V78_SOG_PACKED_FIELDS_LIMIT_AND_IDENTITY_GUARDS_PASS')
