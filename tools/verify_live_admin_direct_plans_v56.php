<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!is_dir('/home/appontrack/public_html'))
    throw new RuntimeException('Approved canonical host read-only check only');
$root='/home/appontrack/public_html/';
require_once $root.'_core/Bootstrap.php';
require_once $root.'_core/SubscriptionPlans.php';
require_once $root.'_core/Views.php';
$actions=(string)file_get_contents($root.'_core/Actions.php');
$views=(string)file_get_contents($root.'_core/Views.php');
$policies=(string)file_get_contents($root.'_core/SubscriptionPlans.php');
$admin=['kind'=>'platform','role'=>'owner'];
$reseller=['kind'=>'reseller','status'=>'active','role'=>'owner'];
$customer=['kind'=>'customer','status'=>'active'];
$paidR=['id'=>4,'kind'=>'reseller','price_minor'=>10000,'status'=>'active'];
$paidU=['id'=>7,'kind'=>'user','price_minor'=>1000,'status'=>'active'];
$freeR=['id'=>5,'kind'=>'reseller','price_minor'=>0,'status'=>'active'];
$allowed=subscription_admin_grant_allowed($admin,$reseller,$paidR,false,'admin')
    &&subscription_admin_grant_allowed($admin,$customer,$paidU,false,'admin')
    &&!subscription_admin_grant_allowed($admin,$reseller,$paidU,false,'admin')
    &&!subscription_admin_grant_allowed($admin,$reseller,$paidR,true,'admin')
    &&!subscription_admin_grant_allowed($admin,$reseller,$paidR,false,'user')
    &&subscription_self_free_allowed($reseller,$freeR,false)
    &&!subscription_self_free_allowed($reseller,$paidR,false);
$pass=$allowed
    &&str_contains($actions,"case 'admin_grant_plan':")
    &&str_contains($actions,"case 'self_select_free_plan':")
    &&str_contains($actions,"!subscription_admin_grant_allowed(")
    &&str_contains($actions,"'payment_recorded'=>false")
    &&str_contains($actions,"site_limit($parent,'call_quota')")
    &&str_contains($views,"form_start('admin_grant_plan'")
    &&str_contains($views,"form_start('self_select_free_plan'")
    &&str_contains($views,"form_start('checkout_plan'")
    &&str_contains($views,"name=\"confirm_admin_grant\"")
    &&str_contains($policies,'subscription_assign_demo');
echo json_encode([
    'result'=>$pass?'PASS':'FAIL',
    'platform_can_assign_priced_reseller_plan'=>$allowed,
    'admin_account_plan_editor'=>str_contains($views,"form_start('admin_grant_plan'"),
    'reseller_self_free_selection'=>str_contains($views,"form_start('self_select_free_plan'"),
    'paid_plans_use_checkout'=>str_contains($views,"form_start('checkout_plan'"),
    'no_payment_receipt_generated'=>str_contains($actions,"'payment_recorded'=>false"),
    'database_mutations'=>false,'secrets_read'=>false
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
exit($pass?0:2);
