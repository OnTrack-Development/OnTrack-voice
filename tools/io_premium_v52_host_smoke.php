<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(3);
require_once '/home/appontrack/public_html/_core/Bootstrap.php';
require_once '/home/appontrack/public_html/_core/Views.php';
require_once '/home/appontrack/public_html/_core/IoPremiumProvider.php';
init_schema();
$root='/home/appontrack/public_html/';
$ui=(string)file_get_contents($root.'_core/Views.php');
$actions=(string)file_get_contents($root.'_core/Actions.php');
$tts=(string)file_get_contents($root.'device/v1/tts.php');
$start=strpos($ui,'function render_voices(array $a):void{');
$end=strpos($ui,'function render_secret_controls(',$start?:0);
$portion=($start!==false&&$end!==false)?substr($ui,$start,$end-$start):'';
$state=io_premium_admin_state();
$voice=one("SELECT id,ready,status FROM voices WHERE code='io.premium'");
$policy=io_premium_access_policy();
$admin=one("SELECT id FROM accounts WHERE kind='platform' AND status='active' ORDER BY id LIMIT 1");
$nonPlatform=one("SELECT id FROM accounts WHERE kind!='platform' AND status='active' ORDER BY id LIMIT 1");
$adminAllowed=$admin?io_premium_access_allowed((int)$admin['id']):false;
$userAllowed=$nonPlatform?io_premium_access_allowed((int)$nonPlatform['id']):false;
$html=render_io_premium_admin_provider();
$pass=$voice&&$state['configured']&&
    $policy['mode']==='all'&&
    (int)$voice['ready']===1&&(string)$voice['status']==='active'&&
    $adminAllowed&&$userAllowed&&
    substr_count($portion,'render_io_premium_admin_provider();')===1&&
    substr_count($html,'name="io_fish_api_key"')===1&&
    substr_count($html,'name="io_access_mode"')===1&&
    str_contains($actions,"case 'save_io_premium_access':")&&
    str_contains($tts,'premium_voice_not_available')&&
    !str_contains($tts,'premium_voice_not_granted');
echo json_encode(['status'=>$pass?'PASS':'FAIL','platform_master_ready'=>$state['configured'],
    'access_mode'=>$policy['mode'],'platform_allowed'=>$adminAllowed,
    'first_customer_allowed'=>$userAllowed,'voice_panel_count'=>substr_count($portion,'render_io_premium_admin_provider();'),
    'fish_credentials_forms'=>substr_count($html,'name="io_fish_api_key"'),
    'global_access_forms'=>substr_count($html,'name="io_access_mode"'),
    'secret_values_exposed'=>false],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($pass?0:2);
