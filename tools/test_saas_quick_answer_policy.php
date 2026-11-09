<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/PublishedFlow.php';
if(is_dir('/home/appontrack') && empty(getenv('ONTRACK_CI_NAMESPACE')))
    throw new RuntimeException('isolate_site_quick_answer_policy_test');
function quick_check(bool $condition,string $label):void {
    if(!$condition)throw new RuntimeException('QUICK_ANSWER_POLICY_FAIL '.$label);
}
seed_console();
$agent=one("SELECT id,account_id,site_id,voice,instructions,knowledge,flow_json
    FROM agents WHERE account_id=4 AND site_id=1 AND status!='archived'
    ORDER BY id LIMIT 1");
quick_check($agent!==null,'seed agent exists');
$account=(int)$agent['account_id'];
$id=(int)$agent['id'];
$baseline=answer_policy_for_agent($account,$id);
$before=publish_agent_flow($account,$id,1);
$publishedBefore=published_agent_flow($account,(int)$agent['site_id'],$id);
quick_check((int)$publishedBefore['revision']===(int)$before['revision'],'baseline published');
$next=$baseline;
$next['auto_answer']=true;
$next['answer_delay_seconds']=5;
save_answer_policy($account,$id,$next);
$draft=answer_policy_for_agent($account,$id);
quick_check($draft['answer_delay_seconds']===5 && $draft['auto_answer']===true,
    'five-second auto-answer saved as valid policy');
foreach(array_diff(array_keys($draft),['answer_delay_seconds','auto_answer']) as $field)
    quick_check($draft[$field]===$baseline[$field], 'preserved existing option '.$field);
$unpublished=published_agent_flow($account,(int)$agent['site_id'],$id);
quick_check($unpublished['snapshot_hash']===$publishedBefore['snapshot_hash'],
    'draft policy cannot change current phone behavior');
$result=publish_agent_flow($account,$id,1);
$after=published_agent_flow($account,(int)$agent['site_id'],$id);
quick_check($after!==null && (int)$after['revision']===(int)$result['revision'],
    'published policy revision exists');
$actual=answer_policy_normalize(json_decode((string)$after['answer_policy_json'],true));
quick_check($actual['auto_answer']===true && $actual['answer_delay_seconds']===5,
    'published five-second policy becomes visible to SIM session');
quick_check($after['voice']===$agent['voice'] &&
    $after['instructions']===$agent['instructions'] &&
    $after['knowledge']===$agent['knowledge'],
    'voice/instructions/knowledge not reset by quick answer setting');
$actions=(string)file_get_contents(__DIR__.'/../saas/console/_core/Actions.php');
$views=(string)file_get_contents(__DIR__.'/../saas/console/_core/Views.php');
quick_check(str_contains($actions,"case 'update_answer_policy_quick':") &&
    str_contains($views,"form_start('update_answer_policy_quick'"),
    'the new SaaS UI and action are connected');
quick_check(str_contains($actions,"$"."nextPolicy=$"."previousPolicy;"),
    'form does not reset other answer policy fields');
quick_check(str_contains($views,'حفظ ونشر للشريحة') && str_contains($views,'حفظ كمسودة'),
    'both publish and save-draft options remain available');
echo "SAAS_QUICK_ANSWER_5_SECONDS_NONREGRESSION_PASS\n";
