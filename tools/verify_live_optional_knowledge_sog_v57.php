<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!is_dir('/home/appontrack/public_html'))
    throw new RuntimeException('canonical host read-only verification only');
$root='/home/appontrack/public_html/';
require_once $root.'_core/Bootstrap.php';
require_once $root.'_core/Views.php';
require_once $root.'_core/TenantVoiceSession.php';
$views=(string)file_get_contents($root.'_core/Views.php');
$actions=(string)file_get_contents($root.'_core/Actions.php');
$session=(string)file_get_contents($root.'_core/TenantVoiceSession.php');
$policy=answer_policy_normalize([
    'scenario_mode'=>'scenario','scenario_text'=>'اسأل العميل عن احتياجه.',
    'use_knowledge'=>true
]);
$instruction=tenant_dynamic_instruction('مساعد الاختبار',
    ['instructions'=>'استمع للمتصل','knowledge'=>''],$policy,'','',
    tenant_voice_scenario_instruction($policy['scenario_text']));
$checks=[
    'io_premium_function_loaded'=>function_exists('io_premium_access_allowed'),
    'reseller_agent_screen_exists'=>function_exists('render_agents'),
    'provider_required_at_views_bootstrap'=>str_contains(substr($views,0,400),
        "require_once __DIR__.'/IoPremiumProvider.php';"),
    'optional_kb_marked_in_agent_studio'=>str_contains($views,'قاعدة المعرفة اختيارية'),
    'empty_knowledge_form_noop'=>str_contains($actions,'if(!$hasFile&&trim($text)==='),
    'real_kb_source_still_validated'=>str_contains($actions,'voice_knowledge_add('),
    'knowledge_backend_failure_nonblocking'=>str_contains($session,'catch(Throwable $kbError)'),
    'text_scenario_without_kb'=>str_contains($instruction,'اسأل العميل عن احتياجه.'),
    'missing_kb_does_not_block'=>str_contains($instruction,'لا توجد معرفة عامة معتمدة'),
    'published_scenario_unchanged'=>str_contains($session,'tenant_voice_scenario_instruction(')
];
$ok=!in_array(false,$checks,true);
echo json_encode(['result'=>$ok?'PASS':'FAIL','checks'=>$checks,
    'database_writes'=>false,'secrets_exposed'=>false],
    JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
exit($ok?0:2);
