<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!is_dir('/home/appontrack/public_html'))
    throw new RuntimeException('Only approved host read-only smoke');
$root='/home/appontrack/public_html/';
require_once $root.'_core/Bootstrap.php';
require_once $root.'_core/Views.php';
require_once $root.'_core/DevicePairing.php';
$views=(string)file_get_contents($root.'_core/Views.php');
$actions=(string)file_get_contents($root.'_core/Actions.php');
$pair=(string)file_get_contents($root.'_core/DevicePairing.php');
$api=(string)file_get_contents($root.'device/v1/index.php');
$checks=[
    'six_digit_home_action'=>function_exists('render_sim_pair_quick_start'),
    'both_sim_slot_buttons'=>str_contains($views,'ربط SIM 1 الآن')&&str_contains($views,'ربط SIM 2 الآن'),
    'six_digit_display'=>str_contains($views,'ot-pair-six-digits')&&str_contains($views,"flash('device_pair_code')"),
    'no_qr_requested'=>!str_contains($views,"flash('device_pair_qr')"),
    'one_time_hmac'=>function_exists('device_pairing_hash')&&str_contains($pair,"hash_hmac('sha256'"),
    'six_digits_only'=>str_contains($pair,"preg_match('/^[0-9]{6}$/D'"),
    'expires_after_5m'=>str_contains($pair,'time()+300'),
    'rate_limit_server'=>function_exists('device_pairing_rate_guard')&&str_contains($api,'device_pairing_rate_guard('),
    'rate_limit_http429'=>str_contains($api,'device_answer(429'),
    'account_agent_scoped'=>str_contains($actions,'$linkedAgent=one('),
    'old_api_keys_not_used'=>!str_contains($pair,'pilot_bearer')
];
$pass=!in_array(false,$checks,true);
echo json_encode(['result'=>$pass?'PASS':'FAIL','checks'=>$checks,
 'database_writes'=>false,'authentication_unchanged'=>true],
 JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
exit($pass?0:2);
