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
    $directParent=account($accountId);
    ok($directParent['kind']==='customer' && (int)$directParent['parent_id']===1,
        'direct registration belongs to platform and never to a reseller');
    $resellerProof='';$resellerEmail='new-reseller-'.bin2hex(random_bytes(5)).'@example.test';
    signup_stage('Reseller Owner','Registered Reseller',$resellerEmail,'StrongPass_2026X',
        static function(string $e,string $t)use(&$resellerProof):bool{$resellerProof=$t;return true;},'reseller');
    $resellerRequest=one('SELECT registration_kind,parent_account_id FROM signup_requests WHERE email=?',[$resellerEmail]);
    ok($resellerRequest['registration_kind']==='reseller' && (int)$resellerRequest['parent_account_id']===1,
        'reseller registration cannot acquire another tenant owner');
    $resellerId=signup_confirm($resellerProof);$createdReseller=account($resellerId);
    ok($createdReseller['kind']==='reseller' && (int)$createdReseller['parent_id']===1,
        'verified reseller is owned by platform');
    $registered=[];
    foreach(['delta-voice'=>2,'nile-voice'=>3] as $slug=>$parentId){
        $proof='';$mail='client-'.bin2hex(random_bytes(5)).'@example.test';
        signup_stage('Customer Owner','Tenant Company',$mail,'StrongPass_2026X',
            static function(string $e,string $t)use(&$proof):bool{$proof=$t;return true;},'customer',$slug);
        $pending=one('SELECT * FROM signup_requests WHERE email=?',[$mail]);
        ok((int)$pending['parent_account_id']===$parentId && $pending['registration_kind']==='customer',
            'customer registered under exact reseller '.$slug);
        $newId=signup_confirm($proof);$created=account($newId);
        ok($created['kind']==='customer' && (int)$created['parent_id']===$parentId,
            'confirmed account remains scoped to selected reseller '.$slug);
        $registered[$slug]=$newId;
    }
    ok(is_reseller_child(2,$registered['delta-voice']) &&
        !is_reseller_child(2,$registered['nile-voice']),'cross-reseller customer access denied');
    $invalid=false;
    try{signup_scope('reseller','delta-voice');}catch(InvalidArgumentException $e){$invalid=true;}
    ok($invalid,'reseller signup cannot impersonate another reseller');
    $invalid=false;
    try{signup_scope('customer','nonexistent-scope');}catch(InvalidArgumentException $e){$invalid=true;}
    ok($invalid,'unrecognized reseller signup link rejected');
    $inactiveEmail='inactive-'.bin2hex(random_bytes(5)).'@example.test';$inactiveProof='';
    signup_stage('Owner','Inactive Parent Test',$inactiveEmail,'StrongPass_2026X',
        static function(string $e,string $t)use(&$inactiveProof):bool{$inactiveProof=$t;return true;},
        'customer','delta-voice');
    execute("UPDATE accounts SET status='suspended' WHERE id=2");
    $rejected=false;
    try{signup_confirm($inactiveProof);}catch(InvalidArgumentException $e){$rejected=true;}
    ok($rejected,'email proof cannot create a customer under suspended reseller');
    execute("UPDATE accounts SET status='active' WHERE id=2");
    $resetEmail='reset-'.bin2hex(random_bytes(5)).'@example.test';
    $resetUserId=insert("INSERT INTO users(name,email,password_hash,status,created_at)
        VALUES (?,?,?,'active',?)",['Reset Demo',$resetEmail,hash_password('ExistingPass2026X'),now()]);
    $resetToken='';password_reset_stage($resetEmail,static function(string $e,string $t)use(&$resetToken):bool{
        $resetToken=$t;return true;
    });
    ok(strlen($resetToken)===64,'password recovery sends a one-time email link');
    $beforeReset=one('SELECT auth_version FROM users WHERE id=?',[$resetUserId]);
    ok(password_reset_confirm($resetToken,'ChangedPass2026X')===$resetUserId,
        'password recovery succeeds after token proof');
    $afterReset=one('SELECT password_hash,auth_version FROM users WHERE id=?',[$resetUserId]);
    ok(password_verify('ChangedPass2026X',$afterReset['password_hash']) &&
        !password_verify('ExistingPass2026X',$afterReset['password_hash']) &&
        (int)$afterReset['auth_version']===(int)$beforeReset['auth_version']+1,
        'password hash rotates and previous session version becomes invalid');
    $resetReplay=false;
    try{password_reset_confirm($resetToken,'ChangedPassAgain2026X');}
    catch(InvalidArgumentException $e){$resetReplay=true;}
    ok($resetReplay,'used reset token cannot be replayed');
    $badCurrent=false;
    try{password_change($resetUserId,'WrongOldPassword','NextStrong2026X');}
    catch(InvalidArgumentException $e){$badCurrent=true;}
    ok($badCurrent,'change-password rejects incorrect current password');
    $oldVersion=(int)$afterReset['auth_version'];
    $currentVersion=password_change($resetUserId,'ChangedPass2026X','NewChange2026X');
    ok($currentVersion===$oldVersion+1 &&
        password_verify('NewChange2026X',(string)one('SELECT password_hash FROM users WHERE id=?',[$resetUserId])['password_hash']),
        'authenticated password change rotates credentials and session version');
    $missingSent=false;
    password_reset_stage('not-found-'.bin2hex(random_bytes(5)).'@example.test',
        static function(string $e,string $t)use(&$missingSent):bool{$missingSent=true;return true;});
    ok(!$missingSent,'unknown recovery email never sends a link or selects an identity');
    echo "VERIFIED_TENANT_REGISTRATION_AND_PASSWORD_RECOVERY_PASS\n";
    echo "VERIFIED_CUSTOMER_SIGNUP_PASS\n";
}
