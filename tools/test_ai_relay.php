<?php
declare(strict_types=1);
if(is_dir('/home/appontrack'))throw new RuntimeException('CI_ONLY');
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/AiRelay.php';
seed_console();
function relay_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException('RELAY_FAIL '.$message);}
$platform=ai_relay_platform();
$a=ai_relay_add($platform,'voice','google_live','Google Primary','gemini-3.8-live','','test-key-100');
$b=ai_relay_add($platform,'voice','google_live','Google Backup','gemini-3.8-live','','test-key-200');
$c=ai_relay_add($platform,'assistant','google_text','Assistant Google','gemini-3.8-flash','','test-key-300');
relay_check(count(ai_relay_rows('voice'))===2&&count(ai_relay_rows('assistant'))===1,'separate groups');
relay_check(!str_contains((string)ai_relay_one($a)['secret_enc'],'test-key-100'),'encrypted at rest');
$sequence=[];
$x=ai_relay_run('voice',static function(array $entry,string $key)use(&$sequence){
    $sequence[]=(int)$entry['id'];
    if($key==='test-key-100')throw new RuntimeException('voice_provider_http_429');
    return 'issued-token';
});
relay_check($x==='issued-token'&&$sequence===[$a,$b],'failover');
relay_check(ai_relay_one($a)['last_status']==='429','429 classified');
relay_check(strtotime((string)ai_relay_one($a)['cooldown_until'])>=time()+890,'15m cooldown');
relay_check(ai_relay_one($c)['cooldown_until']===null,'assistant independent');
$next=[];ai_relay_run('voice',static function(array $entry)use(&$next){$next[]=$entry['id'];return true;});
relay_check($next===[$b],'skip cooldown');
ai_relay_modify($a,'reset');
relay_check(ai_relay_one($a)['cooldown_until']===null,'reset cooldown');
ai_relay_modify($b,'up');
relay_check((int)ai_relay_rows('voice')[0]['id']===$b,'priority reorder');
foreach(['http://example.com/v1/chat/completions','https://127.0.0.1/v1/chat/completions',
 'https://localhost/v1/chat/completions','https://user:pass@example.com/v1/chat/completions',
 'https://example.com/v1/chat/completions?key=secret'] as $url){
 $bad=false;try{ai_relay_endpoint($url);}catch(InvalidArgumentException){$bad=true;}
 relay_check($bad,'unsafe URL rejected');
}
relay_check(ai_relay_endpoint('https://provider.example/v1/chat/completions')==='https://provider.example/v1/chat/completions','valid HTTPS');
relay_check(ai_relay_parse_object('{"nodes":[],"edges":[]}')===['nodes'=>[],'edges'=>[]],'JSON parser');
ai_relay_modify($b,'delete');relay_check(count(ai_relay_rows('voice'))===1,'remove individual entry');
echo "AI_RELAY_VOICE_ASSISTANT_COOLDOWN_SECURITY_PASS\n";
