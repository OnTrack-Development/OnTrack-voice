<?php
declare(strict_types=1);
require __DIR__.'/common.php';
mobile_require_device();
if ($_SERVER['REQUEST_METHOD']!=='POST') mobile_json(['ok'=>false,'error'=>'POST_only'],405);
$mobile=mobile_settings();
if (!$mobile['enabled']) mobile_json(['ok'=>false,'error'=>'mobile_connection_disabled'],409);
// Reuse the deployed session builder and its server-only Gemini key/knowledge.
$ontrackMobileRequest=['mode'=>'demo'];
$ontrackMobileInstruction=$mobile['instruction'];
require dirname(__DIR__,2).'/api/live_session.php';
