<?php
declare(strict_types=1);
/** Disposable local-db verification of human review and cross-tenant safety. */
if(PHP_SAPI!=='cli'||is_dir('/home/appontrack')||
    !preg_match('/^[A-Za-z0-9_-]{8,60}$/D',(string)getenv('ONTRACK_CI_NAMESPACE')))
    throw new RuntimeException('refund_review_test_isolation_required');
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/OrderClaim.php';
require_once __DIR__.'/../saas/console/_core/Checkout.php';
require_once __DIR__.'/../saas/console/_core/KashierWebhook.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
function refund_ui_ok(bool $ok,string $message):void{
    if(!$ok)throw new RuntimeException('REFUND_UI_NONREGRESSION_FAIL '.$message);
}
seed_console();
$plan=insert("INSERT INTO plans
    (kind,name,call_quota,site_limit,agent_limit,staff_limit,reseller_customers,
     price_minor,status,created_at)
    VALUES ('user','Refund UI CI plan',100,1,1,1,0,2575,'active',?)",[now()]);
function refund_ui_create_adjustment(int $account,int $plan,string $kind):array{
    $reference='ovc_'.bin2hex(random_bytes(16));
    $orderId=insert("INSERT INTO orders
        (account_id,integration_id,external_order_id,plan_id,customer_name,customer_email,
         amount_minor,status,payment_reference,created_at)
        VALUES (?,NULL,?,?,?,?,?,'active',?,?)",[
        $account,$reference,$plan,'CI safe refund', 'ci@example.invalid',2575,
        'TX_PAID_'.bin2hex(random_bytes(10)),now()
    ]);
    $data=['amount'=>'5.00','currency'=>'EGP','merchantOrderId'=>$reference,
        'status'=>'SUCCESS','transactionId'=>'RF_'.bin2hex(random_bytes(16)),
        'method'=>'card'];
    $data['signatureKeys']=array_keys($data);
    $body=['event'=>$kind,'data'=>$data];
    $sig=kashier_signature($data,'ci-signature-refund-only');
    refund_ui_ok(is_string($sig) && kashier_verified($body,$sig,'ci-signature-refund-only'),
        'synthetic adjustment actually passes HMAC validation');
    $result=kashier_settle($body,null);
    refund_ui_ok($result['status']==='adjustment_pending_review'&&!$result['duplicate'],
        'verified adjustment recorded once');
    return [$orderId,$reference];
}
[$mineId,$mineRef]=refund_ui_create_adjustment(4,$plan,'refund');
[$resellerId,$resellerRef]=refund_ui_create_adjustment(5,$plan,'partial_refund');
function refund_ui_render(array $tenant):string{
    ob_start();render_billing($tenant);return (string)ob_get_clean();
}
$customer=refund_ui_render(['account_id'=>4,'kind'=>'customer','role'=>'owner']);
refund_ui_ok(str_contains($customer,$mineRef) &&
    !str_contains($customer,$resellerRef) &&
    str_contains($customer,'مراجعات الاسترداد'),
    'customer sees its own refund review but cannot see another tenant');
$reseller=refund_ui_render(['account_id'=>2,'kind'=>'reseller','role'=>'owner']);
refund_ui_ok(str_contains($reseller,$resellerRef) &&
    !str_contains($reseller,$mineRef),
    'reseller sees only owned customer refunds, not independent customers');
$platform=refund_ui_render(['account_id'=>1,'kind'=>'platform','role'=>'owner']);
refund_ui_ok(str_contains($platform,$mineRef) &&
    str_contains($platform,$resellerRef),
    'platform owner can reconcile refunds across all accounts');
$viewer=refund_ui_render(['account_id'=>4,'kind'=>'customer','role'=>'viewer']);
refund_ui_ok(!str_contains($viewer,$mineRef),
    'viewer cannot inspect financial adjustment review');
refund_ui_ok((string)one('SELECT review_status FROM kashier_payment_adjustments
    WHERE order_id=?',[$mineId])['review_status']==='pending_review',
    'finance adjustment remains pending explicit human verification');
refund_ui_ok((int)one('SELECT COUNT(*) n FROM subscriptions
    WHERE account_id=4 AND status=?',['active'])['n']===1,
    'viewing refund review cannot cancel a paid active subscription');
echo "SAAS_REFUND_READONLY_FINANCE_REVIEW_TENANT_SCOPE_PASS\n";
