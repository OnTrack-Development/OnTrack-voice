<?php
declare(strict_types=1);
require dirname(__DIR__).'/saas/console/_core/Bootstrap.php';
require dirname(__DIR__).'/saas/console/_core/TenantVoiceSession.php';
require dirname(__DIR__).'/saas/console/_core/Views.php';
function pass(bool $condition,string $why):void {
    if(!$condition)throw new RuntimeException('TEST_FAILURE: '.$why);
}
// All CI checks run in isolated staging, never against the live host.
if(is_dir('/home/appontrack'))throw new RuntimeException('Test must run in isolated CI, not on hosted production');
$path=private_path().'/tenant-voice-owner-trial.json';
if(is_file($path))throw new RuntimeException('Refusing to replace an existing private trial');
$b=['account_id'=>1,'site_id'=>6,'agent_id'=>7,'sim_slot'=>0];
pass(!tenant_voice_owner_trial_enabled($b),'trial disabled by default');
$settings=['enabled'=>true,'account_id'=>1,'site_id'=>6,'agent_id'=>7,'sim_slot'=>0,
    'expires_utc'=>gmdate('Y-m-d\\TH:i:s\\Z',time()+3600)];
try {
    file_put_contents($path,json_encode($settings,JSON_THROW_ON_ERROR));
    pass(tenant_voice_owner_trial_enabled($b),'explicit owner SIM 1 trial enabled');
    foreach(['account_id','site_id','agent_id','sim_slot'] as $field) {
        $foreign=$b;$foreign[$field]++;
        pass(!tenant_voice_owner_trial_enabled($foreign),'foreign scope denied: '.$field);
    }
    $settings['expires_utc']=gmdate('Y-m-d\\TH:i:s\\Z',time()-5);
    file_put_contents($path,json_encode($settings,JSON_THROW_ON_ERROR));
    pass(!tenant_voice_owner_trial_enabled($b),'expired trial denied');
    $settings['expires_utc']=gmdate('Y-m-d\\TH:i:s\\Z',time()+259200);
    file_put_contents($path,json_encode($settings,JSON_THROW_ON_ERROR));
    pass(!tenant_voice_owner_trial_enabled($b),'long-lived trial denied');
} finally { @unlink($path); }
$form=form_start('archive_agent','/?tab=agents','class="inline-form"');
pass(substr_count($form,'class=')===1,'form generates exactly one CSS class attribute');
pass(str_contains($form,'class="stack inline-form"'),'form retains inline and stack styling');
$branding=form_start('brand','/?tab=settings','enctype="multipart/form-data"');
pass(str_contains($branding,'enctype="multipart/form-data"'),'upload form preserves enctype');
$view=(string)file_get_contents(dirname(__DIR__).'/saas/console/_core/Views.php');
pass(str_contains($view,'app.css?v='),'CSS has source-version cache bust');
pass(str_contains($view,'app.js?v='),'JavaScript has source-version cache bust');
$actions=(string)file_get_contents(dirname(__DIR__).'/saas/console/_core/Actions.php');
pass(str_contains($actions,'الإيجنت مرتبط بشريحة نشطة'),'cannot silently archive SIM-bound agent');
echo "VOICE_TRIAL_AND_UI_PASS\\n";
