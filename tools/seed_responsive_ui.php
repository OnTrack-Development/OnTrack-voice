<?php
declare(strict_types=1);
/** CI-only browser fixture: never operates on production accounts/host. */
if(PHP_SAPI!=='cli'||getenv('SAAS_RESPONSIVE_FIXTURE')!=='1'||
   is_dir('/home/appontrack')||stripos(PHP_OS_FAMILY,'Windows')===false){
    throw new RuntimeException('responsive_fixture_only_on_windows_ci');
}
require __DIR__.'/../saas/console/database/Seed.php';
seed_console();
foreach(['admin@ontrackegy.com','reseller@delta.test','user@direct.test'] as $email){
    $changed=execute("UPDATE users SET password_hash=? WHERE email=?",
        [hash_password('ResponsiveUITest2026'),$email]);
    if($changed!==1)throw new RuntimeException('missing_demo_user_'.hash('sha256',$email));
}
echo "RESPONSIVE_DEMO_USERS_READY\n";
