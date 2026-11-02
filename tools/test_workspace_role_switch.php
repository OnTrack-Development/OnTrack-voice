<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
function workspace_assert(bool $condition,string $message):void{
    if(!$condition)throw new RuntimeException('WORKSPACE_ROLE_SWITCH_FAIL: '.$message);
}
seed_console();
$admin=one("SELECT u.id uid,m.account_id account_id FROM users u
    JOIN memberships m ON m.user_id=u.id
    WHERE u.email='admin@ontrackegy.com' AND m.role='owner'");
$reseller=one("SELECT u.id uid,m.account_id account_id FROM users u
    JOIN memberships m ON m.user_id=u.id
    WHERE u.email='reseller@delta.test' AND m.role='owner'");
$manager=one("SELECT u.id uid,m.account_id account_id FROM users u
    JOIN memberships m ON m.user_id=u.id
    WHERE u.email='manager@delta.test' AND m.role='manager'");
$customer=one("SELECT u.id uid,m.account_id account_id FROM users u
    JOIN memberships m ON m.user_id=u.id
    WHERE u.email='user@direct.test' AND m.role='owner'");
foreach(['admin'=>$admin,'reseller'=>$reseller,'manager'=>$manager,'customer'=>$customer] as $name=>$user)
    workspace_assert((bool)$user,$name.' seed exists');
$_SESSION=['uid'=>(int)$admin['uid'],'account_id'=>(int)$admin['account_id'],'console_view'=>'admin'];
$original=mine();
workspace_assert($original['kind']==='platform','platform context');
workspace_assert(console_can_switch_view($original),'platform owner can switch');
workspace_assert(console_view_mode($original)==='admin','platform starts admin');
$nav=nav_entries('platform');
workspace_assert(isset($nav['accounts'])&&isset($nav['plans']),'admin navigation remains');
ob_start();layout_open($original,'overview');$html=ob_get_clean();
workspace_assert(str_contains($html,'workspace-switcher'),'desktop persistent switch exists');
workspace_assert(str_contains($html,'الانتقال لمساحة المستخدم'),'platform to user button');
workspace_assert(str_contains($html,'name="view" value="user"'),'server action target included');
$_SESSION['console_view']='user';
workspace_assert(console_view_mode($original)==='user','platform preview active');
$nav=nav_entries('platform');
workspace_assert(!isset($nav['accounts'])&&isset($nav['agents']),'platform preview has user menu');
ob_start();layout_open($original,'overview');$html=ob_get_clean();
workspace_assert(str_contains($html,'العودة للوحة مدير المنصة'),'platform back switch in sidebar');
workspace_assert(str_contains($html,'name="view" value="admin"'),'platform exit action');

$_SESSION=['uid'=>(int)$reseller['uid'],'account_id'=>(int)$reseller['account_id'],'console_view'=>'admin'];
$resellerContext=mine();
workspace_assert($resellerContext['kind']==='reseller','reseller context');
workspace_assert(console_can_switch_view($resellerContext),'reseller owner can switch');
workspace_assert(isset(nav_entries('reseller')['accounts']),'reseller management visible in admin mode');
ob_start();layout_open($resellerContext,'overview');$html=ob_get_clean();
workspace_assert(str_contains($html,'الانتقال لمساحة المستخدم'),'reseller user entry shown');
$_SESSION['console_view']='user';
workspace_assert(console_view_mode($resellerContext)==='user','reseller preview active');
workspace_assert(!isset(nav_entries('reseller')['accounts']),'reseller admin links hidden in preview');
ob_start();layout_open($resellerContext,'overview');$html=ob_get_clean();
workspace_assert(str_contains($html,'العودة لمساحة الريسيلر'),'reseller return in sidebar');
workspace_assert(str_contains($html,'لوحة الريسيلر'),'reseller return in topbar');
workspace_assert(str_contains($html,'name="view" value="admin"'),'reseller back action');

$_SESSION=['uid'=>(int)$reseller['uid'],'account_id'=>(int)$reseller['account_id'],
    'console_view'=>'user','support'=>[
      'account_id'=>5,'origin_id'=>(int)$reseller['account_id'],
      'origin_view'=>'user','expires'=>time()+900
    ]];
$supported=mine();
workspace_assert((int)$supported['account_id']===5,'support is scoped to customer');
workspace_assert(!console_can_switch_view($supported),'cannot switch inside support session');
ob_start();layout_open($supported,'overview');$html=ob_get_clean();
workspace_assert(str_contains($html,'العودة لمساحة الريسيلر'),'support return in sidebar');
workspace_assert(str_contains($html,'↩ لوحة الريسيلر'),'support return in header');
workspace_assert(str_contains($html,'name="action" value="support_leave"'),'support leave is POST');
workspace_assert(!str_contains($html,'name="action" value="set_view"'),'support cannot use preview action');

$_SESSION=['uid'=>(int)$manager['uid'],'account_id'=>(int)$manager['account_id'],
    'console_view'=>'user'];
workspace_assert(!console_can_switch_view(mine()),'non-admin reseller manager cannot switch');
workspace_assert(console_view_mode(mine())==='admin','unprivileged preview does not leak');
$_SESSION=['uid'=>(int)$customer['uid'],'account_id'=>(int)$customer['account_id'],
    'console_view'=>'user'];
workspace_assert(!console_can_switch_view(mine()),'customer owner has no admin view');
workspace_assert(!isset(nav_entries('customer')['accounts']),'customer cannot access admin tabs');
echo "WORKSPACE_PLATFORM_RESELLER_SUPPORT_SWITCH_PASS\n";
