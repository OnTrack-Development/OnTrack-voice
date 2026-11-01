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
