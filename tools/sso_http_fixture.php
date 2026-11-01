<?php
declare(strict_types=1);
// Test-only CLI fixture; cannot be executed by HTTP or on the production host.
if(PHP_SAPI!=='cli' || getenv('ONTRACK_TEST_MODE')!=='1' || is_dir('/home/appontrack')){
    http_response_code(403);exit(1);
}
require __DIR__.'/../saas/console/database/Seed.php';
seed_console();
$password=(string)getenv('SSO_TEST_PASSWORD');
$secret=(string)getenv('SSO_TEST_SECRET');
if(strlen($password)<24||!preg_match('/^otint_[a-f0-9]{64}$/D',$secret))exit(2);
$email='sso-'.bin2hex(random_bytes(8)).'@example.invalid';
$uid=insert("INSERT INTO users(name,email,password_hash,status,created_at) VALUES (?,?,?,'active',?)",
    ['SSO HTTP test',$email,hash_password($password),now()]);
insert('INSERT INTO memberships(user_id,account_id,role,created_at) VALUES (?,5,?,?)',[$uid,'viewer',now()]);
$iid=insert("INSERT INTO integrations(account_id,name,provider,token_hash,token_prefix,status,created_at,secret_enc)
    VALUES (2,'SSO HTTP test','whmcs',?,?,'active',?,?)",
    [hash('sha256',$secret),substr($secret,0,12),now(),seal_secret($secret)]);
$otherEmail='sso-other-'.bin2hex(random_bytes(8)).'@example.invalid';
$otherUid=insert("INSERT INTO users(name,email,password_hash,status,created_at) VALUES (?,?,?,'active',?)",
    ['SSO Other Tenant',$otherEmail,hash_password($password),now()]);
insert('INSERT INTO memberships(user_id,account_id,role,created_at) VALUES (?,7,?,?)',[$otherUid,'viewer',now()]);
echo json_encode(['user_id'=>$uid,'email'=>$email,'other_email'=>$otherEmail,'integration_id'=>$iid],JSON_UNESCAPED_SLASHES);
