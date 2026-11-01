<?php
declare(strict_types=1);
require __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/ExternalSso.php';

function check_sso(bool $pass,string $label):void{
    if(!$pass)throw new RuntimeException('FAIL '.$label);
    echo "PASS $label\n";
}
function denied_sso(callable $fn,string $expected):bool{
    try{$fn();}catch(InvalidArgumentException $e){return $e->getMessage()===$expected;}
    return false;
}
function signed_sso_ticket(int $integrationId,string $secret,string $subject,array $overrides=[]):string{
    $now=time();
    $payload=array_merge([
        'v'=>1,'integration_id'=>$integrationId,'iss'=>'whmcs',
        'aud'=>SSO_AUDIENCE,'sub'=>$subject,
        'jti'=>bin2hex(random_bytes(16)),'nonce'=>bin2hex(random_bytes(16)),
        'iat'=>$now,'exp'=>$now+120
    ],$overrides);
    $encoded=rtrim(strtr(base64_encode(json_encode($payload,JSON_UNESCAPED_SLASHES)),'+/','-_'),'=');
    return $encoded.'.'.hash_hmac('sha256',$encoded,$secret);
}
seed_console();
$_SESSION=[];
$pharmacy=one("SELECT id FROM users WHERE email='pharmacy@delta.test'");
$nile=one("SELECT id FROM users WHERE email='user@nile.test'");
$fashion=one("SELECT id FROM users WHERE email='fashion@delta.test'");
check_sso((bool)$pharmacy&&(bool)$nile&&(bool)$fashion,'seeded tenant identities');
$secret='otint_'.bin2hex(random_bytes(32));
$integrationId=insert("INSERT INTO integrations(account_id,name,provider,token_hash,token_prefix,status,created_at,secret_enc)
    VALUES (2,'SSO TEST','whmcs',?,?,'active',?,?)",
    [hash('sha256',$secret),substr($secret,0,12),now(),seal_secret($secret)]);
$beforeMembers=(int)one('SELECT COUNT(*) n FROM memberships')['n'];
$beforeAccounts=(int)one('SELECT COUNT(*) n FROM accounts')['n'];
$beforeUsers=(int)one('SELECT COUNT(*) n FROM users')['n'];
$ticket=signed_sso_ticket($integrationId,$secret,'external-client-001');
$verified=verify_external_sso_ticket($ticket);
check_sso($verified['claims']['sub']==='external-client-001','signed WHMCS assertion validated');
check_sso(external_sso_preview($verified)['linked_user_id']===null,'unbound subject is not auto-linked by email');
check_sso(denied_sso(fn()=>finish_external_sso($ticket,null),'sso_local_login_required'),'unbound subject cannot silently login');
$_SESSION=['uid'=>(int)$nile['id'],'account_id'=>7];
check_sso(denied_sso(fn()=>finish_external_sso($ticket,(int)$nile['id']),'sso_tenant_denied'),'other reseller user cannot link');
check_sso((int)one('SELECT COUNT(*) n FROM sso_used_tickets WHERE integration_id=?',[$integrationId])['n']===0,
    'rejected cross-tenant claim did not burn ticket');
$_SESSION=['uid'=>(int)$pharmacy['id'],'account_id'=>5];
$result=finish_external_sso($ticket,(int)$pharmacy['id']);
check_sso($result['new_link']===true && $result['account_id']===5,'local member explicitly links immutable external subject');
check_sso((int)one('SELECT COUNT(*) n FROM memberships')['n']===$beforeMembers &&
    (int)one('SELECT COUNT(*) n FROM users')['n']===$beforeUsers &&
    (int)one('SELECT COUNT(*) n FROM accounts')['n']===$beforeAccounts,
    'SSO never provisions users, tenants or memberships');
check_sso(denied_sso(fn()=>finish_external_sso($ticket,null),'sso_ticket_replayed_or_identity_conflict'),
    'same ticket cannot be replayed');
check_sso(denied_sso(fn()=>external_sso_preview(verify_external_sso_ticket($ticket)),'sso_ticket_replayed'),
    'consumed ticket rejected before UI confirmation');
$again=signed_sso_ticket($integrationId,$secret,'external-client-001');
check_sso(external_sso_preview(verify_external_sso_ticket($again))['linked_user_id']===(int)$pharmacy['id'],
    'same immutable subject resolves only its bound user');
