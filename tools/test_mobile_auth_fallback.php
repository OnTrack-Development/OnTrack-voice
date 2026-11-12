<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/mobile/server/common.php';
function auth_check(bool $ok,string $message):void {
    if(!$ok)throw new RuntimeException('MOBILE_AUTH_FALLBACK_FAIL '.$message);
}
$dir=sys_get_temp_dir().'/ontrack-mobile-auth-'.bin2hex(random_bytes(6));
if(!mkdir($dir,0700))throw new RuntimeException('fixture_directory');
$legacy=$dir.'/legacy.sha256';
$private=$dir.'/private.sha256';
$old=getenv('ONTRACK_MOBILE_AUTH_SHA256');
$key='ot_pilot_regression_credential_1234567890';
$legacyDigest=hash('sha256',$key);
try {
    file_put_contents($legacy,$legacyDigest);
    putenv('ONTRACK_MOBILE_AUTH_SHA256');
    auth_check(mobile_auth_digest($legacy,$private)===$legacyDigest,'missing private uses legacy');
    $privateDigest=hash('sha256','replacement_credential_123456789');
    file_put_contents($private,$privateDigest);
    chmod($private,0600);
    auth_check(mobile_auth_digest($legacy,$private)===$privateDigest,'private takes precedence');
    file_put_contents($private,'invalid');
    auth_check(mobile_auth_digest($legacy,$private)===null,'invalid private fails closed');
    putenv('ONTRACK_MOBILE_AUTH_SHA256='.$legacyDigest);
    auth_check(mobile_auth_digest($legacy,$private)===$legacyDigest,'environment takes precedence');
    auth_check(mobile_authorized($key),'environment bearer still accepted');
    auth_check(!mobile_authorized('wrong_credential_123456789012345'),'invalid bearer denied');
    putenv('ONTRACK_MOBILE_AUTH_SHA256=invalid');
    auth_check(mobile_auth_digest($legacy,$private)===null,'invalid environment fails closed');
} finally {
    if($old===false)putenv('ONTRACK_MOBILE_AUTH_SHA256');
    else putenv('ONTRACK_MOBILE_AUTH_SHA256='.$old);
    @unlink($private);@unlink($legacy);@rmdir($dir);
}
echo "MOBILE_AUTH_ENV_PRIVATE_LEGACY_FALLBACK_PASS\n";

