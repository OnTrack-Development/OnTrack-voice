<?php
declare(strict_types=1);

/**
 * Maintenance endpoints are NOT public demo APIs.
 *
 * Production operator provisions one SHA-256 digest outside public_html in
 * /home/appontrack/.ontrack-maintenance/token.sha256 (0600, directory 0700).
 * Legacy authorized clients can use the SAME token via X-Maintenance-Token
 * after its digest is provisioned; never transmit it as GET or POST fields.
 */
function ontrack_maintenance_private_digest_file(string $file): ?string {
    // A readable digest is not sufficient: a world-readable file or symlink
    // defeats the operator's private maintenance credential store.
    $dir=dirname($file);
    clearstatcache(true,$file);
    if(!is_file($file)||is_link($file)||!is_readable($file)||
       !is_dir($dir)||is_link($dir))return null;
    $fileMode=@fileperms($file);$dirMode=@fileperms($dir);
    if(!is_int($fileMode)||!is_int($dirMode)||
       ($fileMode & 0077)!==0||($dirMode & 0077)!==0)
        return null;
    $configured=trim((string)@file_get_contents($file,false,null,0,80));
    return preg_match('/\A[a-f0-9]{64}\z/D',$configured) ? $configured : null;
}
function ontrack_maintenance_digest(): ?string {
    $configured=trim((string)(getenv('ONTRACK_MAINTENANCE_TOKEN_SHA256')?:''));
    if($configured==='') {
        return ontrack_maintenance_private_digest_file(
            '/home/appontrack/.ontrack-maintenance/token.sha256');
    }
    return preg_match('/\A[a-f0-9]{64}\z/D',$configured) ? $configured : null;
}
function ontrack_maintenance_authorized(array $server, ?string $digest): bool {
    if($digest===null)return false;
    $supplied=(string)($server['HTTP_X_MAINTENANCE_TOKEN']??'');
    if(strlen($supplied)<24 || strlen($supplied)>256 ||
       !preg_match('/\A[A-Za-z0-9._~\-]{24,256}\z/D',$supplied))return false;
    return hash_equals($digest,hash('sha256',$supplied));
}
function ontrack_maintenance_require(string $method): void {
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
    $actual=(string)($_SERVER['REQUEST_METHOD']??'GET');
    if($actual!==$method){
        header('Allow: '.$method);
        ontrack_maintenance_fail(405,'method_not_allowed');
    }
    // Fail closed when the credential has not yet been provisioned.
    // Do not emit the expected digest or supplied token in logs/errors.
    $digest=ontrack_maintenance_digest();
    if($digest===null)ontrack_maintenance_fail(503,'maintenance_not_configured');
    if(!ontrack_maintenance_authorized($_SERVER,$digest))
        ontrack_maintenance_fail(401,'unauthorized');
}
function ontrack_maintenance_fail(int $code,string $reason): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>$reason],JSON_UNESCAPED_SLASHES);
    exit;
}
