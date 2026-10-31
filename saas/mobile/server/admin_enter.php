<?php
declare(strict_types=1);
require __DIR__.'/common.php';
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, private');
header('Content-Security-Policy: default-src \'none\'; form-action \'self\'');
$ticket=(string)($_GET['t']??'');
if (!preg_match('/^[a-f0-9]{64}$/D',$ticket)) {http_response_code(401);exit('Invalid sign-in link');}
$path=MOBILE_STORE.'/admin-ticket-'.hash('sha256',$ticket).'.json';
$used=MOBILE_STORE.'/admin-used-'.bin2hex(random_bytes(12));
if (!@rename($path,$used)) {http_response_code(401);exit('Sign-in link expired or already used');}
$contents=@file_get_contents($used);
@unlink($used);
$data=json_decode((string)$contents,true);
if (!is_array($data) || !isset($data['expires']) || time()>(int)$data['expires']) {http_response_code(401);exit('Sign-in link expired');}
session_name('ontrack_mobile_admin');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Strict','path'=>'/saas/mobile/']);
session_start();
session_regenerate_id(true);
$_SESSION['mobile_admin']=true;
$_SESSION['csrf']??=bin2hex(random_bytes(24));
header('Location: /saas/mobile/',true,303);
exit;
