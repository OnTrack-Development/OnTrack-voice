<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(3);
require '/home/appontrack/public_html/_core/Bootstrap.php';
require '/home/appontrack/public_html/_core/Views.php';
require '/home/appontrack/public_html/_core/IoPremiumProvider.php';
startsession();
init_schema();
$platform=nav_entries('platform');
$reseller=nav_entries('reseller');
$customer=nav_entries('customer');
$ui=file_get_contents('/home/appontrack/public_html/_core/Views.php');
$actions=file_get_contents('/home/appontrack/public_html/_core/Actions.php');
$voice=one("SELECT id,ready,status FROM voices WHERE code='io.premium'");
ob_start();
$html=render_io_premium_admin_provider();
$accidental=(string)ob_get_clean();
$profile=io_premium_admin_state();
$pass=isset($platform['voices'],$reseller['voices'],$customer['voices'])
    &&str_contains($ui,"select_html('الصوت المستخدم لهذا المساعد','voice'")
    &&substr_count($ui,"select_html('الصوت المستخدم لهذا المساعد','voice'")===1
    &&str_contains($actions,"case 'save_io_premium_provider':")
    &&str_contains($html,'name="io_fish_api_key"')
    &&str_contains($html,'name="io_voice_id"')
    &&str_contains($html,'name="io_fish_model"')
    &&strlen($accidental)===0
    &&$voice && $profile['configured'];
// No API key, no Voice ID, and no form body sent to CI logs.
echo json_encode(['ui_status'=>$pass?'PASS':'FAIL',
    'admin_voices_nav'=>isset($platform['voices']),
    'reseller_voices_nav'=>isset($reseller['voices']),
    'user_voices_nav'=>isset($customer['voices']),
    'agent_voice_picker'=>str_contains($ui,"select_html('الصوت المستخدم لهذا المساعد','voice'"),
    'premium_configured'=>$profile['configured'],
    'premium_ready'=>(int)($voice['ready']??0)===1,
    'provider_form_exists'=>str_contains($html,'name="io_fish_api_key"'),
    'secrets_not_logged'=>true],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($pass?0:2);
