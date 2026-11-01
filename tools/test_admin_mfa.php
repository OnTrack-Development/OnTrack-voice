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

/* Regression: a privileged account with no enrolled MFA must not be locked out.
 * Enrolling a valid, encrypted TOTP seed switches the gate on; unconfirmed
 * drafts are session-only and never count as enrolled. */
require_once dirname(__DIR__).'/saas/console/database/Seed.php';
seed_console();
$admin=one("SELECT u.id FROM users u JOIN memberships m ON m.user_id=u.id WHERE m.role='owner' ORDER BY u.id LIMIT 1");
valid($admin!==null,'fixture privileged owner');
$uid=(int)$admin['id'];
execute('DELETE FROM admin_mfa WHERE user_id=?',[$uid]);
valid(mfa_privileged($uid),'role is privileged');
valid(!mfa_gate_required($uid),'unenrolled admin can log in without TOTP');
$_SESSION=['mfa_pending'=>mfa_secret(),'mfa_pending_uid'=>$uid,'mfa_pending_at'=>time()];
valid(!mfa_gate_required($uid),'unverified QR pending must not enable MFA');
$enrolled=mfa_secret();
insert('INSERT INTO admin_mfa(user_id,secret_enc,last_step,created_at) VALUES (?,?,?,?)',[$uid,seal_secret($enrolled),-1,now()]);
valid(mfa_gate_required($uid),'verified-enrollment record enables MFA gate');
$code=mfa_totp($enrolled,intdiv(time(),30));
valid(mfa_check($uid,$code),'activated MFA accepts correct TOTP');
valid(!mfa_check($uid,$code),'activated MFA rejects replay');
execute('DELETE FROM admin_mfa WHERE user_id=?',[$uid]);
echo "MFA_ENROLLMENT_GATE_PASS\n";
