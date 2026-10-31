<?php
declare(strict_types=1);
require __DIR__.'/common.php';
mobile_require_device();
if ($_SERVER['REQUEST_METHOD']!=='POST') mobile_json(['ok'=>false,'error'=>'POST_only'],405);
if (!is_dir(MOBILE_STORE)) mobile_json(['ok'=>false,'error'=>'private_store_missing'],503);
$ticket=bin2hex(random_bytes(32));
$hash=hash('sha256',$ticket);
$file=MOBILE_STORE.'/admin-ticket-'.$hash.'.json';
$payload=json_encode(['expires'=>time()+35],JSON_THROW_ON_ERROR);
$handle=@fopen($file,'x');
if ($handle===false) mobile_json(['ok'=>false,'error'=>'ticket_unavailable'],503);
try {
    chmod($file,0600);
    if (fwrite($handle,$payload)!==strlen($payload)) throw new RuntimeException('ticket_write');
    fflush($handle);
} finally {fclose($handle);}
header('Referrer-Policy: no-referrer');
mobile_json(['ok'=>true,'url'=>'https://app.ontrackegy.com/saas/mobile/admin_enter.php?t='.$ticket,'expires_seconds'=>35]);
