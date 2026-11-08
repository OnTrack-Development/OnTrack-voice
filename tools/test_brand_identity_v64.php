<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/BrandIdentity.php';
if(is_dir('/home/appontrack')&&!getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('brand v64 requires isolated test data');
function brand_check(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('BRAND_V64_FAIL '.$label);
}
function brand_png_chunk(string $name,string $content):string {
    return pack('N',strlen($content)).$name.$content.pack('N',crc32($name.$content));
}
function brand_fixture_png():string {
    $w=32;$h=24;$scanline="\x00".str_repeat("\x88\x20\xaa\xff",$w);
    return "\x89PNG\r\n\x1a\n".
        brand_png_chunk('IHDR',pack('NNCCCCC',$w,$h,8,6,0,0,0)).
        brand_png_chunk('IDAT',gzcompress(str_repeat($scanline,$h))).
        brand_png_chunk('IEND','');
}
seed_console();
$admin=one("SELECT * FROM users WHERE email='admin@ontrackegy.com'");
$reseller=one("SELECT * FROM users WHERE email='reseller@delta.test'");
$customer=one("SELECT * FROM users WHERE email='pharmacy@delta.test'");
brand_check($admin!==null&&$reseller!==null&&$customer!==null,'fixture identities');
$login=static function(array $user,int $account):array {
    $_SESSION['uid']=(int)$user['id'];
    $_SESSION['auth_version']=(int)($user['auth_version']??0);
    $_SESSION['account_id']=$account;
    $_SESSION['console_view']='admin';
    unset($_SESSION['support']);
    return mine();
};
$platform=$login($admin,1);
brand_check(brand_identity_edit_allowed($platform),'platform owner can customize own logo');
brand_check(brand_identity_mode($platform)==='both','default mode without upload');
$fail=false;
try{brand_identity_save($platform,'OnTrack Voice','image');}
catch(InvalidArgumentException){$fail=true;}
brand_check($fail,'cannot select image-only before upload');
$png=brand_fixture_png();
brand_check(brand_identity_image_validate($png)==='png','valid PNG binary image');
foreach(['','SVG<svg/>','<script>alert(1)</script>',str_repeat('X',3*1024*1024+1)] as $bad){
    $fail=false;
    try{brand_identity_image_validate($bad);}catch(InvalidArgumentException){$fail=true;}
    brand_check($fail,'reject malformed and over-limit upload');
}
$name=brand_identity_store_binary($platform,$png);
brand_check((bool)preg_match('/^logo-1-[a-f0-9]{12}\.png$/D',$name),'platform logo filename');
brand_check(is_file(private_path().'/'.$name),'private file exists');
if(PHP_OS_FAMILY!=='Windows')
    brand_check((int)(fileperms(private_path().'/'.$name)&0777)===0600,
        'logo private permissions on production Unix');
brand_check(account(1)['logo_path']===$name,'platform logo linked in account');
brand_identity_save($platform,'OnTrack Custom Image','image');
brand_check(brand_identity_mode($platform)==='image','image only persisted');
brand_check(brand_identity_brand(account(1))['logo']===$name,'platform brand now supports logo');
brand_check(brand_identity_brand(account(1))['name']==='OnTrack Custom Image',
    'platform branded title no longer hard-coded');
brand_identity_save($platform,'OnTrack Custom Name','text');
brand_check(brand_identity_mode($platform)==='text','text-only persisted');
brand_identity_save($platform,'OnTrack Brand Both','both');
brand_check(brand_identity_mode($platform)==='both','image and text persisted');
$resellerContext=$login($reseller,2);
brand_check(brand_identity_mode($resellerContext)==='both','reseller isolated from platform mode');
brand_check(brand_identity_edit_allowed($resellerContext),'reseller may manage own logo');
$oldReseller=brand_identity_store_binary($resellerContext,$png);
brand_identity_save($resellerContext,'Wide Egyptian Voice','image');
brand_check(brand_identity_mode(account(5))==='image',
    'child automatically inherits parent logo mode');
brand_check(brand_identity_mode(account(7))==='both','foreign reseller child isolated');
brand_check(brand_identity_brand(account(5))['logo']===$oldReseller,'child inherits correct image');
brand_check(brand_identity_brand(account(4))['logo']==='','direct customer not hijacked');
$updatedReseller=brand_identity_store_binary($resellerContext,$png);
brand_check($oldReseller!==$updatedReseller,'fresh filename for cache busting');
brand_check(!is_file(private_path().'/'.$oldReseller),'old logo removed after replacement');
$customerContext=$login($customer,5);
brand_check(!brand_identity_edit_allowed($customerContext),'customer cannot alter reseller logo');
$fail=false;
try{brand_identity_store_binary($customerContext,$png);}
catch(InvalidArgumentException){$fail=true;}
brand_check($fail,'no cross-role upload');
$fail=false;
try{brand_identity_save($customerContext,'Attack','text');}
catch(InvalidArgumentException){$fail=true;}
brand_check($fail,'no customer white-label rewrite');
$resellerContext=$login($reseller,2);
brand_identity_remove_logo($resellerContext);
brand_check((string)(account(2)['logo_path']??'')==='','logo link removed');
brand_check(brand_identity_mode($resellerContext)==='text','image only falls back to text on deletion');
brand_check(!is_file(private_path().'/'.$updatedReseller),'image deleted from private storage');
$platform=$login($admin,1);
brand_identity_remove_logo($platform);
brand_check(brand_identity_mode($platform)==='both','text+image mode preserved when removed');
brand_check((string)(account(1)['logo_path']??'')==='','platform logo fully removed');
brand_check(!is_file(private_path().'/'.$name),'platform file removed');
$views=file_get_contents(__DIR__.'/../saas/console/_core/Views.php');
$actions=file_get_contents(__DIR__.'/../saas/console/_core/Actions.php');
$index=file_get_contents(__DIR__.'/../saas/console/index.php');
$white=file_get_contents(__DIR__.'/../saas/console/_core/WhiteLabel.php');
$js=file_get_contents(__DIR__.'/../saas/console/assets/js/app.js');
brand_check(str_contains($views,'data-brand-display') &&
   str_contains($views,'data-brand-form') &&
   str_contains($views,"form_start('brand_logo_remove'") &&
   str_contains($views,'brand_identity_edit_allowed'),
   'logo identity editor includes display choice and remove');
brand_check(str_contains($actions,"case 'brand_logo_remove':") &&
    str_contains($index,"if(\$path==='/brand-upload')") &&
    str_contains($white,'brand_identity_mode($ctx)') &&
    str_contains($js,"fetch('/?ot_brand_upload=1'"),
    'secure raw upload endpoints and public reseller logo route wired');
echo "BRAND_V64_PLATFORM_RESELLER_WIDE_LOGO_MODE_PRIVATE_UPLOAD_DELETE_PASS\n";
