<?php
declare(strict_types=1);
/** Kashier payment webhooks only (not payouts). HMAC uses the Payment API Key. */
function kashier_signature(array $data,string $apiKey):?string {
    $keys=$data['signatureKeys']??null;
    if(!is_array($keys)||!array_is_list($keys)||count($keys)<5||count($keys)>30)return null;
    $seen=[];
    foreach($keys as $key){
        if(!is_string($key)||!preg_match('/^[a-zA-Z][a-zA-Z0-9]{0,63}$/D',$key)
          ||isset($seen[$key])||!array_key_exists($key,$data)
          ||!is_scalar($data[$key]))return null;
        $seen[$key]=true;
    }
    foreach(['amount','currency','merchantOrderId','status','transactionId'] as $required)
        if(!isset($seen[$required]))return null;
    sort($keys,SORT_STRING);$parts=[];
    foreach($keys as $key) {
        $value=$data[$key];
        if(is_bool($value))$value=$value?'true':'false';
        $parts[]=$key.'='.rawurlencode((string)$value);
    }
    return hash_hmac('sha256',implode('&',$parts),$apiKey);
}
function kashier_verified(array $body,string $signature,string $apiKey):bool {
    if($apiKey===''||!preg_match('/^[A-Fa-f0-9]{64}$/D',$signature))return false;
    $data=$body['data']??null;
    if(!is_array($data)||!in_array($body['event']??null,
        ['pay','capture','authorize','refund','partial_refund','void','reversal','reject','idempotency'],true))return false;
    $expected=kashier_signature($data,$apiKey);
    return $expected!==null && hash_equals($expected,strtolower($signature));
}
function kashier_minor_units(mixed $amount):?int {
    if(!is_string($amount)&&!is_int($amount)&&!is_float($amount))return null;
    $value=(string)$amount;
    if(!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D',$value,$m))return null;
    return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
}
/**
 * A successful refund/void/reversal must not vanish from the finance audit.
 * Keep the event idempotently for owner review, without automatically
 * deleting subscriptions, balances or payment references. This function is
 * called only after kashier_verified() in the public webhook endpoint.
 */
function kashier_adjustment_schema():void {
    $engine=driver()==='mysql'
        ?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS kashier_payment_adjustments (
        fingerprint VARCHAR(64) PRIMARY KEY,
        order_id BIGINT NOT NULL,
        account_id BIGINT NOT NULL,
        event_type VARCHAR(24) NOT NULL,
        transaction_id VARCHAR(160) NOT NULL,
        amount_minor BIGINT NOT NULL,
        review_status VARCHAR(24) NOT NULL,
        created_at VARCHAR(19) NOT NULL
    )".$engine);
}
function kashier_record_adjustment(array $body,?int $integrationId):array {
    $event=(string)($body['event']??'');
    $data=$body['data']??[];
    if(!in_array($event,['refund','partial_refund','void','reversal'],true)
       ||!is_array($data)||($data['status']??'')!=='SUCCESS')
        throw new InvalidArgumentException('invalid_adjustment_event');
    $merchantOrderId=$data['merchantOrderId']??null;
    $transactionId=$data['transactionId']??null;
    if(!is_string($merchantOrderId)||
       !preg_match('/^[A-Za-z0-9_.:-]{1,120}$/D',$merchantOrderId)||
       !is_string($transactionId)||
       !preg_match('/^[A-Za-z0-9_-]{8,160}$/D',$transactionId)||
       ($data['currency']??'')!=='EGP')
        throw new InvalidArgumentException('invalid_adjustment_fields');
    $amount=kashier_minor_units($data['amount']??null);
    if($amount===null||$amount<1)
        throw new InvalidArgumentException('invalid_adjustment_amount');
    // DDL is outside a transaction: MySQL CREATE TABLE implicitly commits.
    kashier_adjustment_schema();
    db()->beginTransaction();
    try {
        if(str_starts_with($merchantOrderId,'ovc_')){
            $order=one('SELECT * FROM orders
                WHERE integration_id IS NULL AND external_order_id=?',
                [$merchantOrderId]);
        }else{
            if($integrationId===null)
                throw new InvalidArgumentException('external_integration_unconfigured');
            $order=one('SELECT * FROM orders
                WHERE integration_id=? AND external_order_id=?',
                [$integrationId,$merchantOrderId]);
        }
        if(!$order||$amount>(int)$order['amount_minor']||
           !in_array($order['status'],
               ['pending_payment','active','paid_pending_claim','payment_review'],true))
            throw new InvalidArgumentException('adjustment_order_mismatch');
        if(driver()==='mysql')
            one('SELECT id FROM orders WHERE id=? FOR UPDATE',[(int)$order['id']]);
        $fingerprint=hash('sha256',
            (int)$order['id'].':'.$event.':'.$transactionId.':SUCCESS');
        $existing=one('SELECT fingerprint FROM kashier_payment_adjustments
            WHERE fingerprint=?',[$fingerprint]);
        if($existing){
            db()->commit();
            return ['status'=>'adjustment_pending_review','duplicate'=>true,
                'order_id'=>(int)$order['id']];
        }
        // If the refund arrives before the original pay event, prevent a
        // later pay webhook activating an already reversed/voided order.
        // For an active purchase keep existing grants intact until finance
        // explicitly reviews; no silent entitlement rollback.
        if($order['status']==='pending_payment')
            execute("UPDATE orders SET status='payment_review'
                WHERE id=? AND status='pending_payment'",[$order['id']]);
        insert('INSERT INTO kashier_payment_adjustments
            (fingerprint,order_id,account_id,event_type,transaction_id,
             amount_minor,review_status,created_at)
            VALUES (?,?,?,?,?,?,?,?)',[
            $fingerprint,(int)$order['id'],(int)$order['account_id'],
            $event,$transactionId,$amount,'pending_review',now()
        ]);
        insert("INSERT INTO outbox
            (account_id,integration_id,kind,payload,status,created_at)
            VALUES (?,?,?,?,'pending',?)",[
            (int)$order['account_id'],$order['integration_id'],
            'billing.adjustment_review',json_encode([
                'order_id'=>(int)$order['id'],'type'=>$event,
                'amount_minor'=>$amount,'needs_human_review'=>true
            ],JSON_THROW_ON_ERROR),now()
        ]);
        db()->commit();
        return ['status'=>'adjustment_pending_review','duplicate'=>false,
            'order_id'=>(int)$order['id']];
    }catch(Throwable $error){
        if(db()->inTransaction())db()->rollBack();
        throw $error;
    }
}

/** A signed event does not activate a user or grant paid entitlements. */
function kashier_settle(array $body,?int $integrationId):array {
    $data=$body['data'];
    if(($data['status']??'')!=='SUCCESS')
        return ['status'=>'ignored_non_success','duplicate'=>false];
    if(in_array($body['event'],['refund','partial_refund','void','reversal'],true))
        return kashier_record_adjustment($body,$integrationId);
    if(!in_array($body['event'],['pay','capture'],true))
        return ['status'=>'ignored_non_success','duplicate'=>false];
    $orderId=$data['merchantOrderId']??null;$tx=$data['transactionId']??null;
    if(!is_string($orderId)||!preg_match('/^[A-Za-z0-9_.:-]{1,120}$/D',$orderId)
        ||!is_string($tx)||!preg_match('/^[A-Za-z0-9_-]{8,160}$/D',$tx)
        ||($data['currency']??'')!=='EGP')throw new InvalidArgumentException('invalid_payment_fields');
    $minor=kashier_minor_units($data['amount']??null);
    if($minor===null)throw new InvalidArgumentException('invalid_amount');
    db()->beginTransaction();
    try{
        if(str_starts_with($orderId,'ovc_')){
            $order=one('SELECT * FROM orders WHERE integration_id IS NULL AND external_order_id=?',[$orderId]);
        }else{
            if($integrationId===null)throw new InvalidArgumentException('external_integration_unconfigured');
            $order=one('SELECT * FROM orders WHERE integration_id=? AND external_order_id=?',
                [$integrationId,$orderId]);
        }
        if(!$order || (int)$order['amount_minor']!==$minor)
            throw new InvalidArgumentException('order_mismatch');
        $result=$order['integration_id']===null
            ?record_verified_internal_checkout($order,$tx)
            :record_verified_external_payment($order,$tx);
        db()->commit();return $result;
    }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
}
