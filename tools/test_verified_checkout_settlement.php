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
    // An already paid 65-day entitlement must survive a NEW paid purchase.
    $previousEnd=gmdate('Y-m-d H:i:s',time()+65*86400);
    execute("UPDATE subscriptions SET ends_at=? WHERE account_id=?
        AND status='active'",[$previousEnd,$accountId]);
    $expectedEnd=gmdate('Y-m-d H:i:s',strtotime($previousEnd)+30*86400);
    $reference='ovc_'.bin2hex(random_bytes(16));
    $tx='TX_'.bin2hex(random_bytes(16));
    $prior=(int)one('SELECT COUNT(*) n FROM outbox WHERE account_id=? AND kind=?',
        [$accountId,'subscription.activated'])['n'];
    $orderId=insert("INSERT INTO orders(account_id,integration_id,external_order_id,plan_id,customer_name,customer_email,amount_minor,status,created_at)
        VALUES (?,NULL,?,?,?,?,?,'pending_payment',?)",
        [$accountId,$reference,$plan['id'],'Synthetic Owner','testpay@example.invalid',
            $plan['price_minor'],now()]);
    // The order holds the historical accepted price; changing a public
    // plan's price after a Kashier session was created cannot void payment.
    execute('UPDATE plans SET price_minor=? WHERE id=?',
        [(int)$plan['price_minor']+1800,(int)$plan['id']]);
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
    ok((string)$active['ends_at']===$expectedEnd,
        'paid renewal adds 30 days after previously paid 65 days without truncation');
    ok((string)one('SELECT status FROM subscriptions
        WHERE account_id=? ORDER BY id LIMIT 1',[$accountId])['status']==='replaced',
        'prior subscription preserved for auditing');
    ok((int)one('SELECT amount_minor FROM orders WHERE id=?',[$orderId])['amount_minor']===
        (int)$plan['price_minor'],'historical order charge remains unchanged after plan repricing');
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
    // A second uniquely charged order cannot restart the clock either.
    $secondRef='ovc_'.bin2hex(random_bytes(16));
    $secondTransaction='TX_'.bin2hex(random_bytes(16));
    $secondOrder=insert("INSERT INTO orders(account_id,integration_id,external_order_id,
        plan_id,customer_name,customer_email,amount_minor,status,created_at)
        VALUES (?,NULL,?,?,?,?,?,'pending_payment',?)",[
        $accountId,$secondRef,$plan['id'],'Synthetic Owner','testpay@example.invalid',
        $plan['price_minor'],now()
    ]);
    $second=$body;
    $second['data']['merchantOrderId']=$secondRef;
    $second['data']['transactionId']=$secondTransaction;
    $secondSettled=kashier_settle($second,null);
    ok($secondSettled['status']==='active' && !$secondSettled['duplicate'],
        'second verified payment settles as an independent 30-day order');
    $endAfterSecond=(string)one('SELECT ends_at FROM subscriptions
        WHERE account_id=? AND status=? ORDER BY id DESC LIMIT 1',
        [$accountId,'active'])['ends_at'];
    ok($endAfterSecond===gmdate('Y-m-d H:i:s',strtotime($expectedEnd)+30*86400),
        'two paid orders credit two distinct 30-day terms without shortening prior time');
    ok((int)one("SELECT COUNT(*) n FROM orders WHERE account_id=? AND status='active'
        AND id IN (?,?)",[$accountId,$orderId,$secondOrder])['n']===2,
        'both financial orders remain independently verifiable');
    echo "KASHIER_PAID_TIME_AND_ORDER_SNAPSHOT_NONREGRESSION_PASS\n";    // Kashier also emits signed refund/partial-refund/void events.
    // Preserve the working plan until a verified human adjustment, but NEVER
    // silently drop financial reversals or create another activation.
    $outboxBefore=(int)one("SELECT COUNT(*) n FROM outbox
        WHERE account_id=? AND kind='billing.adjustment_review'",[$accountId])['n'];
    $refund=$body;
    $refund['event']='refund';
    $refund['data']['transactionId']='RF_'.bin2hex(random_bytes(16));
    $refundSig=kashier_signature($refund['data'],'11111');
    ok(is_string($refundSig)&&kashier_verified($refund,$refundSig,'11111'),
        'Kashier refund test event passes the actual HMAC verifier');
    $review=kashier_settle($refund,null);
    ok($review['status']==='adjustment_pending_review'&&!$review['duplicate'],
        'signed full refund is retained for finance reconciliation');
    $reviewAgain=kashier_settle($refund,null);
    ok($reviewAgain['duplicate']===true,'refund webhook retry is idempotent');
    $changedRefund=$refund;
    $changedRefund['data']['amount']='1.00';
    $changedSig=kashier_signature($changedRefund['data'],'11111');
    ok(is_string($changedSig)&&kashier_verified($changedRefund,$changedSig,'11111'),
        'changed refund amount still uses valid HMAC so state machine must validate replay');
    $conflictRejected=false;
    try{kashier_settle($changedRefund,null);}
    catch(InvalidArgumentException $e){
        $conflictRejected=$e->getMessage()==='adjustment_replay_amount_mismatch';
    }
    ok($conflictRejected,'signed refund transaction cannot replay with conflicting amount');
    $partial=$refund;
    $partial['event']='partial_refund';
    $partial['data']['transactionId']='RF_'.bin2hex(random_bytes(16));
    $partial['data']['amount']='5.00';
    $partialSig=kashier_signature($partial['data'],'11111');
    ok(is_string($partialSig)&&kashier_verified($partial,$partialSig,'11111'),
        'partial refund uses the signed amount and event');
    ok(kashier_settle($partial,null)['duplicate']===false,
        'partial refund is independently recorded without losing full-refund history');
    ok((int)one("SELECT COUNT(*) n FROM kashier_payment_adjustments
        WHERE order_id=?",[$orderId])['n']===2,
        'full and partial refund references retained as separate audit evidence');
    ok((int)one("SELECT COUNT(*) n FROM outbox
        WHERE account_id=? AND kind='billing.adjustment_review'",[$accountId])['n']===$outboxBefore+2,
        'exactly one owner review notice per distinct reversal');
    $failedRefund=$refund;$failedRefund['data']['status']='FAILURE';
    ok(kashier_settle($failedRefund,null)['status']==='ignored_non_success',
        'failed refund event does not mutate financial state');
    $wrongRefund=$refund;$wrongRefund['data']['currency']='USD';
    $wrongDenied=false;
    try{kashier_settle($wrongRefund,null);}catch(InvalidArgumentException){$wrongDenied=true;}
    ok($wrongDenied,'currency-mismatched reversal is rejected');
    $still=one("SELECT ends_at FROM subscriptions
        WHERE account_id=? AND status='active' ORDER BY id DESC LIMIT 1",[$accountId]);
    ok((string)$still['ends_at']===gmdate('Y-m-d H:i:s',
        strtotime($expectedEnd)+30*86400),
        'refund review cannot automatically delete an active plan or reset its duration');
    // Out-of-order webhooks: a refund first must quarantine an unpaid order,
    // and a later pay notification must not provision a reversed charge.
    $futureRef='ovc_'.bin2hex(random_bytes(16));
    $futureOrder=insert("INSERT INTO orders
        (account_id,integration_id,external_order_id,plan_id,customer_name,
         customer_email,amount_minor,status,created_at)
        VALUES (?,NULL,?,?,?,?,?,'pending_payment',?)",[
        $accountId,$futureRef,$plan['id'],'CI future reversal',
        'pending-refund@example.invalid',2575,now()
    ]);
    $futureRefund=$refund;
    $futureRefund['data']['merchantOrderId']=$futureRef;
    $futureRefund['data']['transactionId']='RF_'.bin2hex(random_bytes(16));
    ok(kashier_settle($futureRefund,null)['status']==='adjustment_pending_review',
        'refund delivered before pay can be recorded');
    ok((string)one('SELECT status FROM orders WHERE id=?',[$futureOrder])['status']==='payment_review',
        'reversed yet unpaid order is quarantined from settlement');
    $latePay=$body;
    $latePay['data']['merchantOrderId']=$futureRef;
    $latePay['data']['transactionId']='TX_'.bin2hex(random_bytes(16));
    $lateDenied=false;
    try{kashier_settle($latePay,null);}
    catch(InvalidArgumentException){$lateDenied=true;}
    ok($lateDenied,'late pay event cannot activate a previously reversed order');
    echo "KASHIER_REFUND_REPLAY_AND_OUT_OF_ORDER_REVIEW_PASS\n";

    echo "KASHIER_SETTLEMENT_E2E_DB_PASS\n";
}
