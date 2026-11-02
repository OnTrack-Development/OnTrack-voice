<?php
declare(strict_types=1);
require dirname(__DIR__).'/saas/console/_core/VoiceCatalog.php';
function chk(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
chk(google_key_looks_valid('AIzaSy0123456789abcde-_-example'),'normal key shape');
chk(!google_key_looks_valid(''),'empty key');
chk(!google_key_looks_valid('{"type":"service_account"}'),'JSON credentials');
chk(str_contains(google_api_failure(403),'قيود'),'403 permission vs invalid key');
chk(str_contains(google_api_failure(429),'حصة'),'429 quota explanation');
chk(str_contains(google_api_failure(0),'الاتصال'),'network explanation');
// Shape is not a substitute for calling the provider: short safe values reach Google.
chk(google_key_looks_valid('short-key'),'safe key must reach remote probe');
chk(!google_key_looks_valid("abc\\r\\nInjected: yes"),'no header injection');
$seen=false;
google_api_probe('short-key',function(string $key) use (&$seen):array{
    $seen=$key==='short-key';
    return ['status'=>200,'body'=>'{"models":[{"name":"models/test"}]}'];
});
chk($seen,'HTTP probe executed instead of preemptive key rejection');
try{
    google_api_probe('short-key',fn(string $key):array=>['status'=>403,'body'=>'{}']);
    throw new RuntimeException('403 should fail');
}catch(RuntimeException $error){chk(str_contains($error->getMessage(),'قيود'),'403 provider rejection');}
try{
    google_api_probe('short-key',fn(string $key):array=>['status'=>429,'body'=>'{}']);
    throw new RuntimeException('429 should fail');
}catch(RuntimeException $error){chk(str_contains($error->getMessage(),'حصة'),'429 quota rejection');}
echo "GOOGLE_PROVIDER_CONFIG_PASS\\n";
