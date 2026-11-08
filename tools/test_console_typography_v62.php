<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/ConsoleTypography.php';
if(is_dir('/home/appontrack')&&!getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('CI font test may not use production database');
function font_assert(bool $b,string $description):void {
    if(!$b)throw new RuntimeException('TYPO_V62_FAIL '.$description);
}
seed_console();
$admin=one("SELECT id,auth_version FROM users WHERE email='admin@ontrackegy.com'");
$reseller=one("SELECT id,auth_version FROM users WHERE email='reseller@delta.test'");
$manager=one("SELECT id,auth_version FROM users WHERE email='manager@delta.test'");
$customer=one("SELECT id,auth_version FROM users WHERE email='pharmacy@delta.test'");
font_assert($admin!==null&&$reseller!==null&&$manager!==null&&$customer!==null,
    'seed principals are available');
$login=static function(array $user,int $accountId):array {
    $_SESSION['uid']=(int)$user['id'];
    $_SESSION['auth_version']=(int)($user['auth_version']??0);
    $_SESSION['account_id']=$accountId;
    unset($_SESSION['support']);
    return mine();
};
font_assert(array_keys(console_font_options())===['plex','tajawal','cairo'],
    'only approved font IDs are selectable');
font_assert(console_font_tenant(1)==='plex'&&console_font_tenant(2)==='plex'&&
    console_font_tenant(5)==='plex','original defaults are consistent');
font_assert(!console_font_valid('Tahoma')&&!console_font_valid('url(http://evil.invalid/font)')&&
    !console_font_valid('<style>'),'CSS injection and legacy fonts rejected');
$adminContext=$login($admin,1);
console_font_save($adminContext,'cairo','tenant');
font_assert(console_font_tenant(1)==='cairo','admin company default persisted');
font_assert(console_font_tenant(2)==='plex','platform font does not overwrite reseller');
console_font_save($adminContext,'tajawal','personal');
font_assert(console_font_effective($adminContext)==='tajawal','personal override active');
console_font_save($adminContext,'inherit','personal');
font_assert(console_font_effective($adminContext)==='cairo','removing personal override restores tenant font');

$resellerContext=$login($reseller,2);
console_font_save($resellerContext,'tajawal','tenant');
font_assert(console_font_tenant(2)==='tajawal','reseller tenant default persisted');
font_assert(console_font_tenant(5)==='tajawal','child inherits reseller font when unset');
font_assert(console_font_tenant(7)==='plex','other reseller child isolated');
font_assert(console_font_tenant(4)==='plex','direct account isolated');
$managerContext=$login($manager,2);
font_assert(console_font_effective($managerContext)==='tajawal','staff inherits company font');
$blocked=false;
try{console_font_save($managerContext,'cairo','tenant');}
catch(InvalidArgumentException){$blocked=true;}
font_assert($blocked,'non-owner cannot override company font');
console_font_save($managerContext,'cairo','personal');
font_assert(console_font_effective($managerContext)==='cairo','staff personal font allowed');
font_assert(console_font_tenant(2)==='tajawal','staff preference does not affect tenant');
$blocked=false;
try{console_font_save(['account_id'=>7],'cairo','personal');}
catch(InvalidArgumentException){$blocked=true;}
font_assert($blocked,'user cannot edit outside their memberships');

$customerContext=$login($customer,5);
font_assert(console_font_effective($customerContext)==='tajawal','user inherits reseller font');
console_font_save($customerContext,'cairo','tenant');
font_assert(console_font_tenant(5)==='cairo','customer owner can set independent company font');
font_assert(console_font_tenant(6)==='tajawal','sibling customers remain isolated');
console_font_save($customerContext,'tajawal','personal');
font_assert(console_font_effective($customerContext)==='tajawal','customer personal override');
console_font_save($customerContext,'inherit','personal');
font_assert(console_font_effective($customerContext)==='cairo','customer personal reset');
console_font_save($customerContext,'inherit','tenant');
font_assert(console_font_tenant(5)==='tajawal','tenant reset re-inherits parent branding');
font_assert(console_font_effective($customerContext)==='tajawal','reset changes effective font');

$blocked=false;
try{console_font_save($customerContext,'</style><script>','personal');}
catch(InvalidArgumentException){$blocked=true;}
font_assert($blocked,'font choice injection rejected');
$blocked=false;
try{console_font_save($customerContext,'cairo','other');}
catch(InvalidArgumentException){$blocked=true;}
font_assert($blocked,'unknown font scope rejected');

$views=file_get_contents(__DIR__.'/../saas/console/_core/Views.php');
$actions=file_get_contents(__DIR__.'/../saas/console/_core/Actions.php');
font_assert(str_contains($views,'data-ot-font') &&
    str_contains($views,'function render_settings') &&
    str_contains($views,"form_start('save_console_font'") &&
    str_contains($views,'data-font-sample'),
    'RTL font choice and preview visible in settings');
font_assert(str_contains($actions,"case 'save_console_font':") &&
    str_contains($actions,'console_font_save($a,$chosen,$scope)'),
    'server route persists the scoped typography choice');
echo "TYPO_V62_TENANT_USER_RESELLER_DEFAULT_OVERRIDE_AND_ISOLATION_PASS\n";
