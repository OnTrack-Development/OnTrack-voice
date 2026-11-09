<?php
declare(strict_types=1);
require_once __DIR__.'/MaintenanceAuth.php';

/**
 * Browser diagnostics stay available to an authorized operator, but do not
 * expose the Omnira tester as an anonymous publicly billable proxy.
 */
function ontrack_maintenance_browser_authorized(array $server, ?string $digest): bool {
    $user=(string)($server['PHP_AUTH_USER']??'');
    $password=(string)($server['PHP_AUTH_PW']??'');
    if($user==='' && $password==='') {
        $header=(string)($server['HTTP_AUTHORIZATION']??$server['REDIRECT_HTTP_AUTHORIZATION']??'');
        if(preg_match('/\ABasic ([A-Za-z0-9+\/=]+)\z/D',$header,$match)){
            $raw=base64_decode($match[1],true);
            if(is_string($raw)&&strlen($raw)<300 && str_contains($raw,':'))
                [$user,$password]=explode(':',$raw,2);
        }
    }
    if(!hash_equals('ontrack-maintenance',$user))return false;
    return ontrack_maintenance_authorized(['HTTP_X_MAINTENANCE_TOKEN'=>$password],$digest);
}
function ontrack_maintenance_browser_require(): string {
    header('Cache-Control: no-store, private');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    if(!ontrack_maintenance_browser_authorized($_SERVER,ontrack_maintenance_digest())){
        header('WWW-Authenticate: Basic realm="OnTrack Maintenance", charset="UTF-8"');
        ontrack_maintenance_fail(401,'maintenance_authentication_required');
    }
    if(session_status()!==PHP_SESSION_ACTIVE){
        ini_set('session.use_strict_mode','1');
        session_name('ONTRACKMAINT');
        $https=!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
        session_set_cookie_params(['lifetime'=>0,'path'=>'/', 'secure'=>$https,
            'httponly'=>true,'samesite'=>'Strict']);
        if(!session_start())ontrack_maintenance_fail(503,'maintenance_session_unavailable');
    }
    $nonce=(string)($_SESSION['maintenance_csrf']??'');
    if(!preg_match('/\A[a-f0-9]{64}\z/D',$nonce)){
        $nonce=bin2hex(random_bytes(32));
        $_SESSION['maintenance_csrf']=$nonce;
    }
    return $nonce;
}
function ontrack_maintenance_browser_csrf(array $server,string $nonce):bool{
    $sent=(string)($server['HTTP_X_ONTRACK_MAINTENANCE_CSRF']??'');
    $site=strtolower((string)($server['HTTP_SEC_FETCH_SITE']??''));
    if($site==='cross-site')return false;
    return preg_match('/\A[a-f0-9]{64}\z/D',$sent)===1 && hash_equals($nonce,$sent);
}
