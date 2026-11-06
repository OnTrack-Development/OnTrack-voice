<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/VoiceConnectorCache.php';
if(is_dir('/home/appontrack'))throw new RuntimeException('CI fixture only');
function cached_check(bool $ok,string $what):void {
    if(!$ok)throw new RuntimeException('SITE_PUBLIC_API_CACHE_FAIL '.$what);
}
seed_console();
$site=(int)one("SELECT id FROM sites WHERE account_id=4 AND status='active' LIMIT 1")['id'];
$conn=voice_connector_add(4,$site,'whmcs','Public hosting catalog',
    'https://catalog.example.com','/includes/api.php','whmcs','id:secret');
execute("UPDATE voice_data_connectors SET status='verified' WHERE id=? AND account_id=4",[$conn]);
$fake=static fn()=>['result'=>'success','products'=>['product'=>[
    ['name'=>'Demo Hosting Plan','description'=>'100 GB monthly','price'=>'199.00',
     'email'=>'testprivate@example.com','password'=>'not-in-cache','domain'=>'private.example'],
]]];
$cache=voice_connector_cache_refresh(4,$site,$conn,60,$fake);
cached_check($cache['status']==='refreshed'&&$cache['public_only'],'read-only snapshot');
$content=voice_connector_cache_context(4,$site);
cached_check(str_contains($content,'Demo Hosting Plan'),'public catalogue surfaced');
cached_check(str_contains($content,'199.00'),'public price surfaced');
cached_check(!str_contains($content,'testprivate@example.com')&&
    !str_contains($content,'not-in-cache')&&
    !str_contains($content,'private.example'),'PII and secrets excluded');
cached_check(voice_connector_cache_context(5,$site)==='','cross tenant excluded');
$wrong=false;try{voice_connector_cache_refresh(5,$site,$conn,60,$fake);}
catch(InvalidArgumentException){$wrong=true;}
cached_check($wrong,'cross tenant refresh denied');
$wrong=false;try{voice_connector_cache_refresh(4,$site,$conn,1,$fake);}
catch(InvalidArgumentException){$wrong=true;}
cached_check($wrong,'unsafe TTL denied');
execute("UPDATE voice_connector_public_cache SET expires_at=? WHERE connector_id=? AND account_id=4",
    [gmdate('Y-m-d H:i:s',time()-60),$conn]);
cached_check(!str_contains(voice_connector_cache_context(4,$site),'Demo Hosting Plan'),
    'expired facts never read');
$bad=false;try{voice_connector_cache_refresh(4,$site,$conn,60,
    static fn()=>['result'=>'error','products'=>['product'=>[]]]);}
catch(RuntimeException){$bad=true;}
cached_check($bad,'provider error cannot refresh cache');
cached_check(!str_contains(voice_connector_cache_context(4,$site),'Demo Hosting Plan'),
    'failed refresh cannot extend stale information');
cached_check(count(voice_connector_cache_rows(4,$site))===1,'cache UI status available');

// The scheduled worker refreshes only verified public catalogue snapshots.
$site5=(int)one("SELECT id FROM sites WHERE account_id=5 AND status='active' LIMIT 1")['id'];
$conn5=voice_connector_add(5,$site5,'whmcs','Tenant 5 catalogue',
    'https://catalog.example.org','/includes/api.php','whmcs','id:secret');
execute("UPDATE voice_data_connectors SET status='verified' WHERE id=? AND account_id=5",[$conn5]);
$batch=voice_connector_cache_due_all(2,static fn(array $connector):array=>[
    'result'=>'success','products'=>['product'=>[[
        'name'=>'Tenant '.(int)$connector['account_id'].' public product',
        'description'=>'Verified public catalogue'
    ]]]
]);
cached_check($batch['checked']===2&&$batch['refreshed']===2&&$batch['failed']===0,
    'bounded public snapshot auto refresh');
cached_check(str_contains(voice_connector_cache_context(4,$site),'Tenant 4 public product'),
    'first tenant refreshed');
cached_check(str_contains(voice_connector_cache_context(5,$site5),'Tenant 5 public product'),
    'second tenant refreshed');
cached_check(!str_contains(voice_connector_cache_context(4,$site),'Tenant 5 public product')&&
    !str_contains(voice_connector_cache_context(5,$site5),'Tenant 4 public product'),
    'scheduled cache never crosses tenant');
cached_check(voice_connector_cache_due_all(2,static function():never {
    throw new RuntimeException('unexpected network call');
})['checked']===0,'fresh snapshots are never re-requested every tick');
$old=gmdate('Y-m-d H:i:s',time()-60);
execute('UPDATE voice_connector_public_cache SET expires_at=? WHERE connector_id=? AND account_id=4',
    [$old,$conn]);
execute('UPDATE voice_connector_public_refresh_schedule SET next_attempt_at=?
    WHERE connector_id=? AND account_id=4',[$old,$conn]);
$down=voice_connector_cache_due_all(1,static function():never {
    throw new RuntimeException('synthetic api offline');
});
cached_check($down['checked']===1&&$down['failed']===1,'public cache failure reported');
$retry=one('SELECT failures,next_attempt_at FROM voice_connector_public_refresh_schedule
    WHERE connector_id=? AND account_id=4',[$conn]);
cached_check((int)$retry['failures']===1&&$retry['next_attempt_at']>now(),
    'failed public API gets a retry backoff');
cached_check(!str_contains(voice_connector_cache_context(4,$site),'Tenant 4 public product'),
    'failed refresh cannot make expired snapshot available');
cached_check(voice_connector_cache_due_all(1,static function():never {
    throw new RuntimeException('unexpected immediate retry');
})['checked']===0,'failed provider is not hammered on every cron tick');
$bad=false;try{voice_connector_cache_due_all(9);}catch(InvalidArgumentException){$bad=true;}
cached_check($bad,'global refresh budget enforced');

echo "VOICE_PUBLIC_API_CACHE_TTL_AND_TENANT_PASS\n";
