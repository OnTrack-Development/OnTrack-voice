<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/_core/Signup.php';
function test_signup():void {
    $users=(int)one('SELECT COUNT(*) n FROM users')['n'];
    $members=(int)one('SELECT COUNT(*) n FROM memberships')['n'];
    $subs=(int)one('SELECT COUNT(*) n FROM subscriptions')['n'];
    $company='Regression Company';
    $email='new-direct-company-'.bin2hex(random_bytes(5)).'@example.test';
    $token='';
    signup_stage('Registered Owner',$company,$email,'StrongPass_2026X',
        static function(string $address,string $proof)use(&$token,$email):bool{
            if($address!==$email)throw new RuntimeException('wrong address');
            $token=$proof;return true;
        });
    ok(strlen($token)===64,'signup sends random email-only proof through transport');
    ok((int)one('SELECT COUNT(*) n FROM users')['n']===$users,'unverified signup does not create user');
    ok((int)one('SELECT COUNT(*) n FROM memberships')['n']===$members,'unverified signup does not grant membership');
    $row=one('SELECT * FROM signup_requests WHERE email=?',[$email]);
    ok($row!==null && $row['token_hash']===hash('sha256',$token),'only token hash is persisted');
    $accountId=signup_confirm($token);
    ok($accountId>0,'verified email created company');
    $user=one('SELECT id FROM users WHERE email=?',[$email]);
    ok($user!==null && (int)one('SELECT COUNT(*) n FROM memberships WHERE user_id=? AND account_id=? AND role=?',
        [$user['id'],$accountId,'owner'])['n']===1,'verified owner gets only its own membership');
    ok((int)one('SELECT COUNT(*) n FROM users')['n']===$users+1,'exactly one user');
    ok((int)one('SELECT COUNT(*) n FROM subscriptions')['n']===$subs,'email proof does not activate any paid plan');
    $replay=false;try{signup_confirm($token);}catch(InvalidArgumentException $e){$replay=true;}
    ok($replay,'verification token replay denied');
    $existingToken='';
    $res=signup_stage('Other',$company,$email,'StrongPass_2026X',
        static function(string $e,string $t)use(&$existingToken):bool{$existingToken=$t;return true;});
    ok($res===false && $existingToken==='','existing verified user cannot be overwritten by signup');
    $badEmail='invalid-'.bin2hex(random_bytes(5)).'@example.test';
    $fail=false;
    try{signup_stage('User',$company,$badEmail,'StrongPass_2026X',static fn()=>false);}
    catch(RuntimeException $e){$fail=true;}
    ok($fail && one('SELECT id FROM signup_requests WHERE email=?',[$badEmail])===null,
       'failed mail transport never leaves open activation token');
    $another='expired-'.bin2hex(random_bytes(5)).'@example.test';
    $expiredToken='';
    signup_stage('User',$company,$another,'StrongPass_2026X',
        static function(string $e,string $t)use(&$expiredToken):bool{$expiredToken=$t;return true;});
    execute("UPDATE signup_requests SET expires_at='2000-01-01 00:00:00' WHERE email=?",[$another]);
    $expired=false;try{signup_confirm($expiredToken);}catch(InvalidArgumentException $e){$expired=true;}
    ok($expired,'expired registration link rejected');
    echo "VERIFIED_CUSTOMER_SIGNUP_PASS\n";
}
