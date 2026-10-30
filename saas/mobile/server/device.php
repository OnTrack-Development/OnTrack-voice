<?php
declare(strict_types=1);
require __DIR__.'/common.php';
mobile_require_device();
if (!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)) mobile_json(['ok'=>false,'error'=>'method'],405);
$settings=mobile_settings();
mobile_json(['ok'=>true,'bridge_version'=>'0.1.3','settings'=>$settings,
    'session_path'=>'saas/mobile/session.php','heartbeat_seconds'=>5]);
