<?php
declare(strict_types=1);
require __DIR__.'/../saas/console/database/Seed.php';
require __DIR__.'/../saas/console/_core/Views.php';
require __DIR__.'/../saas/console/_core/Signup.php';
function assert_v1(bool $yes,string $name):void {if(!$yes)throw new RuntimeException($name);}
if(is_dir('/home/appontrack'))throw new RuntimeException('Refusing to modify live feature flags');
seed_console();
$original=platform_resellers_visible();
try{
    platform_set_resellers_visible(true);
    assert_v1(platform_resellers_visible(),'enabled flag');
    $scope=signup_scope('reseller');
    assert_v1($scope['kind']==='reseller','reseller signup enabled');
    platform_set_resellers_visible(false);
    assert_v1(!platform_resellers_visible(),'disabled flag persisted');
    $blocked=false;
    try{signup_scope('reseller');}catch(InvalidArgumentException $e){$blocked=true;}
    assert_v1($blocked,'reseller registration rejected');
    assert_v1(signup_scope('customer')['kind']==='customer','direct customer allowed');
    $u=one("SELECT id FROM users WHERE email='admin@ontrackegy.com'");
    assert_v1($u!==null,'admin fixture');
    $_SESSION=['uid'=>(int)$u['id'],'account_id'=>1,'console_view'=>'admin'];
    $a=mine();
    assert_v1($a!==null&&$a['kind']==='platform','platform context');
    ob_start();render_plans($a);$plans=(string)ob_get_clean();
    assert_v1(!str_contains($plans,'ريسيلر') && !str_contains($plans,'kind=reseller'),'hidden reseller plans');
    ob_start();render_accounts($a);$accounts=(string)ob_get_clean();
    assert_v1(!str_contains($accounts,'تسجيل ريسيلر'),'hidden signup links');
    ob_start();render_settings($a);$settings=(string)ob_get_clean();
    assert_v1(str_contains($settings,'platform_resellers_visibility'),'admin can re-enable feature');
    platform_set_resellers_visible(true);
    ob_start();render_accounts($a);$accountsOn=(string)ob_get_clean();
    assert_v1(str_contains($accountsOn,'تسجيل ريسيلر'),'reseller links restored');
    echo "V1 reseller visibility PASS\n";
}finally{platform_set_resellers_visible($original);}
