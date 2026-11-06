<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/Signup.php';
if(is_dir('/home/appontrack') && empty(getenv('ONTRACK_CI_NAMESPACE')))
    throw new RuntimeException('CI fixture only');
function throttle_check(bool $truth,string $message):void {
    if(!$truth)throw new RuntimeException('AUTH_THROTTLE_FAILED '.$message);
}
seed_console();
$ip='198.51.100.84';
$email='session-replay-'.bin2hex(random_bytes(5)).'@example.test';
$another='unrelated-'.bin2hex(random_bytes(5)).'@example.test';
throttle_check(!auth_login_throttled($email,$ip),'new email is allowed');
for($n=0;$n<15;$n++)auth_login_record($email,$ip,false);
throttle_check(auth_login_throttled($email,$ip),
    'too many failed passwords are blocked in the database');
$_SESSION=[];
throttle_check(auth_login_throttled($email,$ip),
    'cookie/session reset cannot evade server-side throttle');
throttle_check(!auth_login_throttled($another,$ip),
    'different email is not locked by few peer mistakes');
$bucket=auth_login_buckets($email,$ip)[0]['hash'];
$entry=one('SELECT attempts,blocked_until FROM auth_login_throttle WHERE bucket_hash=?',[$bucket]);
throttle_check((int)$entry['attempts']===15 && $entry['blocked_until']>now(),
    'account bucket records a temporary lock');
auth_login_record($email,$ip,true);
throttle_check(!auth_login_throttled($email,$ip),
    'successful credentials clear only this email bucket');
$peer=auth_login_buckets($email,$ip)[1]['hash'];
throttle_check((int)one('SELECT attempts FROM auth_login_throttle WHERE bucket_hash=?',[$peer])['attempts']===15,
    'IP spraying history remains after one successful login');
for($n=15;$n<500;$n++)auth_login_record($another,$ip,false);
throttle_check(auth_login_throttled($email,$ip),'many bad identities trigger peer lock');
throttle_check(auth_login_throttled($another,$ip),'peer lock also applies to another email');
$stale=gmdate('Y-m-d H:i:s',time()-2000);
execute('UPDATE auth_login_throttle SET first_attempt_at=?,blocked_until=?
    WHERE bucket_hash IN (?,?)',[$stale,$stale,$peer,auth_login_buckets($another,$ip)[0]['hash']]);
throttle_check(!auth_login_throttled($email,$ip),'expired peer lock is released');
auth_login_record($email,$ip,false);
throttle_check((int)one('SELECT attempts FROM auth_login_throttle WHERE bucket_hash=?',[$peer])['attempts']===1,
    'past-window throttle counter resets rather than remaining permanently blocked');
echo "AUTH_LOGIN_DATABASE_THROTTLE_PASS\n";
