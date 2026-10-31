<?php
declare(strict_types=1);
require __DIR__.'/common.php';
header('X-Frame-Options: DENY');
session_name('ontrack_mobile_admin');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Strict','path'=>'/saas/mobile/']);
session_start();
if(empty($_SESSION['mobile_admin'])) mobile_json(['ok'=>false,'error'=>'unauthorized'],401);
if($_SERVER['REQUEST_METHOD']!=='GET') mobile_json(['ok'=>false,'error'=>'method'],405);
$data=json_decode((string)@file_get_contents(MOBILE_STORE.'/call-status.json'),true);
$desired=mobile_settings();
$last=strtotime((string)($data['seen_at']??''));$fresh=$last!==false && time()-$last<15;
$applied=$fresh && (int)($data['policy_revision']??0)===(int)$desired['revision']
    && (bool)($data['agent_running']??false)===(bool)$desired['enabled'];
mobile_json(['ok'=>true,'fresh'=>$fresh,'phase'=>$fresh?(string)$data['phase']:'offline',
    'desired_enabled'=>(bool)$desired['enabled'],
    'device_agent_enabled'=>$fresh ? (bool)($data['agent_running']??false) : null,
    'synchronized'=>$applied,
    'desired_revision'=>(int)$desired['revision'],
    'agent_running'=>$fresh && !empty($data['agent_running']),
    'last_seen'=>$data['seen_at']??null,
    'policy_revision'=>(int)($data['policy_revision']??0)]);
