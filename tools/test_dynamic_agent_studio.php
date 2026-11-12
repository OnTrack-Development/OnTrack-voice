<?php
declare(strict_types=1);
if(is_dir('/home/appontrack'))throw new RuntimeException('CI_TEST_ONLY');
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/TenantVoiceSession.php';
require_once __DIR__.'/../saas/console/_core/VoiceKnowledge.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
function dynamic_test(bool $yes,string $message):void{
    if(!$yes)throw new RuntimeException('DYNAMIC_AGENT_STUDIO_FAIL: '.$message);
}
seed_console();
$agent=one("SELECT id,site_id FROM agents WHERE account_id=4 AND status!='archived' ORDER BY id LIMIT 1");
dynamic_test($agent!==null,'seeded demo agent');
$id=(int)$agent['id'];$site=(int)$agent['site_id'];
$policy=answer_policy_normalize([
    'goal'=>'تشخيص أعطال الاستضافة وتوضيح الخدمات وفق طلب العميل',
    'guardrails'=>'ممنوع تأكيد الدفع أو التعديل بدون موافقة',
    'response_style'=>'egyptian_short',
    'use_knowledge'=>true,'use_signed_facts'=>true,'human_handoff'=>true
]);
dynamic_test($policy['conversation_mode']==='dynamic','dynamic mode default');
dynamic_test($policy['goal']!=='','goal accepted');
save_answer_policy(4,$id,$policy);
$graph=['nodes'=>[
    ['id'=>'start','type'=>'start','text'=>'بدء','x'=>800,'y'=>100],
    ['id'=>'intro','type'=>'say','text'=>'أهلاً بك في خدمة العملاء','x'=>800,'y'=>190],
    ['id'=>'question','type'=>'ask','text'=>'ما نوع الخدمة المطلوبة؟','x'=>800,'y'=>300],
    ['id'=>'end','type'=>'end','text'=>'تم تسجيل طلبك','x'=>800,'y'=>420]
], 'edges'=>[
    ['from'=>'start','to'=>'intro','label'=>''],
    ['from'=>'intro','to'=>'question','label'=>''],
    ['from'=>'question','to'=>'end','label'=>'الاستضافة']
]];
execute("UPDATE agents SET flow_json=? WHERE id=? AND account_id=?",[
    json_encode($graph,JSON_THROW_ON_ERROR),$id,4]);
$release=publish_agent_flow(4,$id,1);
dynamic_test(($release['revision']??0)>=1,'published snapshot created');
$knowledge=voice_knowledge_add(4,$site,'Public support FAQ','note',
    'KNOWLEDGE_PUBLIC_SITE_4_SITE_'.$site);
voice_knowledge_add(4,$site,'Staff internal','note','DONT_LEAK_STAFF_KNOWLEDGE','staff');
try{
    $binding=['account_id'=>4,'site_id'=>$site,'agent_id'=>$id,'sim_slot'=>0];
    $live=tenant_voice_definition($binding);
    $prompt=$live['instruction'];
    dynamic_test($live['conversation_mode']==='dynamic','runtime mode is dynamic');
    dynamic_test(str_contains($prompt,$policy['goal']),'published goal passed to live runtime');
    dynamic_test(str_contains($prompt,$policy['guardrails']),'published guardrails passed to live runtime');
    dynamic_test(str_contains($prompt,'KNOWLEDGE_PUBLIC_SITE_4_SITE_'.$site),
        'public tenant/site-scoped knowledge included');
    dynamic_test(!str_contains($prompt,'DONT_LEAK_STAFF_KNOWLEDGE'),
        'staff-only knowledge excluded');
    dynamic_test(str_contains($prompt,'مسارات المخطط المنشور')
        && str_contains($prompt,'question [ask] ما نوع الخدمة المطلوبة؟')
        && str_contains($prompt,'الاستضافة → end'),
        'published graph routes and conditional labels guide the real Voice prompt');
    dynamic_test(!str_contains($prompt,'لا تتبع شجرة قرارات'),
        'obsolete instruction to ignore the published graph removed');
    dynamic_test($live['routing_contract']==='published_graph_prompt_guidance',
        'prompt-based graph guidance explicitly distinguished from deterministic execution');
    dynamic_test(str_contains($prompt,'استأنف العقدة المعلقة'),
        'side questions return to pending graph step');
    dynamic_test(str_contains($prompt,'المقاطعات'),'interruption handling');
    dynamic_test(str_contains($prompt,'ليس لديك أداة WHMCS'),'no imaginary CRUD tools');
    $otherDenied=false;
    try{tenant_voice_definition(['account_id'=>5,'site_id'=>$site,'agent_id'=>$id,'sim_slot'=>0]);}
    catch(InvalidArgumentException){$otherDenied=true;}
    dynamic_test($otherDenied,'foreign tenant isolated');
    $policyOff=$policy;$policyOff['use_knowledge']=false;$policyOff['use_signed_facts']=false;
    $noFacts=tenant_dynamic_instruction('Demo', ['instructions'=>'انت مساعد','knowledge'=>'Company'],
        $policyOff,'SECRET_PUBLIC_DATA','SECRET_INTEGRATION');
    dynamic_test(!str_contains($noFacts,'SECRET_PUBLIC_DATA')&&!str_contains($noFacts,'SECRET_INTEGRATION'),
        'disabled knowledge and external metadata not in voice session');
    $owner=one("SELECT user_id FROM memberships WHERE account_id=4 AND role='owner' ORDER BY user_id LIMIT 1");
    dynamic_test($owner!==null,'owner membership fixture');
    $_SESSION=['uid'=>(int)$owner['user_id'],'account_id'=>4];
    $_GET=['tab'=>'agents','site'=>(string)$site];
    $admin=one('SELECT * FROM accounts WHERE id=4');
    ob_start();render_agents(array_merge($admin,['account_id'=>4,'role'=>'owner']));
    $html=(string)ob_get_clean();
    dynamic_test(str_contains($html,'agent_goal') && str_contains($html,'agent_guardrails'),
        'Agent Studio renders goals and safeguards');
    dynamic_test(str_contains($html,'agent-studio-form'),'Agent Studio AJAX form rendered');
    dynamic_test(str_contains($html,'data-flow-editor') && str_contains($html,'graph-ai-launcher')===false,
        'visual builder restored as a real canvas that JS mounts');
    dynamic_test(str_contains($html,'data-builder-tab="intelligence"') && str_contains($html,'data-builder-pane="knowledge"'),
        'new intelligence, knowledge and integration panels alongside visual builder');
    dynamic_test(!str_contains($html,'flow_template'),
        'no mandatory old scenario template on agent');
    echo "DYNAMIC_AGENT_STUDIO_RUNTIME_PASS\n";
}finally{
    voice_knowledge_delete(4,$knowledge);
    execute("DELETE FROM voice_knowledge_sources WHERE account_id=4 AND site_id=? AND name='Staff internal'",[$site]);
}
