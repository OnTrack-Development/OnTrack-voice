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
 * Financial reversal needs distinct, signed proof of the ORIGINAL payment.
 * Event names are outside Kashier's signatureKeys, so never reverse a
 * subscription from the event name alone. Partial/ambiguous refunds become
 * auditable manual-review items, never speculative cancellations.
 */
function kashier_refund_init():void {
    $mysql=driver()==='mysql';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    db()->exec("CREATE TABLE IF NOT EXISTS kashier_refund_events (
        refund_reference VARCHAR(160) PRIMARY KEY,
        order_id BIGINT NOT NULL,
        original_reference VARCHAR(160) NOT NULL,
        event_kind VARCHAR(24) NOT NULL,
        amount_minor BIGINT NOT NULL,
        status VARCHAR(24) NOT NULL,
        created_at VARCHAR(19) NOT NULL
    )".$engine);
}
function kashier_refund_settle(array $body,?int $integrationId):array {
    $data=$body['data'];
    if(($data['status']??null)!=='SUCCESS')
        return ['status'=>'ignored_non_success','duplicate'=>false];
    // A signed originalTransactionId is required. Without it the legitimate
    // pay receipt could be relabelled as a reversal (event is not in HMAC).
    $signed=$data['signatureKeys']??[];
    if(!is_array($signed)||!in_array('originalTransactionId',$signed,true))
        throw new InvalidArgumentException('refund_original_payment_proof_missing');
    $orderNo=(string)($data['merchantOrderId']??'');
    $ref=(string)($data['transactionId']??'');
    $original=(string)($data['originalTransactionId']??'');
    $minor=kashier_minor_units($data['amount']??null);
    if(!preg_match('/^[A-Za-z0-9_.:-]{1,120}$/D',$orderNo)||
       !preg_match('/^[A-Za-z0-9_-]{8,160}$/D',$ref)||
       !preg_match('/^[A-Za-z0-9_-]{8,160}$/D',$original)||
       hash_equals($ref,$original)||$minor===null||$minor<1||
       ($data['currency']??null)!=='EGP')
        throw new InvalidArgumentException('refund_payment_fields_invalid');
    kashier_refund_init(); // MySQL DDL must run OUTSIDE settlement transaction.
    db()->beginTransaction();
    try{
        if(str_starts_with($orderNo,'ovc_'))
            $order=one('SELECT * FROM orders WHERE integration_id IS NULL AND external_order_id=?',[$orderNo]);
        else {
            if($integrationId===null)throw new InvalidArgumentException('refund_integration_missing');
            $order=one('SELECT * FROM orders WHERE integration_id=? AND external_order_id=?',
                [$integrationId,$orderNo]);
        }
        if(!$order || !in_array((string)$order['status'],
                ['active','refunded','paid_pending_claim'],true) ||
           !hash_equals((string)($order['payment_reference']??''),$original) ||
           $minor>(int)$order['amount_minor'])
            throw new InvalidArgumentException('refund_order_or_original_transaction_mismatch');
        $old=one('SELECT * FROM kashier_refund_events WHERE refund_reference=?',[$ref]);
        if($old){
            if((int)$old['order_id']!==(int)$order['id']||
               !hash_equals((string)$old['original_reference'],$original)||
               (int)$old['amount_minor']!==$minor||
               (string)$old['event_kind']!==(string)$body['event'])
                throw new InvalidArgumentException('refund_reference_replay_conflict');
            db()->commit();
            return ['status'=>(string)$old['status'],'duplicate'=>true];
        }
        $status='review_required';
        $full=$minor===(int)$order['amount_minor'];
        // A prior partial refund, even if already reviewed, makes the
        // financial total ambiguous. Never silently revoke unrelated paid
        // renewal time or mark the whole order fully reconciled.
        $priorRefund=one('SELECT refund_reference FROM kashier_refund_events WHERE order_id=? LIMIT 1',
            [(int)$order['id']]);
        // Kashier signs data.signatureKeys, not the outer webhook event.
        // A mutable unsigned event name must never authorize entitlement
        // cancellation. Only a matching signed eventType can auto-settle;
        // otherwise keep the verified payment and flag for manual review.
        $signedEvent=in_array('eventType',$signed,true) &&
            is_string($data['eventType']??null) &&
            hash_equals((string)$body['event'],(string)$data['eventType']);
        if($full && !$priorRefund && $signedEvent &&
           $order['integration_id']===null &&
           $order['status']==='active' &&
           in_array($body['event'],['refund','reversal'],true)){
            // Only the exact paid subscription created by this payment may be
            // cancelled. Legacy receipts without a subscription_id require
            // human reconciliation instead of revoking an unrelated plan.
            $events=all("SELECT payload FROM outbox WHERE account_id=? AND
                kind='subscription.activated' ORDER BY id DESC LIMIT 500",
                [(int)$order['account_id']]);
            $subId=0;
            foreach($events as $entry){
                $record=json_decode((string)$entry['payload'],true);
                if(is_array($record) && (int)($record['order_id']??0)===(int)$order['id']){
                    $subId=(int)($record['subscription_id']??0);
                    break;
                }
            }
            $subscription=$subId>0?one('SELECT id,account_id,status FROM subscriptions WHERE id=? AND account_id=?',
                [$subId,(int)$order['account_id']]):null;
            if($subscription && $subscription['status']==='active'){
                // Never call a replaced/historical plan fully reconciled.
                // Its unused paid time may have been carried into a newer
                // subscription, requiring an explicit prorated adjustment.
                if(execute("UPDATE subscriptions SET status='cancelled'
                    WHERE id=? AND account_id=? AND status='active'",
                    [$subId,(int)$order['account_id']])!==1)
                    throw new RuntimeException('refund_subscription_write_conflict');
                execute("UPDATE orders SET status='refunded'
                    WHERE id=? AND integration_id IS NULL AND status='active'",
                    [(int)$order['id']]);
                $status='refunded';
            }
        }
        insert("INSERT INTO kashier_refund_events
            (refund_reference,order_id,original_reference,event_kind,amount_minor,status,created_at)
            VALUES (?,?,?,?,?,?,?)",[
            $ref,(int)$order['id'],$original,(string)$body['event'],$minor,$status,now()
        ]);
        insert("INSERT INTO outbox(account_id,integration_id,kind,payload,status,created_at)
            VALUES (?,?,?,?, 'pending',?)",[
            (int)$order['account_id'],$order['integration_id'],
            $status==='refunded'?'payment.refunded':'payment.refund_review',
            json_encode(['order_id'=>(int)$order['id'],'refund_reference'=>$ref,
                'result'=>$status,'amount_minor'=>$minor],JSON_THROW_ON_ERROR),now()
        ]);
        db()->commit();
        return ['status'=>$status,'duplicate'=>false];
    }catch(Throwable $error){
        if(db()->inTransaction())db()->rollBack();
        throw $error;
    }
}
/** A signed event does not activate a user or grant paid entitlements. */
function kashier_settle(array $body,?int $integrationId):array {
    $data=$body['data'];
    if(in_array($body['event'],['refund','partial_refund','reversal','void'],true))
        return kashier_refund_settle($body,$integrationId);
    if(!in_array($body['event'],['pay','capture'],true)||($data['status']??'')!=='SUCCESS')
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
