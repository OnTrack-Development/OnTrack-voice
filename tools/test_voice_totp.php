<?php
declare(strict_types=1);
require __DIR__.'/../saas/console/database/Seed.php';
require __DIR__.'/../saas/console/_core/TwoFactor.php';
function totp_ok(bool $condition,string $message):void {
    if(!$condition)throw new RuntimeException('TOTP_FAIL '.$message);
    echo "PASS $message\n";
}
seed_console();
$admin=one("SELECT id FROM users WHERE email='admin@ontrackegy.com'");
$manager=one("SELECT id FROM users WHERE email='manager@delta.test'");
$uid=(int)$admin['id'];
totp_ok(twofa_required($uid),'admin requires second factor');
totp_ok(!twofa_required((int)$manager['id']),'non-admin manager is not forced into enrollment');
$vector=twofa_base32('12345678901234567890');
totp_ok(twofa_unbase32($vector)==='12345678901234567890','base32 roundtrip');
totp_ok(twofa_code($vector,1)==='287082','RFC6238 SHA1 vector truncated to six digits');
$secret=twofa_new_secret();
totp_ok(strlen($secret)===32 && twofa_unbase32($secret)!=='','random secret has 160 bits');
$at=1700000000;
$code=twofa_code($secret,intdiv($at,30));
$rejected=false;
try{twofa_enroll((int)$manager['id'],$secret,$code,$at);}catch(InvalidArgumentException $e){$rejected=true;}
totp_ok($rejected,'non-admin cannot enroll through forced setup');
$codes=twofa_enroll($uid,$secret,$code,$at);
totp_ok(count($codes)===8 && count(array_unique($codes))===8,'eight independent one-time recovery codes');
$row=twofa_record($uid);
totp_ok(twofa_enabled($uid) && !str_contains((string)$row['secret_enc'],$secret),
    'TOTP secret encrypted at rest');
totp_ok(!str_contains((string)$row['recovery_hashes'],$codes[0]),
    'recovery codes stored as hashes');
totp_ok(!twofa_check($uid,$code,$at),'enrollment OTP cannot be replayed');
$next=twofa_code($secret,intdiv($at+60,30));
totp_ok(twofa_check($uid,$next,$at+60),'fresh TOTP permits login');
totp_ok(!twofa_check($uid,$next,$at+60),'same TOTP cannot authorize twice');
totp_ok(twofa_check($uid,$codes[0],$at+60),'recovery code authorizes once');
totp_ok(!twofa_check($uid,$codes[0],$at+60),'used recovery code rejected');
$_SESSION=['uid'=>$uid,'account_id'=>1];
totp_ok(me()===null,'uid alone does not bypass enabled 2FA');
$_SESSION['twofa_verified_uid']=$uid;
totp_ok((int)(me()['id']??0)===$uid,'verified session can access account');
$wrong='000000';
while(in_array($wrong,[
    twofa_code($secret,intdiv($at+120,30)-1),
    twofa_code($secret,intdiv($at+120,30)),
    twofa_code($secret,intdiv($at+120,30)+1)
],true))$wrong=str_pad((string)((int)$wrong+1),6,'0',STR_PAD_LEFT);
for($i=0;$i<5;$i++)totp_ok(!twofa_check($uid,$wrong,$at+120),'invalid OTP rejected '.($i+1));
$row=twofa_record($uid);
totp_ok((int)$row['locked_until']===$at+120+900,'database lockout after five invalid attempts');
$valid=twofa_code($secret,intdiv($at+150,30));
totp_ok(!twofa_check($uid,$valid,$at+150),'locked account rejects even valid OTP');
execute('UPDATE user_totp SET locked_until=NULL WHERE user_id=?',[$uid]);
totp_ok(twofa_check($uid,$valid,$at+150),'fresh OTP works after lock expiry');
echo "VOICE_TOTP_REGRESSION_PASS\n";
