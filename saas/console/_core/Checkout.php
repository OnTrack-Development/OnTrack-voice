<?php
declare(strict_types=1);
/** Authenticated account checkout. Never infer OnTrack identity from a payment email. */
function checkout_config():array {
    $file=private_path().'/kashier.json';
    if(!is_file($file))throw new RuntimeException('Kashier لم يتم إعداده بعد');
    $cfg=json_decode((string)file_get_contents($file),true,16,JSON_THROW_ON_ERROR);
    if(!is_array($cfg)||!in_array($cfg['mode']??null,['test','live'],true)
       ||!is_string($cfg['merchant_id']??null)||!is_string($cfg['payment_api_key']??null)
       ||!is_string($cfg['secret_key']??null)||strlen($cfg['secret_key'])<8)
        throw new RuntimeException('Kashier configuration unavailable');
    return $cfg;
}
function checkout_amount(int $minor):string {
    if($minor<1||$minor>999999999)throw new InvalidArgumentException('invalid_payment_amount');
    return intdiv($minor,100).'.'.str_pad((string)($minor%100),2,'0',STR_PAD_LEFT);
}
function checkout_session_payload(string $reference,int $minor,string $email,string $mid):array {
    if(!preg_match('/^ovc_[a-f0-9]{32}$/D',$reference)
       ||!filter_var($email,FILTER_VALIDATE_EMAIL))
        throw new InvalidArgumentException('checkout_identity_invalid');
    return [
        'expireAt'=>gmdate('Y-m-d\TH:i:s\Z',time()+900),
        'maxFailureAttempts'=>3,'paymentType'=>'credit','amount'=>checkout_amount($minor),
        'currency'=>'EGP','order'=>$reference,'merchantId'=>$mid,
        'merchantRedirect'=>'https://app.ontrackegy.com/saas/console/?tab=billing',
        'serverWebhook'=>'https://app.ontrackegy.com/saas/console/hooks/kashier.php',
        'display'=>'ar','type'=>'one-time','allowedMethods'=>'card,wallet',
        'interactionSource'=>'ECOMMERCE','description'=>'OnTrack Voice subscription',
        'customer'=>['email'=>$email]
    ];
}
function checkout_session_url(string $url):bool {
    $u=parse_url($url);
    return is_array($u)&&($u['scheme']??'')==='https'
        &&($u['host']??'')==='payments.kashier.io'
        &&!isset($u['port'],$u['user'],$u['pass'],$u['fragment'])
        &&str_starts_with($u['path']??'','/session/');
}
function checkout_request(array $config,array $payload):string {
    if(!function_exists('curl_init'))throw new RuntimeException('PHP curl غير متاح');
    $endpoint=$config['mode']==='test'
        ?'https://test-api.kashier.io/v3/payment/sessions'
        :'https://api.kashier.io/v3/payment/sessions';
    $curl=curl_init($endpoint);
    curl_setopt_array($curl,[
        CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_HTTPHEADER=>['Authorization: '.$config['secret_key'],
            'api-key: '.$config['payment_api_key'],'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR)
    ]);
    try{$raw=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);}
    finally{curl_close($curl);}
    if(!is_string($raw)||strlen($raw)>20000||$status<200||$status>=300)
        throw new RuntimeException('تعذر إنشاء جلسة دفع Kashier');
    $reply=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    $url=$reply['sessionUrl']??null;
    if(!is_string($url)||!checkout_session_url($url))
        throw new RuntimeException('Kashier returned invalid payment URL');
    return $url;
}
function checkout_start(int $accountId,int $planId,array $user):string {
    $cfg=checkout_config();
    $account=account($accountId);
    if(!$account||$account['status']!=='active'
       ||!in_array($account['kind'],['reseller','customer'],true))
        throw new InvalidArgumentException('checkout_account_invalid');
    $plan=one('SELECT * FROM plans WHERE id=? AND kind=? AND status=?',[
        $planId,$account['kind']==='reseller'?'reseller':'user','active']);
    if(!$plan)throw new InvalidArgumentException('checkout_plan_unavailable');
    $minor=(int)$plan['price_minor'];$amount=checkout_amount($minor);
    $reference='ovc_'.bin2hex(random_bytes(16));
    $email=(string)$user['email'];
    $body=checkout_session_payload($reference,$minor,$email,(string)$cfg['merchant_id']);
    $id=insert("INSERT INTO orders(account_id,integration_id,external_order_id,plan_id,customer_name,customer_email,amount_minor,status,created_at)
        VALUES (?,NULL,?,?,?,?,?,'pending_payment',?)",[
        $accountId,$reference,$planId,(string)$user['name'],$email,$minor,now()]);
    $url=checkout_request($cfg,$body);
    audit('billing.checkout.created',$accountId,['order_id'=>$id,'plan_id'=>$planId]);
    return $url;
}
/** Direct checkout is bound to a logged-in tenant BEFORE payment, never by email. */
function record_verified_internal_checkout(array $order,string $reference):array {
    if(!preg_match('/^[A-Za-z0-9_-]{8,160}$/D',$reference))
        throw new InvalidArgumentException('invalid_payment_reference');
    if($order['integration_id']!==null
        ||!preg_match('/^ovc_[a-f0-9]{32}$/D',(string)$order['external_order_id']))
        throw new InvalidArgumentException('not_internal_checkout');
    if($order['status']==='active'){
        if(!hash_equals((string)$order['payment_reference'],$reference))
            throw new InvalidArgumentException('payment_reference_conflict');
        return ['status'=>'active','duplicate'=>true,'account_id'=>(int)$order['account_id']];
    }
    if($order['status']!=='pending_payment')throw new InvalidArgumentException('order_not_payable');
    $account=account((int)$order['account_id']);
    $plan=one('SELECT * FROM plans WHERE id=? AND status=?',[$order['plan_id'],'active']);
    if(!$account||$account['status']!=='active'||!$plan
        ||!in_array($account['kind'],['reseller','customer'],true)
        ||$plan['kind']!==($account['kind']==='reseller'?'reseller':'user')
        ||(int)$plan['price_minor']!==(int)$order['amount_minor'])
        throw new InvalidArgumentException('checkout_account_or_plan_changed');
    if(one('SELECT id FROM orders WHERE payment_reference=? AND id<>?',[$reference,$order['id']]))
        throw new InvalidArgumentException('transaction_already_claimed');
    if($account['kind']==='customer'&&!empty($account['parent_id'])){
        $reseller=account((int)$account['parent_id']);
        if(!$reseller||$reseller['status']!=='active')throw new InvalidArgumentException('reseller_unavailable');
        $used=(int)one("SELECT COALESCE(SUM(p.call_quota),0) n FROM subscriptions s
            JOIN plans p ON p.id=s.plan_id JOIN accounts a ON a.id=s.account_id
            WHERE a.parent_id=? AND a.id!=? AND s.status='active'",[
            $reseller['id'],$account['id']])['n'];
        if($used+(int)$plan['call_quota']>site_limit((int)$reseller['id'],'call_quota'))
            throw new InvalidArgumentException('reseller_quota_exceeded');
    }
    $changed=execute("UPDATE orders SET status='active',payment_reference=?
        WHERE id=? AND account_id=? AND integration_id IS NULL AND status='pending_payment'",[
        $reference,$order['id'],$order['account_id']]);
    if($changed!==1)throw new RuntimeException('payment_state_conflict');
    execute("UPDATE subscriptions SET status='replaced' WHERE account_id=? AND status='active'",
        [$order['account_id']]);
    insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at)
        VALUES (?,?,'active',?,?)",[$order['account_id'],$plan['id'],now(),
        gmdate('Y-m-d H:i:s',time()+30*86400)]);
    insert("INSERT INTO outbox(account_id,integration_id,kind,payload,status,created_at)
        VALUES (?,NULL,?,?,'pending',?)",[(int)$order['account_id'],
        'subscription.activated',json_encode(['order_id'=>(int)$order['id'],
            'plan_id'=>(int)$plan['id'],'source'=>'verified_kashier'],JSON_THROW_ON_ERROR),now()]);
    return ['status'=>'active','duplicate'=>false,'account_id'=>(int)$order['account_id']];
}
