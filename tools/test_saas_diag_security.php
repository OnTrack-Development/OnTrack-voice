<?php
declare(strict_types=1);
require __DIR__.'/../api/DiagnosticsSafety.php';
function test_diag_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('DIAG_SECURITY_FAILED '.$message);
}
$realKey='AIzaTESTKEY_very_secret_1234567890';
$bad='request failed Bearer hunter2 api_key=mysecret password=hidden '.$realKey;
$safe=ontrack_diag_scrub($bad,$realKey);
foreach (['hunter2','mysecret','hidden',$realKey] as $leak)
    test_diag_assert(!str_contains($safe,$leak), 'secret redaction: '.$leak);
test_diag_assert(ontrack_diag_transport_error('Operation timed out')==='timeout','timeout category');
test_diag_assert(ontrack_diag_transport_error('SSL certificate verify failed')==='tls_failure','TLS category');
test_diag_assert(ontrack_diag_transport_error('Could not resolve host')==='dns_failure','DNS category');
test_diag_assert(ontrack_diag_transport_error('')===null,'no false error');
$source=(string)file_get_contents(__DIR__.'/../api/diag.php');
test_diag_assert(str_contains($source,'CURLOPT_FOLLOWLOCATION => false'),'no authenticated redirect');
test_diag_assert(str_contains($source,'CURLOPT_PROTOCOLS => CURLPROTO_HTTPS'),'HTTPS only');
test_diag_assert(str_contains($source,'32768'),'bounded upstream response');
test_diag_assert(!str_contains($source,"mb_substr(\$raw,0,500"),'no raw Gemini response output');
test_diag_assert(!str_contains($source,"mb_substr(\$raw,0,250"),'no raw HF response output');
test_diag_assert(str_contains($source,"ontrack_maintenance_require('GET')"),'auth preserved');
echo "SAAS_DIAGNOSTICS_HTTPS_AND_REDACTION_PASS\n";
