<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/StaffInvitations.php';
require_once __DIR__.'/../saas/console/_core/AdminMfa.php';
require_once __DIR__.'/../saas/console/_core/Views.php';

function check_admin_wa(bool $yes,string $what):void {
    if(!$yes)throw new RuntimeException('ADMIN_WHATSAPP_SEPARATION_FAIL: '.$what);
}
seed_console();
$cases=[
    'platform'=>['email'=>'admin@ontrackegy.com','accountKind'=>'platform'],
    'reseller'=>['email'=>'reseller@delta.test','accountKind'=>'reseller'],
    'customer'=>['email'=>'user@direct.test','accountKind'=>'customer']
];
foreach($cases as $role=>$info){
    $member=one("SELECT u.id AS uid,m.account_id AS aid,m.role
        FROM users u JOIN memberships m ON m.user_id=u.id
        JOIN accounts a ON a.id=m.account_id
        WHERE u.email=? AND a.kind=? ORDER BY m.account_id LIMIT 1",[
        $info['email'],$info['accountKind']]);
    check_admin_wa($member!==null,'demo '.$role.' available');
    $_SESSION=['uid'=>(int)$member['uid'],'account_id'=>(int)$member['aid'],'console_view'=>'admin'];
    $a=mine();
    check_admin_wa($a['kind']===$info['accountKind'],'expected account kind');
    $links=nav_entries($a['kind']);
    foreach(['agents','answering','whatsapp','calls','campaigns','contacts','devices','sites'] as $key){
        if($role!=='customer')
            check_admin_wa(!isset($links[$key]),$role.' admin should not display user operation '.$key);
    }
    if($role==='platform'){
        check_admin_wa(isset($links['whatsapp_admin']),'platform admin owns dedicated WhatsApp tab');
        check_admin_wa(!isset($links['whatsapp']),'platform inbox hidden in admin mode');
        ob_start();layout_open($a,'overview');$html=(string)ob_get_clean();
        check_admin_wa(str_contains($html,'?tab=whatsapp_admin'),'admin WhatsApp direct nav link visible');
        check_admin_wa(!str_contains($html,'?tab=whatsapp"'),'user inbox link absent');
        ob_start();render_whatsapp_platform_settings($a);$settings=(string)ob_get_clean();
        check_admin_wa(str_contains($settings,'wa_platform_onboarding_save'),'Meta controls rendered only admin');
        check_admin_wa(str_contains($settings,'Embedded Signup v4 Configuration ID'),'Embedded config rendered');
        check_admin_wa(str_contains($settings,'Coexistence Configuration ID'),'Coexistence config rendered');
        check_admin_wa(!str_contains($settings,'wa_connection_add'),'no user phone registration in admin page');
        ob_start();render_settings($a);$regular=(string)ob_get_clean();
        check_admin_wa(str_contains($regular,'?tab=whatsapp_admin'),'platform settings links to dedicated tab');
        check_admin_wa(!str_contains($regular,'wa_platform_onboarding_save'),'platform Meta form not buried below MFA');
        $_SESSION['console_view']='user';
        $preview=nav_entries($a['kind']);
        check_admin_wa(isset($preview['whatsapp']),'platform user workspace offers WhatsApp Inbox');
        check_admin_wa(!isset($preview['whatsapp_admin']),'admin platform setting hidden in user workspace');
        ob_start();render_settings($a);$userSettings=(string)ob_get_clean();
        check_admin_wa(!str_contains($userSettings,'?tab=whatsapp_admin'),'user preview does not show admin link');
    }elseif($role==='reseller'){
        check_admin_wa(!isset($links['whatsapp_admin']),'reseller cannot configure platform Meta');
        check_admin_wa(isset($links['accounts']),'reseller account management retained');
        $_SESSION['console_view']='user';
        check_admin_wa(isset(nav_entries($a['kind'])['whatsapp']),'reseller user workspace gets Inbox');
    }else{
        check_admin_wa(!isset($links['whatsapp_admin']),'customer has no platform Meta controls');
        check_admin_wa(isset($links['whatsapp']),'customer has own WhatsApp inbox');
    }
}
echo "ADMIN_WHATSAPP_SETTINGS_SEPARATION_PASS\n";
