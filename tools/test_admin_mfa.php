<?php
declare(strict_types=1);
require dirname(__DIR__).'/saas/console/_core/AdminMfa.php';
function valid(bool $b,string $m):void {if(!$b)throw new RuntimeException($m);}
$secret='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
valid(mfa_totp($secret,1)==='287082','RFC6238 SHA1 vector');
$secret=mfa_secret();valid(strlen($secret)===32,'secret format');
$t=1700000000;$s=intdiv($t,30);$code=mfa_totp($secret,$s);
valid(mfa_step($secret,$code,$t)===$s,'valid code');
valid(mfa_step($secret,$code,$t,$s)===null,'replay must fail');
valid(mfa_step($secret,$code,$t+120)===null,'expired code must fail');
valid(mfa_step($secret,'12345',$t)===null,'bad format');
echo "ADMIN_MFA_PASS\n";

/* Isolated, in-memory DB fixture: NEVER mutate the shared seeded app DB.
 * Enrolled record alone permits a TOTP challenge; role or pending session doesn't. */
$GLOBALS['mfa_test_row']=null;
function one(string $sql,array $args=[]):?array {
    if(str_contains($sql,'FROM admin_mfa'))return $GLOBALS['mfa_test_row'];
    if(str_contains($sql,'FROM memberships'))return ['user_id'=>1];
    return null;
}
function unseal_secret(?string $raw):?string {
    return is_string($raw)&&str_starts_with($raw,'test-encrypted:')?substr($raw,15):null;
}
function execute(string $sql,array $args=[]):int {
    if(!str_starts_with($sql,'UPDATE admin_mfa SET last_step='))throw new RuntimeException('Unexpected SQL');
    if($GLOBALS['mfa_test_row']===null||(int)$GLOBALS['mfa_test_row']['last_step'] >= (int)$args[0])return 0;
    $GLOBALS['mfa_test_row']['last_step']=(int)$args[0];return 1;
}
$uid=1;
valid(mfa_privileged($uid),'owner is privileged');
valid(mfa_gate_required($uid),'unenrolled admin must complete MFA setup before entering console');
$_SESSION=['mfa_pending'=>mfa_secret(),'mfa_pending_uid'=>$uid,'mfa_pending_at'=>time()];
valid(mfa_gate_required($uid),'QR draft still blocks admin before a verified TOTP');
$enrolled=mfa_secret();
$GLOBALS['mfa_test_row']=['secret_enc'=>'test-encrypted:'.$enrolled,'last_step'=>-1];
valid(mfa_gate_required($uid),'verified stored MFA enrollment requires login code');
$code=mfa_totp($enrolled,intdiv(time(),30));
valid(mfa_check($uid,$code),'enrolled TOTP accepts correct code');
valid(!mfa_check($uid,$code),'enrolled TOTP blocks code replay');
$GLOBALS['mfa_test_row']=null;
valid(mfa_gate_required($uid),'privileged gate remains required without stored enrollment');
echo "MFA_ENROLLMENT_GATE_PASS\n";
