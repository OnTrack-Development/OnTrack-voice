<?php
declare(strict_types=1);
if(is_dir('/home/appontrack'))throw new RuntimeException('CI_ONLY: never write hosted configuration');
if(PHP_SAPI!=='cli')throw new RuntimeException('CLI_ONLY');
if(!getenv('ONTRACK_CI_NAMESPACE'))putenv('ONTRACK_CI_NAMESPACE=ioacc'.bin2hex(random_bytes(9)));
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/IoPremiumProvider.php';
require_once __DIR__.'/../saas/console/_core/GoogleTenantKeys.php';
require_once __DIR__.'/../saas/console/_core/AiRelay.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
seed_console();
function io_check(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('IO_PREMIUM_ACCESS_FAIL: '.$label);
}
$voice=one("SELECT id FROM voices WHERE code='io.premium'");
io_check(is_array($voice),'IO Premium catalog exists');
$path=private_path().'/voice-lab/owner-1.json';
if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);
$encrypted=seal_secret('fixture-only-fish-key-not-real');
$profile=['keys'=>['fish'=>$encrypted],'settings'=>[
    'voiceId'=>str_repeat('a',32),'fishModel'=>'drama-3'
]];
file_put_contents($path,json_encode($profile,JSON_THROW_ON_ERROR));
execute("UPDATE voices SET ready=1,status='active' WHERE code='io.premium'");
io_check(io_premium_admin_state()['configured'],'valid encrypted platform profile');
io_check(io_premium_access_allowed(1),'platform owner uses own saved voice');
io_check(io_premium_access_allowed(4),'customer inherited platform voice by default');
io_check(io_premium_access_allowed(5),'reseller customer inherited same platform voice');
io_premium_admin_save_access('limited',2);
$p=io_premium_access_policy();
io_check($p['mode']==='limited'&&$p['limit']===2,'limited mode stored');
io_check(io_premium_access_allowed(1),'admin does not consume quota');
io_check(io_premium_access_allowed(2)&&io_premium_access_allowed(3),'first two active reseller slots');
io_check(!io_premium_access_allowed(4)&&!io_premium_access_allowed(5),'others rejected by limit');
io_premium_admin_save_access('off',0);
io_check(!io_premium_access_allowed(1)&&!io_premium_access_allowed(2),
    'off mode overrides even existing individual grants');
io_premium_admin_save_access('all',0);
io_check(io_premium_access_allowed(4)&&io_premium_access_allowed(7),'global access restored');
io_check(io_premium_admin_profile()['keys']['fish']===$encrypted,'saving access preserves encrypted Fish key');
try{io_premium_admin_save_access('limited',0);throw new RuntimeException('limit zero accepted');}
catch(InvalidArgumentException $expected){}
ob_start();
$render=render_io_premium_admin_provider();
io_check((string)ob_get_clean()==='','form emits no stray output');
io_check(substr_count($render,'name="io_fish_api_key"')===1,
    'one Fish key form only');
io_check(substr_count($render,'name="io_access_mode"')===1,
    'one global distribution form only');
execute("UPDATE voices SET ready=0 WHERE code='io.premium'");
io_check(!io_premium_access_allowed(4),'unready voice is always unavailable');
execute("UPDATE voices SET ready=1 WHERE code='io.premium'");
$platformKey=ai_relay_add(1,'voice','google_live','Platform fixture',
    'gemini-3.8-live','','fictional-admin-google-key');
$userKey=ai_relay_add(4,'voice','google_live','User fixture',
    'gemini-3.8-live','','fictional-user-google-key');
io_check(google_voice_pool_owner(4)===4,'enabled user BYOK selected');
ai_relay_modify($userKey,'toggle',4);
io_check(google_voice_pool_owner(4)===1,'disabled user BYOK falls back to enabled platform');
echo "IO_PREMIUM_GLOBAL_ACCESS_AND_GOOGLE_FALLBACK_PASS\n";
