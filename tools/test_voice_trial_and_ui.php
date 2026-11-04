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
// Rights come from account and subscription, never a daily owner test pass.
$platform=['kind'=>'platform','status'=>'active'];
$tenant=['kind'=>'customer','status'=>'active'];
pass(tenant_voice_entitlement_allows($platform,false),'active platform account has permanent access');
pass(tenant_voice_entitlement_allows($tenant,true),'active subscribed account has voice access');
pass(!tenant_voice_entitlement_allows($tenant,false),'unsubscribed tenant denied');
pass(!tenant_voice_entitlement_allows(['kind'=>'platform','status'=>'suspended'],true),'suspended admin denied');
pass(!tenant_voice_entitlement_allows(null,true),'unknown account denied');
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
