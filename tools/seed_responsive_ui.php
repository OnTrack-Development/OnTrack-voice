<?php
declare(strict_types=1);
/** CI-only browser fixture: never operates on production accounts/host. */
// Windows self-hosted and GitHub-hosted Ubuntu CI use the exact same
// disposable SQLite seed. Never let a live host, browser request or ad-hoc
// developer invocation reset real accounts.
$githubIsolated=getenv('GITHUB_ACTIONS')==='true'
    && getenv('CI')==='true'
    && preg_match('/^[A-Za-z0-9_-]{8,60}$/D',(string)getenv('ONTRACK_CI_NAMESPACE'))===1;
$windowsIsolated=PHP_OS_FAMILY==='Windows'
    && preg_match('/^[A-Za-z0-9_-]{8,60}$/D',(string)getenv('ONTRACK_CI_NAMESPACE'))===1;
if(PHP_SAPI!=='cli'||getenv('SAAS_RESPONSIVE_FIXTURE')!=='1'
   ||is_dir('/home/appontrack')||!($githubIsolated||$windowsIsolated)){
    throw new RuntimeException('responsive_fixture_isolated_ci_only');
}
require __DIR__.'/../saas/console/database/Seed.php';
seed_console();
foreach(['admin@ontrackegy.com','reseller@delta.test','user@direct.test'] as $email){
    $changed=execute("UPDATE users SET password_hash=? WHERE email=?",
        [hash_password('ResponsiveUITest2026'),$email]);
    if($changed!==1)throw new RuntimeException('missing_demo_user_'.hash('sha256',$email));
}
echo "RESPONSIVE_DEMO_USERS_READY\n";
