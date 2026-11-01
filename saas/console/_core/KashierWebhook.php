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
/** A signed event does not activate a user or grant paid entitlements. */
function kashier_settle(array $body,?int $integrationId):array {
    $data=$body['data'];
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