$_SESSION=[];
$login=finish_external_sso($again,null);
check_sso($login['new_link']===false && $login['user_id']===(int)$pharmacy['id'],
    'linked identity can sign in with fresh one-time ticket');
$_SESSION=['uid'=>(int)$fashion['id'],'account_id'=>6];
$wrongUser=signed_sso_ticket($integrationId,$secret,'external-client-001');
check_sso(denied_sso(fn()=>finish_external_sso($wrongUser,(int)$fashion['id']),'sso_account_mismatch'),
    'signed ticket cannot swap a different logged-in user');
$_SESSION=['uid'=>(int)$pharmacy['id'],'account_id'=>5];
$secondSubject=signed_sso_ticket($integrationId,$secret,'external-client-002');
check_sso(denied_sso(fn()=>finish_external_sso($secondSubject,(int)$pharmacy['id']),'sso_user_already_linked'),
    'one local user cannot bind two external subjects in one integration');
$otherResellerSecret='otint_'.bin2hex(random_bytes(32));
$otherId=insert("INSERT INTO integrations(account_id,name,provider,token_hash,token_prefix,status,created_at,secret_enc)
    VALUES (3,'SSO NILE','whmcs',?,?,'active',?,?)",
    [hash('sha256',$otherResellerSecret),substr($otherResellerSecret,0,12),now(),seal_secret($otherResellerSecret)]);
check_sso(denied_sso(fn()=>finish_external_sso(
    signed_sso_ticket($otherId,$otherResellerSecret,'nile-identity-001'),(int)$pharmacy['id']),'sso_tenant_denied'),
    'foreign reseller cannot link pharmacy member');
check_sso(denied_sso(fn()=>verify_external_sso_ticket(
    signed_sso_ticket($integrationId,$otherResellerSecret,'external-client-001')),'sso_signature_invalid'),
    'incorrect HMAC signature rejected');
check_sso(denied_sso(fn()=>verify_external_sso_ticket(
    signed_sso_ticket($integrationId,$secret,'external-client-001',['aud'=>'untrusted-service'])),'sso_ticket_expired_or_invalid'),
    'wrong audience rejected');
check_sso(denied_sso(fn()=>verify_external_sso_ticket(
    signed_sso_ticket($integrationId,$secret,'external-client-001',['iss'=>'laravel'])),'sso_issuer_not_trusted'),
    'issuer mismatch rejected');
check_sso(denied_sso(fn()=>verify_external_sso_ticket(
    signed_sso_ticket($integrationId,$secret,'external-client-001',['iat'=>time()-200,'exp'=>time()-100])),'sso_ticket_expired_or_invalid'),
    'expired assertion rejected');
check_sso(denied_sso(fn()=>verify_external_sso_ticket(
    signed_sso_ticket($integrationId,$secret,'external-client-001',['iat'=>time()+120,'exp'=>time()+180])),'sso_ticket_expired_or_invalid'),
    'future-dated assertion rejected');
check_sso(denied_sso(fn()=>verify_external_sso_ticket(
    signed_sso_ticket($integrationId,$secret,'external-client-001',['nonce'=>'bad'])),'sso_ticket_expired_or_invalid'),
    'missing valid nonce rejected');
$_SESSION=['uid'=>(int)$pharmacy['id'],'account_id'=>5,
    'support'=>['account_id'=>5,'origin_id'=>1,'expires'=>time()+900]];
check_sso(denied_sso(fn()=>finish_external_sso(
    signed_sso_ticket($integrationId,$secret,'external-client-001'),null),'sso_support_session_forbidden'),
    'support impersonation cannot complete SSO');
unset($_SESSION['support']);
execute("UPDATE integrations SET status='revoked' WHERE id=?",[$integrationId]);
check_sso(denied_sso(fn()=>verify_external_sso_ticket(
    signed_sso_ticket($integrationId,$secret,'external-client-001')),'sso_issuer_not_trusted'),
    'revoked integration invalidates federation immediately');
check_sso((int)one('SELECT COUNT(*) n FROM external_identity_links WHERE integration_id=?',[$integrationId])['n']===1,
    'exactly one subject mapping created');
echo "VOICE_SSO_REGRESSION_PASS\n";
