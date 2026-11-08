<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||is_dir('/home/appontrack'))
    throw new RuntimeException('CI_ONLY_SCENARIO_OPTIONAL_KB');
if(!getenv('ONTRACK_CI_NAMESPACE'))putenv('ONTRACK_CI_NAMESPACE=scenariokb'.bin2hex(random_bytes(8)));
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/TenantVoiceSession.php';
require_once __DIR__.'/../saas/console/_core/VoiceKnowledge.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
function check_optional_kb(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('OPTIONAL_KNOWLEDGE_FAIL: '.$label);
}
seed_console();
$account=4;
$site=insert("INSERT INTO sites(account_id,name,domain,status,created_at)
    VALUES (?,?,?,'active',?)",[$account,'Standalone optional-KB scenario',null,now()]);
$owner=one("SELECT user_id FROM memberships WHERE account_id=? AND role='owner' LIMIT 1",[$account]);
check_optional_kb((bool)$owner,'account owner exists');
$graph=['nodes'=>[['id'=>'start','type'=>'start','text'=>'ابدأ'],['id'=>'end','type'=>'end','text'=>'أنهي']],
    'edges'=>[['from'=>'start','to'=>'end','label'=>'']]];
$agent=insert("INSERT INTO agents(account_id,site_id,name,voice,instructions,knowledge,flow_json,status,created_at)
    VALUES (?,?,?,?,?,?,?,'draft',?)",[
    $account,$site,'Scenario-only agent','google.aoede','اتكلم ببساطة','',
    json_encode($graph,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),now()
]);
try{
    $scenario='ابدأ بتحية لطيفة، اسمع سؤال المتصل، جاوب عليه ثم اختم باختصار.';
    $policy=answer_policy_defaults();
    $policy['scenario_mode']='scenario';
    $policy['scenario_text']=$scenario;
    $policy['use_knowledge']=true;
    save_answer_policy($account,$agent,$policy);
    $published=publish_agent_flow($account,$agent,(int)$owner['user_id']);
    check_optional_kb($published['revision']===1,'publish without any uploaded KB');
    $binding=['account_id'=>$account,'site_id'=>$site,'agent_id'=>$agent,'sim_slot'=>0];
    $live=tenant_voice_definition($binding);
    check_optional_kb($live['scenario_mode']==='scenario','same published scenario mode');
    check_optional_kb(str_contains($live['instruction'],$scenario),'scenario present without KB');
    check_optional_kb(str_contains($live['instruction'],'لا توجد معرفة عامة معتمدة'),'missing KB is explicit non-blocking fallback');
    check_optional_kb(str_contains($live['instruction'],'المعرفة الإضافية اختيارية'),'no inline KB required');
    check_optional_kb(voice_kb_agent_context($account,$site,$agent)==='','no sources means empty KB context');
    $invalid=false;
    try{voice_knowledge_validate('','note','','public');}
    catch(InvalidArgumentException){$invalid=true;}
    check_optional_kb($invalid,'saving a malformed non-empty KB source still rejects');
    $name='التعليمات المعتمدة';
    $src=voice_knowledge_add($account,$site,$name,'note','المواعيد تتحدد بعد التواصل.','public');
    $augmented=tenant_voice_definition($binding);
    check_optional_kb(str_contains($augmented['instruction'],'المواعيد تتحدد بعد التواصل.'),
        'optional public KB enriches same scenario when available');
    check_optional_kb(str_contains($augmented['instruction'],$scenario),'KB does not replace published scenario');
    voice_knowledge_delete($account,$src);
    $after=tenant_voice_definition($binding);
    check_optional_kb(str_contains($after['instruction'],$scenario),'scenario survives archived KB');
    $_SESSION=['uid'=>(int)$owner['user_id'],'account_id'=>$account];
    $_GET=['tab'=>'agents','site'=>(string)$site];
    $accountRow=one("SELECT * FROM accounts WHERE id=?",[$account]);
    ob_start();render_agents(array_merge($accountRow,['account_id'=>$account,'role'=>'owner']));
    $html=(string)ob_get_clean();
    check_optional_kb(str_contains($html,'قاعدة المعرفة اختيارية'),'optional KB explained in studio');
    check_optional_kb(str_contains($html,'io.premium')||function_exists('io_premium_access_allowed'),
        'IO Premium function available before user navigates to voices');
    $actions=(string)file_get_contents(__DIR__.'/../saas/console/_core/Actions.php');
    check_optional_kb(str_contains($actions,"if(!$".'hasFile&&trim($text)'."==='')"),
        'empty KB form explicitly bypasses source validation');
    check_optional_kb(str_contains($actions,"voice_knowledge_add("),'nonempty KB upload still validated');
    echo "OPTIONAL_KB_SCENARIO_PUBLISH_AND_SOG_IO_PREMIUM_BOOTSTRAP_PASS\n";
}finally{
    execute('DELETE FROM agent_flow_versions WHERE agent_id=? AND account_id=?',[$agent,$account]);
    execute('DELETE FROM agent_answer_policies WHERE agent_id=? AND account_id=?',[$agent,$account]);
    execute('DELETE FROM agents WHERE id=? AND account_id=?',[$agent,$account]);
    execute('DELETE FROM voice_knowledge_sources WHERE account_id=? AND site_id=?',[$account,$site]);
    execute('DELETE FROM sites WHERE id=? AND account_id=?',[$site,$account]);
}
