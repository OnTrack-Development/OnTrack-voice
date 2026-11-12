<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/_core/Checkout.php';
require_once __DIR__.'/../saas/console/_core/KashierWebhook.php';
require_once __DIR__.'/../saas/console/_core/OrderClaim.php';
function test_verified_checkout_settlement():void {
    $plan=one("SELECT * FROM plans WHERE kind='user' AND status='active' AND price_minor>0 ORDER BY id LIMIT 1");
    // Demo seed deliberately prices all plans at zero; create a paid plan in the isolated test database.
    if(!$plan){
        $paidPlanId=insert("INSERT INTO plans(kind,name,call_quota,site_limit,agent_limit,staff_limit,reseller_customers,price_minor,status,created_at)
            VALUES ('user','Synthetic paid regression plan',800,2,2,3,0,2575,'active',?)",[now()]);
        $plan=one("SELECT * FROM plans WHERE id=?",[$paidPlanId]);
    }
    ok($plan!==null && (int)$plan['price_minor']===2575,'paid fixture exists only in test DB');
    $accountId=4;
    $reference='ovc_'.bin2hex(random_bytes(16));
    $tx='TX_'.bin2hex(random_bytes(16));
    $prior=(int)one('SELECT COUNT(*) n FROM outbox WHERE account_id=? AND kind=?',
        [$accountId,'subscription.activated'])['n'];
    $orderId=insert("INSERT INTO orders(account_id,integration_id,external_order_id,plan_id,customer_name,customer_email,amount_minor,status,created_at)
        VALUES (?,NULL,?,?,?,?,?,'pending_payment',?)",
        [$accountId,$reference,$plan['id'],'Synthetic Owner','testpay@example.invalid',
            $plan['price_minor'],now()]);
    $data=['amount'=>checkout_amount((int)$plan['price_minor']),'currency'=>'EGP',
        'merchantOrderId'=>$reference,'status'=>'SUCCESS','transactionId'=>$tx,
        'channel'=>'online | e-commerce','method'=>'card'];
    $data['signatureKeys']=array_keys($data);
    $body=['event'=>'pay','data'=>$data];
    $sig=kashier_signature($data,'11111');
    ok(is_string($sig)&&kashier_verified($body,$sig,'11111'),
        'synthetic card payment uses real provider signature algorithm');
    $wrong=$body;$wrong['data']['amount']='9999.99';
    $denied=false;
    try{kashier_settle($wrong,null);}catch(InvalidArgumentException $e){$denied=true;}
    ok($denied && (string)one('SELECT status FROM orders WHERE id=?',[$orderId])['status']==='pending_payment',
        'wrong amount cannot activate or mutate a plan');
    $result=kashier_settle($body,null);
    ok($result['status']==='active'&&!$result['duplicate'] && $result['account_id']===$accountId,
        'signed and amount-matched payment activates only authenticated pre-owned account');
    $record=one('SELECT * FROM orders WHERE id=?',[$orderId]);
    ok($record['payment_reference']===$tx && $record['status']==='active',
        'verified transaction reference persisted on correct order');
    $active=one("SELECT * FROM subscriptions WHERE account_id=? AND status='active' ORDER BY id DESC LIMIT 1",[$accountId]);
    ok($active!==null && (int)$active['plan_id']===(int)$plan['id'],
        'paid plan entitlement activated exactly once');
    $again=kashier_settle($body,null);
    ok($again['status']==='active'&&$again['duplicate'],
        'replayed Kashier callback is idempotent');
    ok((int)one('SELECT COUNT(*) n FROM outbox WHERE account_id=? AND kind=?',
        [$accountId,'subscription.activated'])['n']===$prior+1,
        'subscription activation outbox emitted only once');
    $after=(int)one("SELECT COUNT(*) n FROM subscriptions WHERE account_id=? AND status='active'",[$accountId])['n'];
    ok($after===1,'no duplicate active subscriptions after replay');
    $diff=$body;$diff['data']['transactionId']='TX_'.bin2hex(random_bytes(16));
    $conflict=false;
    try{kashier_settle($diff,null);}catch(InvalidArgumentException $e){$conflict=true;}
    ok($conflict,'different transaction for settled order cannot replace proof');
    echo "KASHIER_SETTLEMENT_E2E_DB_PASS\n";
}
