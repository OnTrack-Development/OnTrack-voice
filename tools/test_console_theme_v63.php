<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/ConsoleTheme.php';
if(is_dir('/home/appontrack')&&!getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('isolated_console_theme_test_only');
function theme_assert(bool $ok,string $what):void {
    if(!$ok)throw new RuntimeException('THEME_V63_FAIL '.$what);
}
seed_console();
$admin=one("SELECT id,auth_version FROM users WHERE email='admin@ontrackegy.com'");
$reseller=one("SELECT id,auth_version FROM users WHERE email='reseller@delta.test'");
$other=one("SELECT id,auth_version FROM users WHERE email='reseller@nile.test'");
$customer=one("SELECT id,auth_version FROM users WHERE email='pharmacy@delta.test'");
$manager=one("SELECT id,auth_version FROM users WHERE email='manager@delta.test'");
$login=static function(array $user,int $accountId):array {
    $_SESSION['uid']=(int)$user['id'];
    $_SESSION['auth_version']=(int)($user['auth_version']??0);
    $_SESSION['account_id']=$accountId;
    unset($_SESSION['support']);
    return mine();
};
$defaults=console_theme_defaults();
theme_assert($defaults['layout']==='split' && $defaults['dark_sidebar']==='#171c24',
    'precision theme defaults are stable');
foreach(['#ffffff','#000000','#Aa2233'] as $hex)
    theme_assert(console_theme_valid_hex($hex),'approved strict hex');
foreach(['red','linear-gradient(#fff,#000)','</style><script>','#123456;position:absolute','#fff'] as $hex)
    theme_assert(!console_theme_valid_hex($hex),'CSS injection and shorthand denied');
$palette=[
    'layout'=>'split',
    'dark_sidebar'=>'#123456','dark_content'=>'#102038','dark_accent'=>'#cc3355',
    'light_sidebar'=>'#efefee','light_content'=>'#f3e7dc','light_accent'=>'#223344'
];
$adminCtx=$login($admin,1);
console_theme_write($adminCtx,$palette);
theme_assert(console_theme_effective(1)['dark_sidebar']==='#123456','platform stores dark sidebar');
theme_assert(console_theme_effective(2)['dark_sidebar']===$defaults['dark_sidebar'],
    'platform does not overwrite reseller');
$resellerCtx=$login($reseller,2);
$palette['dark_sidebar']='#ffffff';
$palette['dark_content']='#181818';
$palette['dark_accent']='#eeeeee';
$palette['light_content']='#121212';
$palette['light_sidebar']='#999999';
$palette['light_accent']='#222222';
console_theme_write($resellerCtx,$palette);
theme_assert(console_theme_effective(2)['dark_sidebar']==='#ffffff','reseller palette saved');
theme_assert(console_theme_effective(5)['dark_sidebar']==='#ffffff','child inherits reseller palette');
theme_assert(console_theme_effective(6)['dark_sidebar']==='#ffffff','sibling defaults inherited');
theme_assert(console_theme_effective(7)['dark_sidebar']===$defaults['dark_sidebar'],
    'foreign reseller child never inherits');
theme_assert(console_theme_effective(4)['dark_sidebar']===$defaults['dark_sidebar'],
    'direct user account independent');
$vars=console_theme_vars(console_theme_effective(5));
theme_assert(str_contains($vars,'--ot-dark-sidebar:#ffffff;') &&
    str_contains($vars,'--ot-light-content:#121212;') &&
    str_contains($vars,'--ot-light-on-accent:#ffffff;') &&
    str_contains($vars,'--ot-dark-on-accent:#10131a;'),
    'palette exports valid contrast-aware CSS tokens in dark and light');
theme_assert(console_theme_on('#000000')==='#ffffff' &&
    console_theme_on('#ffffff')==='#10131a','WCAG contrast text choice');
$managerCtx=$login($manager,2);
$denied=false;
try{console_theme_write($managerCtx,$palette);}catch(InvalidArgumentException){$denied=true;}
theme_assert($denied,'manager cannot modify company palette');
$customerCtx=$login($customer,5);
$palette['layout']='single';
$palette['dark_content']='#444444';
$palette['light_content']='#eeeeee';
console_theme_write($customerCtx,$palette);
theme_assert(console_theme_effective(5)['layout']==='single' &&
    console_theme_effective(5)['dark_sidebar']==='#444444' &&
    console_theme_effective(5)['light_sidebar']==='#eeeeee','single surface really normalizes color values');
theme_assert(console_theme_effective(6)['dark_sidebar']==='#ffffff',
    'customer overrides do not spill to sibling');
theme_assert(console_theme_effective(2)['dark_sidebar']==='#ffffff',
    'child does not override reseller');
theme_assert(str_contains(console_theme_attribute(5),'data-ot-surface=&quot;')===false &&
    str_contains(console_theme_attribute(5),'data-ot-surface="single"'),
    'HTML attribute is valid and tenant scoped');
$bad=$palette;$bad['dark_content']='url(https://attacker.invalid)';
$denied=false;
try{console_theme_write($customerCtx,$bad);}catch(InvalidArgumentException){$denied=true;}
theme_assert($denied,'malicious CSS rejected with zero persisted mutation');
theme_assert(console_theme_effective(5)['dark_content']==='#444444',
    'invalid input does not mutate tenant theme');
$bad=$palette;$bad['layout']='dark-gold-supermode';
$denied=false;
try{console_theme_write($customerCtx,$bad);}catch(InvalidArgumentException){$denied=true;}
theme_assert($denied,'unbounded new theme modes rejected');
console_theme_write($customerCtx,[],true);
theme_assert(console_theme_effective(5)['dark_sidebar']==='#ffffff',
    'customer reset restores inherited reseller theme');
$resellerCtx=$login($reseller,2);
console_theme_write($resellerCtx,[],true);
theme_assert(console_theme_effective(5)['dark_sidebar']===$defaults['dark_sidebar'],
    'reseller reset restores default across inherited clients');
theme_assert(console_theme_effective(1)['dark_sidebar']==='#123456',
    'reseller reset never overwrites platform');
$otherCtx=$login($other,3);
console_theme_write($otherCtx,$palette);
theme_assert(console_theme_effective(7)['layout']==='single','other reseller independent');
$views=file_get_contents(__DIR__.'/../saas/console/_core/Views.php');
$actions=file_get_contents(__DIR__.'/../saas/console/_core/Actions.php');
$js=file_get_contents(__DIR__.'/../saas/console/assets/js/app.js');
$css=file_get_contents(__DIR__.'/../saas/console/assets/css/app.css');
theme_assert(str_contains($views,'data-ot-palette-editor') &&
    str_contains($views,'data-ot-palette-preview') &&
    str_contains($views,'console_theme_attribute') &&
    str_contains($views,'render_console_theme_settings') &&
    str_contains($views,'reset_console_theme'),'theme form, live preview and reset exist');
theme_assert(str_contains($actions,"case 'save_console_theme':") &&
    str_contains($actions,'console_theme_write($a,$palette,false)'),
    'owner action is server-scoped');
theme_assert(str_contains($js,'themeStorageKey') &&
    str_contains($js,'setUpPaletteEditor') &&
    str_contains($css,'OTV_TENANT_STYLE_V63_BEGIN'),
    'per-account mode and preview script and style rules exist');
echo "THEME_V63_ADMIN_RESELLER_CUSTOMER_ISOLATION_CONTRAST_PREVIEW_RESET_PASS\\n";

