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

/* Owner decision 2026-11-04: no forced login OTP for any site role.
 * Legacy algorithm is retained only for compatibility with historic encrypted
 * records. It is NOT loaded by the site router or offered in Settings.
 * Security controls retained: password, CSRF, session regeneration and
 * failed-login throttle. Device pairing tokens are unrelated to website MFA.
 */
$root=dirname(__DIR__).'/saas/console';
$front=(string)file_get_contents($root.'/index.php');
$views=(string)file_get_contents($root.'/_core/Views.php');
$signup=(string)file_get_contents($root.'/_core/Signup.php');
valid(!str_contains($front,"require __DIR__.'/_core/AdminMfa.php'"),'website never loads old MFA controller');
valid(!preg_match('/mfa_gate_required\\s*\\(|mfa_route\\s*\\(|mfa_privileged\\s*\\(|mfa_record\\s*\\(/',$front),'no active MFA gate for login, SSO or admin');
valid(str_contains($front,"if(in_array(\$path,['/mfa','/mfa/setup'],true))"),'old MFA links route to authenticated dashboard');
valid(str_contains($front,'require_login();')&&str_contains($front,"go('/');"),'old MFA routes must not expose OTP pages or bypass password');
valid(!str_contains($views,"echo card('التحقق بخطوتين'"),'settings must not render compulsory 2FA controls');
valid(!str_contains($views,"mfa_record("),'settings must not access MFA enrollment');
valid(!str_contains($signup,'سجل الدخول لإعداد المصادقة الثنائية'),'registration must not instruct enabling MFA');
valid(str_contains($front,'password_verify($pass,')&&str_contains($front,"require_csrf();"),'password and CSRF remain mandatory');
valid(str_contains($front,'$bad>=8')&&str_contains($front,'session_regenerate_id(true)'),'login rate limit and session regeneration preserved');
valid(str_contains($front,'verify_external_sso_ticket($ticket)')&&str_contains($front,'require_csrf();'),'signed SSO consent remains mandatory');
valid(str_contains($front,"if(\$path==='/verify-email')signup_verify_route()"),'email ownership confirmation for NEW accounts unaffected');
echo "SITE_PASSWORD_ONLY_LOGIN_NO_MFA_PASS\\n";
