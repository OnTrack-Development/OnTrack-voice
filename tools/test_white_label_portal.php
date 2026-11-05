<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/Signup.php';
require_once __DIR__.'/../saas/console/_core/DomainVerification.php';
function wlcheck(bool $value,string $label):void {
    if(!$value)throw new RuntimeException('WHITE_LABEL_FAILED: '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
function test_white_label_portal():void {
    seed_console();
    $_SESSION=[];
    $_SERVER['HTTP_HOST']='app.ontrackegy.com';
    $_SERVER['REQUEST_URI']='/';
    wlcheck(white_label_context()===null,'platform identity is not white-label');
    $_SERVER['REQUEST_URI']='/portal/delta-voice/login';
    wlcheck(route()==='/login','branded login is routed internally without changing browser URL');
    $ctx=white_label_context();
    wlcheck((int)$ctx['id']===2 && $ctx['_portal_mode']==='path','brand resolves exact reseller');
    wlcheck(white_label_url('/register')==='/portal/delta-voice/register',
        'registration URL uses branded portal rather than reseller query parameter');
    wlcheck(str_contains(white_label_absolute_url('/verify-email?token=abc'),
        '/portal/delta-voice/verify-email?token=abc'),'email confirmation stays on branded portal');
    wlcheck(white_label_account_allowed(2) && white_label_account_allowed(5)
        && !white_label_account_allowed(1) && !white_label_account_allowed(3)
        && !white_label_account_allowed(7),'platform and other reseller are isolated');
    $delta=one("SELECT id FROM users WHERE email='reseller@delta.test'");
    $nile=one("SELECT id FROM users WHERE email='reseller@nile.test'");
    wlcheck(count(white_label_memberships((int)$delta['id']))===1 &&
        count(white_label_memberships((int)$nile['id']))===0,
        'only the correct reseller owner can log in from white-label portal');
    $blocked=false;
    try{signup_scope('customer','nile-voice');}catch(InvalidArgumentException){$blocked=true;}
    wlcheck($blocked,'customer cannot forge the parent reseller in a branded POST');
    $blocked=false;
    try{signup_scope('reseller');}catch(InvalidArgumentException){$blocked=true;}
    wlcheck($blocked,'white-label portal never creates upstream reseller accounts');
    $proof='';
    $email='branded-'.bin2hex(random_bytes(4)).'@example.test';
    signup_stage('Demo','Branded Company',$email,'UniquePassword2026',
        static function(string $address,string $token)use(&$proof):bool {$proof=$token;return true;},
        'customer','delta-voice');
    wlcheck(strlen($proof)===64,'branded registration emails must verify ownership');
    $_SERVER['REQUEST_URI']='/portal/nile-voice/verify-email';
    $rejected=false;
    try{signup_confirm($proof);}catch(InvalidArgumentException){$rejected=true;}
    wlcheck($rejected,'verification link cannot be claimed by a different reseller');
    $_SERVER['REQUEST_URI']='/portal/delta-voice/verify-email';
    $newAccount=signup_confirm($proof);
    wlcheck((int)account($newAccount)['parent_id']===2,'new customer belongs to branded reseller');
    $_SERVER['REQUEST_URI']='/portal/delta-voice/forgot-password';
    $foreignSent=false;
    password_reset_stage('reseller@nile.test',
        static function(string $address,string $token)use(&$foreignSent):bool {$foreignSent=true;return true;});
    wlcheck(!$foreignSent,'branded password recovery never emails another tenant');
    $localProof='';
    password_reset_stage($email,
        static function(string $address,string $token)use(&$localProof):bool {$localProof=$token;return true;});
    wlcheck(strlen($localProof)===64,'branded password recovery sends own user link');
    $_SERVER['REQUEST_URI']='/portal/nile-voice/reset-password';
    $rejected=false;
    try{password_reset_confirm($localProof,'SecureNextPass2026');}catch(InvalidArgumentException){$rejected=true;}
    wlcheck($rejected,'reset token cannot be claimed across branded tenants');
    $_SERVER['REQUEST_URI']='/portal/delta-voice/reset-password';
    password_reset_confirm($localProof,'SecureNextPass2026');
    wlcheck(password_verify('SecureNextPass2026',
        (string)one("SELECT password_hash FROM users WHERE email=?",[$email])['password_hash']),
        'branded password reset changes only correct user');
    $_SESSION=['wl_portal_slug'=>'delta-voice'];
    $_SERVER['REQUEST_URI']='/';
    wlcheck(white_label_url('/login')==='/portal/delta-voice/login',
        'shared-host navigation retains portal identity in session');
    $_SESSION=[];
    $domain='app.sog-'.bin2hex(random_bytes(4)).'.example.org';
    $id=insert("INSERT INTO domains(account_id,hostname,verification_token,status,created_at)
        VALUES (2,?,?,'pending',?)",[$domain,bin2hex(random_bytes(18)),now()]);
    $_SERVER['HTTP_HOST']=$domain;
    $_SERVER['REQUEST_URI']='/login';
    $unverified=false;
    try{white_label_context();}catch(InvalidArgumentException){$unverified=true;}
    wlcheck($unverified,'unverified custom host cannot serve the customer portal');
    $tokens=one('SELECT verification_token FROM domains WHERE id=?',[$id]);
    $getTxt=static fn(string $name):array=>[['txt'=>'ontrack-verify='.$tokens['verification_token']]];
    wlcheck(domain_dns_verify(2,$id,$getTxt)['verified'],'DNS ownership proof accepted');
    $bad=domain_https_activate(2,$id,$getTxt,static fn():bool=>false,
        static fn():bool=>true);
    wlcheck($bad['active']===false && $bad['reason']==='dns_target_unverified',
        'domain cannot activate without correct DNS destination');
    $bad=domain_https_activate(2,$id,$getTxt,static fn():bool=>true,
        static fn():bool=>false);
    wlcheck($bad['active']===false && $bad['reason']==='https_virtualhost_not_ready',
        'domain cannot activate without valid HTTPS and server routing');
    $foreignDenied=false;
    try{domain_https_activate(3,$id,$getTxt,static fn():bool=>true,static fn():bool=>true);}
    catch(InvalidArgumentException){$foreignDenied=true;}
    wlcheck($foreignDenied,'other reseller cannot activate a domain it does not own');
    $passed=domain_https_activate(2,$id,$getTxt,static fn():bool=>true,static fn():bool=>true);
    wlcheck($passed['active'] && one('SELECT status FROM domains WHERE id=?',[$id])['status']==='active',
        'only verified DNS plus real-routed HTTPS may activate custom domain');
    $ctx=white_label_context();
    wlcheck((int)$ctx['id']===2 && $ctx['_portal_mode']==='domain','active hostname resolves own reseller');
    wlcheck(white_label_url('/login')==='/login' &&
        white_label_absolute_url('/register')==='https://'.$domain.'/register',
        'custom hostname contains all public routes without parent vendor hostname');
    $_SERVER['REQUEST_URI']='/portal/nile-voice/login';
    $cross=false;
    try{white_label_context();}catch(InvalidArgumentException){$cross=true;}
    wlcheck($cross,'custom hostname cannot switch to another reseller portal path');
    echo "WHITE_LABEL_TENANT_AND_DOMAIN_PASS\n";
}
if(PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)test_white_label_portal();
