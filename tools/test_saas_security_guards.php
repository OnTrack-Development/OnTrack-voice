<?php
declare(strict_types=1);
require_once __DIR__.'/../api/MaintenanceAuth.php';
require_once __DIR__.'/../api/MaintenanceBrowser.php';
require_once __DIR__.'/../saas/console/_core/ApiWriteGuard.php';

function sec_check(bool $condition,string $label):void {
    if(!$condition)throw new RuntimeException('SAAS_SECURITY_GUARD_FAIL '.$label);
}
$credential='ot_live_security_regression_example_2026';
$digest=hash('sha256',$credential);
sec_check(ontrack_maintenance_authorized(['HTTP_X_MAINTENANCE_TOKEN'=>$credential],$digest),
    'maintenance accepts valid header credential');
sec_check(!ontrack_maintenance_authorized(['HTTP_X_MAINTENANCE_TOKEN'=>'bad'],$digest),
    'maintenance rejects invalid header credential');
sec_check(!ontrack_maintenance_authorized(['QUERY_STRING'=>'token='.$credential],$digest),
    'maintenance never accepts query token');
sec_check(!ontrack_maintenance_authorized(['POST_TOKEN'=>$credential],$digest),
    'maintenance never accepts body token');
sec_check(!ontrack_maintenance_authorized(['HTTP_X_MAINTENANCE_TOKEN'=>$credential],null),
    'maintenance fails closed without provisioned digest');
sec_check(ontrack_maintenance_browser_authorized([
    'PHP_AUTH_USER'=>'ontrack-maintenance','PHP_AUTH_PW'=>$credential],$digest),
    'browser tester available to authenticated operator');
sec_check(ontrack_maintenance_browser_authorized([
    'HTTP_AUTHORIZATION'=>'Basic '.base64_encode('ontrack-maintenance:'.$credential)],$digest),
    'browser Basic authorization fallback');
sec_check(!ontrack_maintenance_browser_authorized([
    'HTTP_AUTHORIZATION'=>'Bearer '.$credential],$digest),
    'browser rejects bearer in Basic endpoint');
sec_check(!ontrack_maintenance_browser_authorized([
    'PHP_AUTH_USER'=>'wrong-user','PHP_AUTH_PW'=>$credential],$digest),
    'browser rejects wrong user');
$nonce=str_repeat('a',64);
sec_check(ontrack_maintenance_browser_csrf(['HTTP_X_ONTRACK_MAINTENANCE_CSRF'=>$nonce],$nonce),
    'same-origin browser request accepted');
sec_check(!ontrack_maintenance_browser_csrf([
    'HTTP_X_ONTRACK_MAINTENANCE_CSRF'=>$nonce,
    'HTTP_SEC_FETCH_SITE'=>'cross-site'],$nonce),'cross-site browser request denied');
sec_check(!ontrack_maintenance_browser_csrf([], $nonce), 'missing browser nonce denied');

$folder=sys_get_temp_dir().'/ot-saas-rate-'.bin2hex(random_bytes(7));
try{
    $stamp=1762675200;
    sec_check(saas_api_write_quota($folder,'acct1|key1|lead.create',$stamp,60,2),
        'first write accepted');
    sec_check(saas_api_write_quota($folder,'acct1|key1|lead.create',$stamp,60,2),
        'second write accepted');
    sec_check(!saas_api_write_quota($folder,'acct1|key1|lead.create',$stamp,60,2),
        'third write denied by window');
    sec_check(saas_api_write_quota($folder,'acct1|key2|lead.create',$stamp,60,2),
        'distinct key independent quota');
    sec_check(saas_api_write_quota($folder,'acct2|key1|lead.create',$stamp,60,2),
        'distinct tenant independent quota');
    sec_check(saas_api_write_quota($folder,'acct1|key1|lead.create',$stamp+60,60,2),
        'next window resumes');
    $files=glob($folder.'/*.count')?:[];
    sec_check(count($files)===4,'each window/key has its own counter');
    foreach($files as $file){
        sec_check(!str_contains(basename($file),'acct1') &&
            !str_contains((string)file_get_contents($file),'acct1'),
            'quota file does not expose tenant identity');
    }
    sec_check(!saas_api_write_quota('/dev/null/test','key',$stamp,60,2),
        'quota fails closed when directory is unusable');
}finally{
    foreach(glob($folder.'/*')?:[] as $file)@unlink($file);
    @rmdir($folder);
}
$root=dirname(__DIR__);
$setup=(string)file_get_contents($root.'/api/setup_demo.php');
$diag=(string)file_get_contents($root.'/api/diag.php');
$tester=(string)file_get_contents($root.'/omnira_api_tester.php');
$api=(string)file_get_contents($root.'/saas/console/api/v1/index.php');
sec_check(str_contains($setup,"ontrack_maintenance_require('POST')"),
    'maintenance setup requires header-authenticated POST');
sec_check(str_contains($diag,"ontrack_maintenance_require('GET')"),
    'diagnostics require authenticated header with GET semantics');
sec_check(!str_contains($setup,'TOKEN_HASH') && !str_contains($diag,'TOKEN_HASH'),
    'no shared hard-coded maintenance digest left in endpoints');
sec_check(!str_contains($setup,"\$_GET['key']") && !str_contains($setup,"\$_GET['token']") &&
    !str_contains($diag,"\$_GET['token']"), 'no credentials accepted from URL');
sec_check(str_contains($tester,'ontrack_maintenance_browser_require()') &&
    str_contains($tester,'ontrack_maintenance_browser_csrf($_SERVER,$maintenanceCsrf)'),
    'tester authentication and anti-CSRF added');
sec_check(str_contains($tester,"data-test=\"stream\"") &&
    str_contains($tester,"data-test=\"tools\"") && str_contains($tester,"data-test=\"models\""),
    'existing tester buttons preserved');
sec_check(str_contains($api,"saas_api_write_allowed((int)\$a['id'],(int)\$grant['id'],'lead.create')"),
    'lead create remains authenticated and bounded');
echo "SAAS_MAINTENANCE_AND_LEAD_SECURITY_NONREGRESSION_PASS\n";
