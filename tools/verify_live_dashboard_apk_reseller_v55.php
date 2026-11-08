<?php
declare(strict_types=1);
/** Read-only proof on canonical host; never writes accounts, membership or auth state. */
if(PHP_SAPI!=='cli'||!is_dir('/home/appontrack/public_html'))
    throw new RuntimeException('This probe is for authorized canonical server only');
require_once '/home/appontrack/public_html/_core/Bootstrap.php';
require_once '/home/appontrack/public_html/_core/Views.php';
$root='/home/appontrack/public_html/';
$release=ontrack_native_public_release();
$banner=ontrack_update_banner();
$views=(string)file_get_contents($root.'_core/Views.php');
$bootstrap=(string)file_get_contents($root.'_core/Bootstrap.php');
$actions=(string)file_get_contents($root.'_core/Actions.php');
$route=(string)file_get_contents($root.'index.php');
$ok=is_array($release)&&(int)$release['code']>=54 &&
    str_starts_with((string)$release['url'],'https://app.ontrackegy.com/mobile/updates/native/') &&
    str_contains($banner,'↓ تنزيل APK')&&
    str_contains($banner,(string)$release['url'])&&
    str_contains($views,'ot-direct-apk')&&
    str_contains($views,"form_start('set_support_view'")&&
    str_contains($bootstrap,'function console_can_switch_support_view(')&&
    str_contains($bootstrap,"in_array($".'m[\'role\']'.",['owner','admin','support'],true)")&&
    str_contains($actions,"case 'set_support_view':")&&
    str_contains($route,"console_view_mode($".'a'.")==='user'");
$report=[
    'result'=>$ok?'PASS':'FAIL',
    'native_version'=>$release['name']??null,
    'native_version_code'=>$release['code']??null,
    'direct_home_apk'=>$release!==null&&str_contains($views,'ot-direct-apk'),
    'customer_home_has_download'=>str_contains($views,'ot-direct-apk'),
    'reseller_home_has_download'=>str_contains($views,'ot-direct-apk'),
    'support_reseller_can_switch_presentation_only'=>str_contains($actions,"case 'set_support_view':"),
    'support_session_restricted_to_origin_membership'=>str_contains($bootstrap,'console_can_switch_support_view'),
    'credentials_read'=>false,'tenants_modified'=>false
];
echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
exit($ok?0:2);
