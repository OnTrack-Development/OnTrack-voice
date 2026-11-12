<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')throw new RuntimeException('CLI only');
$tmp=sys_get_temp_dir().'/fish-circuit-'.bin2hex(random_bytes(8));
function private_path():string {return $GLOBALS['test_private'];}
$GLOBALS['test_private']=$tmp;
require_once __DIR__.'/../saas/console/device/v1/FishTtsFallbackPolicy.php';
function chk(bool $ok,string $name):void {
    if(!$ok)throw new RuntimeException('FISH_FAILOVER_TEST_FAIL: '.$name);
}
try {
    chk(FishTtsFallbackPolicy::classify(402,'{"error":"Insufficient balance"}')==='premium_tts_credit_exhausted','402 credit');
    chk(FishTtsFallbackPolicy::classify(403,'{"message":"Insufficient API credits"}')==='premium_tts_credit_exhausted','403 credit');
    chk(FishTtsFallbackPolicy::classify(429,'{"error":"credit limit exceeded"}')==='premium_tts_credit_exhausted','429 credit text');
    chk(FishTtsFallbackPolicy::classify(429,'{"error":"too many concurrent requests"}')==='premium_tts_rate_limited','429 concurrency distinct from billing');
    chk(FishTtsFallbackPolicy::classify(401,'bad token')==='premium_tts_provider_unavailable','provider 401');
    chk(FishTtsFallbackPolicy::classify(400,'invalid reference_id')==='premium_tts_generation_failed','bad input not credit');
    chk(FishTtsFallbackPolicy::clientHttp('premium_tts_credit_exhausted')===402,'billing HTTP must remain typed');
    chk(FishTtsFallbackPolicy::clientHttp('premium_tts_rate_limited')===429,'rate limit HTTP must remain typed');
    chk(FishTtsFallbackPolicy::ttl('premium_tts_credit_exhausted')===900,'bounded circuit breaker');
    chk(FishTtsFallbackPolicy::ttl('premium_tts_rate_limited')===45,'short concurrency hold');
    $a=FishTtsFallbackPolicy::fingerprint('encrypted-key-1');
    $b=FishTtsFallbackPolicy::fingerprint('encrypted-key-2');
    chk(FishTtsFallbackPolicy::active($a)===null,'initially clean');
    FishTtsFallbackPolicy::mark($a,'premium_tts_credit_exhausted');
    chk(FishTtsFallbackPolicy::active($a)==='premium_tts_credit_exhausted','credit circuit active');
    chk(FishTtsFallbackPolicy::active($b)===null,'new Fish key bypasses old cooldown');
    FishTtsFallbackPolicy::mark($b,'premium_tts_rate_limited');
    chk(FishTtsFallbackPolicy::active($b)==='premium_tts_rate_limited','new key rate circuit');
    $files=glob($tmp.'/voice-lab/*');
    foreach($files?:[] as $path){if(is_file($path))unlink($path);}
    @rmdir($tmp.'/voice-lab');@rmdir($tmp);
    echo "FISH_TTS_CREDIT_AND_LIMITED_FAILOVER_TEST_PASS\n";
}finally{
    foreach(glob($tmp.'/voice-lab/*')?:[] as $path)if(is_file($path))@unlink($path);
    @rmdir($tmp.'/voice-lab');@rmdir($tmp);
}
