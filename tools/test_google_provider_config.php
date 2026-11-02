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
echo "GOOGLE_PROVIDER_CONFIG_PASS\n";
