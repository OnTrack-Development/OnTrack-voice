<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||is_dir('/home/appontrack'))
    throw new RuntimeException('CI_ONLY_SIX_DIGIT_PAIRING');
if(!getenv('ONTRACK_CI_NAMESPACE'))
    putenv('ONTRACK_CI_NAMESPACE=sim6'.bin2hex(random_bytes(8)));
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
require_once __DIR__.'/../saas/console/_core/DevicePairing.php';
seed_console();
function six_ok(bool $ok,string $msg):void{
    if(!$ok)throw new RuntimeException('SIX_DIGIT_PAIRING_TEST_FAIL: '.$msg);
}
$owner=one("SELECT id FROM users WHERE email='admin@ontrackegy.com'");
$account=4;$site=1;$agent=1;
$code=device_pairing_create($account,$site,$agent,(int)$owner['id'],0);
six_ok((bool)preg_match('/^[0-9]{6}$/D',$code),'six digits including leading zeros');
six_ok(!str_contains(json_encode(all('SELECT * FROM device_pairings WHERE account_id=?',[$account])),$code),
    'no raw code in DB');
$hash=device_pairing_hash($code,intdiv(time(),300));
six_ok((bool)one('SELECT id FROM device_pairings WHERE code_hash=?',[$hash]),'server keyed HMAC code');
$wrong=false;
try{device_pairing_redeem($code,'sim6-test-install-0001',1,'Wrong physical SIM');}
catch(InvalidArgumentException $e){$wrong=$e->getMessage()==='device_pairing_slot_mismatch';}
six_ok($wrong,'wrong SIM rejected even if digits are correct');
$linked=device_pairing_redeem($code,'sim6-test-install-0001',0,'Phone SIM1');
six_ok($linked['sim_slot']===0&&str_starts_with($linked['device_token'],'otdev_'),
    'one-time code paired SIM1');
$replay=false;
try{device_pairing_redeem($code,'sim6-test-install-0002',0,'Replay');}
catch(InvalidArgumentException $e){$replay=$e->getMessage()==='device_pairing_expired_or_used';}
six_ok($replay,'no second device after redeem');
$expired=device_pairing_create($account,$site,$agent,(int)$owner['id'],1);
execute('UPDATE device_pairings SET expires_at=? WHERE code_hash=?',[
    gmdate('Y-m-d H:i:s',time()-1),device_pairing_hash($expired,intdiv(time(),300))]);
$rejected=false;
try{device_pairing_redeem($expired,'sim6-test-install-0003',1,'Expired');}
catch(InvalidArgumentException $e){$rejected=$e->getMessage()==='device_pairing_expired_or_used';}
six_ok($rejected,'expired code cannot pair');
for($i=0;$i<20;$i++)device_pairing_rate_guard('203.0.113.41','ratelimit-install-'.str_pad((string)$i,4,'0',STR_PAD_LEFT));
$blocked=false;
try{device_pairing_rate_guard('203.0.113.41','ratelimit-install-9999');}
catch(InvalidArgumentException $e){$blocked=$e->getMessage()==='device_pairing_rate_limited';}
six_ok($blocked,'per-IP guessing capped even if installation IDs rotate');
for($i=0;$i<12;$i++)
    device_pairing_rate_guard('198.51.100.'.($i+20),'same-installation-0000123');
$blocked=false;
try{device_pairing_rate_guard('198.51.100.90','same-installation-0000123');}
catch(InvalidArgumentException $e){$blocked=$e->getMessage()==='device_pairing_rate_limited';}
six_ok($blocked,'installation guessing capped even if IP rotates');
$platform=(int)$owner['id'];
$_SESSION=['uid'=>$platform,'account_id'=>1,'console_view'=>'admin'];
ob_start();render_overview(mine());$home=(string)ob_get_clean();
six_ok(str_contains($home,'ربط الهاتف والشريحة بسهولة'),'SIM entry on platform owner home');
$_SESSION=['uid'=>(int)one("SELECT id FROM users WHERE email='user@direct.test'")['id'],
    'account_id'=>4,'console_view'=>'admin'];
ob_start();render_overview(mine());$customer=(string)ob_get_clean();
six_ok(str_contains($customer,'ربط SIM 1 الآن')&&str_contains($customer,'ربط SIM 2 الآن'),
    'customer with an agent sees both SIM pairing buttons');
$_SESSION=['uid'=>(int)one("SELECT id FROM users WHERE email='reseller@delta.test'")['id'],
    'account_id'=>2,'console_view'=>'admin'];
ob_start();render_overview(mine());$reseller=(string)ob_get_clean();
six_ok(str_contains($reseller,'ربط الهاتف والشريحة بسهولة'),'reseller sees pairing entry');
$views=(string)file_get_contents(__DIR__.'/../saas/console/_core/Views.php');
six_ok(!str_contains($views,"flash('device_pair_qr')")&&str_contains($views,"flash('device_pair_code')"),
    'site displays numeric code not QR');
six_ok(str_contains($views,'ot-pair-six-digits'),'six-digit code legible');
$native=(string)file_get_contents(__DIR__.'/../saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLinkActivity.kt');
six_ok(str_contains($native,'showQuickTenantPair()')&&str_contains($native,'performTenantPair(slot:Int,rawCode:String)'),
    'Android only asks for six digits');
six_ok(str_contains($native,'pendingTenantPair=slot to code'),'permission prompt preserves entry');
six_ok(!str_contains($native,'scanTenantQr()'),'no QR scanner required');
echo "SIX_DIGIT_SIM_PAIRING_WEB_AND_ANDROID_CONTRACT_PASS\n";
