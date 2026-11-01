<?php
declare(strict_types=1);
/** CI-only router for the real preview endpoint; never deployed under saas/. */
$_SERVER['HTTPS']='on'; // Simulate the HTTPS-terminating host in loopback PHP -S.
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if($path==='/saas/console/__fixture_login'){
    require __DIR__.'/../saas/console/_core/Bootstrap.php';
    startsession();
    init_schema();
    $accountId=(int)($_GET['account']??0);
    $member=one("SELECT m.user_id FROM memberships m JOIN users u ON u.id=m.user_id
        WHERE m.account_id=? AND m.role IN ('owner','admin') AND u.status='active'
        ORDER BY m.user_id LIMIT 1",[$accountId]);
    if(!$member){http_response_code(404);exit('not_found');}
    $_SESSION=['uid'=>(int)$member['user_id'],'account_id'=>$accountId];
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['csrf'=>csrf(),'account_id'=>$accountId]);
    exit;
}
if($path==='/saas/console/'||$path==='/saas/console/index.php'){
    require __DIR__.'/../saas/console/index.php';
    exit;
}
require __DIR__.'/../saas/console/flow-preview.php';
