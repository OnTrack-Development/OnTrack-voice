<?php
declare(strict_types=1);

/**
 * External payment is not proof of ownership of an OnTrack user identity.
 * Keep paid orders in an unclaimed state until verified signed SSO or an
 * authenticated account-claim flow is implemented and tested.
 *
 * This operation intentionally does not create users, accounts, memberships
 * or subscriptions. Never attach an existing user by email from a webhook.
 */
function record_verified_external_payment(array $order,string $paymentReference):array {
    if(!preg_match('/^[a-zA-Z0-9_-]{8,160}$/D',$paymentReference))
        throw new InvalidArgumentException('invalid_payment_reference');
    $status=(string)$order['status'];
    if($status==='active'){
        if((string)($order['payment_reference']??'')!==$paymentReference)
            throw new InvalidArgumentException('payment_reference_conflict');
        return ['status'=>'active','account_id'=>(int)$order['account_id'],'duplicate'=>true];
    }
    if($status==='paid_pending_claim'){
        if((string)$order['payment_reference']!==$paymentReference)
            throw new InvalidArgumentException('payment_reference_conflict');
        return ['status'=>'paid_pending_claim','order_id'=>(int)$order['id'],'claim_required'=>true,'duplicate'=>true];
    }
    if($status!=='pending_payment')
        throw new InvalidArgumentException('order_not_payable');
    $changed=execute("UPDATE orders SET status='paid_pending_claim',payment_reference=? WHERE id=? AND integration_id=? AND status='pending_payment'",
        [$paymentReference,$order['id'],$order['integration_id']]);
    if($changed!==1)throw new RuntimeException('payment_state_conflict');
    $payload=json_encode([
        'order_id'=>(int)$order['id'],
        'external_order_id'=>(string)$order['external_order_id'],
        'status'=>'paid_pending_claim',
        'claim_required'=>true,
    ],JSON_UNESCAPED_SLASHES);
    insert("INSERT INTO outbox(account_id,integration_id,kind,payload,status,created_at) VALUES (?,?,?,?,'pending',?)",
        [(int)$order['account_id'],(int)$order['integration_id'],'payment.received_pending_claim',$payload,now()]);
    require_once __DIR__.'/BillingInvoices.php';
    billing_invoice_settle((int)$order['id'],$paymentReference);
    return ['status'=>'paid_pending_claim','order_id'=>(int)$order['id'],'claim_required'=>true,'duplicate'=>false];
}
