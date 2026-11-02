<?php
declare(strict_types=1);
if(is_dir('/home/appontrack'))throw new RuntimeException('CI_TEST_ONLY');
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/TenantVoiceSession.php';
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
    ['id'=>'start','type'=>'start','text'=>'OBSOLETE_FLOW_TEXT_NEVER_SPEAK','x'=>800,'y'=>100],
    ['id'=>'end','type'=>'end','text'=>'Legacy graph only','x'=>800,'y'=>320]
], 'edges'=>[['from'=>'start','to'=>'end','label'=>'']]];
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
    dynamic_test(!str_contains($prompt,'OBSOLETE_FLOW_TEXT_NEVER_SPEAK'),
        'legacy scenario nodes never used to direct conversation');
    dynamic_test(str_contains($prompt,'لا تتبع شجرة قرارات'),'dynamic conversation instead of fixed tree');
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
    $_GET=['tab'=>'agents','site'=>(string)$site];
    $admin=one('SELECT * FROM accounts WHERE id=4');
    ob_start();render_agents(array_merge($admin,['account_id'=>4,'role'=>'owner']));
    $html=(string)ob_get_clean();
    dynamic_test(str_contains($html,'agent_goal') && str_contains($html,'agent_guardrails'),
        'Agent Studio renders goals and safeguards');
    dynamic_test(str_contains($html,'agent-studio-form'),'Agent Studio AJAX form rendered');
    dynamic_test(!str_contains($html,'data-flow-editor') && !str_contains($html,'flow_template'),
        'no forced scenario template or graph builder');
    echo "DYNAMIC_AGENT_STUDIO_RUNTIME_PASS\n";
}finally{
    voice_knowledge_delete(4,$knowledge);
    execute("DELETE FROM voice_knowledge_sources WHERE account_id=4 AND site_id=? AND name='Staff internal'",[$site]);
}
