<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||is_dir('/home/appontrack'))
    throw new RuntimeException('CI-only synthetic signed-manifest fixture');
if(!getenv('ONTRACK_CI_NAMESPACE'))putenv('ONTRACK_CI_NAMESPACE=apkhom'.bin2hex(random_bytes(9)));
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
seed_console();
function expect_dashboard(bool $condition,string $label):void {
    if(!$condition)throw new RuntimeException('DASHBOARD_APK_RESELLER_TEST_FAIL: '.$label);
}
$native=__DIR__.'/../saas/console/mobile/updates/native';
if(is_dir($native))throw new RuntimeException('Refuse overwriting existing test/public OTA path');
mkdir($native,0700,true);
$filename='ontrack-voice-v54-'.str_repeat('a',64).'.apk';
$apk=$native.'/'.$filename;
$fh=fopen($apk,'x');
try{
    if(!$fh||!ftruncate($fh,1000001))throw new RuntimeException('cannot create sparse test APK');
}finally{if($fh)fclose($fh);}
$manifest=$native.'/latest.json';
file_put_contents($manifest,json_encode([
    'payload'=>json_encode([
        'schema'=>1,'channel'=>'stable','package'=>'com.ontrack.voiceproof',
        'file'=>$filename,'version_code'=>54,'version_name'=>'0.3.31',
        'sha256'=>str_repeat('a',64),'size'=>1000001
    ],JSON_THROW_ON_ERROR),
    'signature'=>base64_encode('fixture-signature-not-a-real-certificate')
],JSON_THROW_ON_ERROR));
try{
    $release=ontrack_native_public_release();
    expect_dashboard($release!==null&&$release['code']===54,'canonical public release manifest discovery');
    expect_dashboard(str_starts_with($release['url'],'https://app.ontrackegy.com/mobile/updates/native/'),'white-label safe canonical link');
    $reseller=one("SELECT id FROM users WHERE email='reseller@delta.test'");
    $admin=one("SELECT id FROM users WHERE email='admin@ontrackegy.com'");
    $customer=one("SELECT id FROM users WHERE email='user@direct.test'");
    expect_dashboard($reseller&&$admin&&$customer,'seed user fixtures');
    foreach([[(int)$reseller['id'],2,'reseller'],[(int)$customer['id'],4,'customer']] as $row){
        $_SESSION=['uid'=>$row[0],'account_id'=>$row[1],'console_view'=>'admin'];
        $a=mine();
        expect_dashboard($a!==null&&$a['kind']===$row[2],'active account identity');
        ob_start();render_overview($a);$html=(string)ob_get_clean();
        expect_dashboard(str_contains($html,'ot-direct-apk'),'prominent home APK button '.$row[2]);
        expect_dashboard(str_contains($html,$filename),'home link tracks actual version '.$row[2]);
        expect_dashboard(str_contains($html,'↓ تحميل APK الآن'),'direct home download label '.$row[2]);
    }
    $_SESSION=['uid'=>(int)$reseller['id'],'account_id'=>2,'console_view'=>'admin'];
    expect_dashboard(console_can_switch_view(),'reseller owner allowed to switch view');
    expect_dashboard(console_view_mode()==='admin','normal reseller management mode');
    $_SESSION['console_view']='user';
    expect_dashboard(console_view_mode()==='user','reseller owner user workspace mode');
    expect_dashboard(isset(nav_entries('reseller')['agents']),'reseller user workspace navigation');
    expect_dashboard(!isset(nav_entries('reseller')['accounts']),'reseller customer workspace does not have admin accounts nav');
    $who=mine();
    ob_start();render_overview($who);$html=(string)ob_get_clean();
    expect_dashboard(str_contains($html,'العودة لإدارة الريسيلر'),'home offers switch back');
    expect_dashboard(str_contains($html,'ot-direct-apk'),'reseller user-mode APK download remains');
    // Authorized platform staff entered the reseller via existing support grant.
    $_SESSION=['uid'=>(int)$admin['id'],'account_id'=>1,
        'console_view'=>'admin','support'=>[
            'account_id'=>2,'origin_id'=>1,'origin_view'=>'admin',
            'view'=>'admin','expires'=>time()+1800]];
    $a=mine();
    expect_dashboard($a!==null&&$a['kind']==='reseller' && (int)$a['account_id']===2,
        'support stays bound to reseller tenant');
    expect_dashboard(console_can_switch_support_view($a),'authorized platform support may view reseller workspace');
    expect_dashboard(console_view_mode($a)==='admin','support defaults to reseller administration');
    $_SESSION['support']['view']='user';
    expect_dashboard(console_view_mode($a)==='user','support user preview follows session-scoped view');
    expect_dashboard(isset(nav_entries('reseller')['agents']),'support preview includes user agent navigation');
    expect_dashboard((int)mine()['account_id']===2&&me()['id']===(int)$admin['id'],
        'support preview never impersonates a new account or actor');
    ob_start();render_overview($a);$html=(string)ob_get_clean();
    expect_dashboard(str_contains($html,'set_support_view')&&str_contains($html,'العودة لإدارة الريسيلر'),
        'support reseller homepage offers direct return switch');
    $_SESSION['support']['account_id']=4;
    expect_dashboard(!console_can_switch_support_view($a),'tampered target denied');
    unset($_SESSION['support']);$_SESSION['console_view']='admin';
    expect_dashboard(!console_can_switch_support_view(),'no global support view outside a grant');
    echo "DASHBOARD_APK_AND_RESELLER_USER_SUPPORT_PREVIEW_PASS\n";
}finally{
    @unlink($manifest);@unlink($apk);@rmdir($native);@rmdir(dirname($native));@rmdir(dirname(dirname($native)));
}
