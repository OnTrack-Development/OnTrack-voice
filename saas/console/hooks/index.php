<?php
declare(strict_types=1);
require __DIR__.'/../_core/Bootstrap.php';
function respond(int $code,array $body):never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')respond(405,['ok'=>false,'error'=>'method_not_allowed']);
$raw=(string)file_get_contents('php://input');
if(strlen($raw)>32768)respond(413,['ok'=>false,'error'=>'payload_too_large']);
$bearer=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
if(!str_starts_with($bearer,'Bearer '))respond(401,['ok'=>false,'error'=>'auth']);
$token=substr($bearer,7);
$timestamp=(string)($_SERVER['HTTP_X_ONTRACK_TIMESTAMP']??'');
$signature=(string)($_SERVER['HTTP_X_ONTRACK_SIGNATURE']??'');
if(!preg_match('/^\d{10}$/D',$timestamp)||abs(time()-(int)$timestamp)>300||
   !preg_match('/^[a-f0-9]{64}$/D',$signature))respond(401,['ok'=>false,'error'=>'signature']);
$expected=hash_hmac('sha256',$timestamp."\n".$raw,$token);
if(!hash_equals($expected,$signature))respond(401,['ok'=>false,'error'=>'signature']);
try{
    init_schema();
    $integration=one("SELECT * FROM integrations WHERE token_hash=? AND status='active'",[hash('sha256',$token)]);
    if(!$integration)respond(401,['ok'=>false,'error'=>'integration_not_found']);
    if(!in_array($integration['provider'],['whmcs','laravel','webhook'],true))
        respond(403,['ok'=>false,'error'=>'provider_not_authorized']);
    $data=json_decode($raw,true);
    if(!is_array($data))respond(422,['ok'=>false,'error'=>'json']);
    $eventId=(string)($data['event_id']??'');
    $kind=(string)($data['type']??'');
    if(!preg_match('/^[a-zA-Z0-9_.:-]{8,128}$/D',$eventId))respond(422,['ok'=>false,'error'=>'event_id']);
    if(!in_array($kind,['order.created','payment.confirmed','registration.created','booking.created'],true))
        respond(422,['ok'=>false,'error'=>'event_type']);
    $payload=$data['data']??null;
    if(!is_array($payload))respond(422,['ok'=>false,'error'=>'payload']);
    $reseller=(int)$integration['account_id'];
    $resellerAccount=account($reseller);
    if(!$resellerAccount||!in_array($resellerAccount['kind'],['platform','reseller','customer'],true))
        respond(403,['ok'=>false,'error'=>'tenant']);
    if($resellerAccount['kind']==='customer' && $kind!=='booking.created')respond(403,['ok'=>false,'error'=>'customer_scope']);
    db()->beginTransaction();
    try{
        $prior=one('SELECT * FROM events WHERE integration_id=? AND external_id=?',[$integration['id'],$eventId]);
        if($prior){
            db()->commit();
            respond(200,['ok'=>true,'duplicate'=>true,'event_id'=>$eventId,'status'=>$prior['status']]);
        }
        $event=insert("INSERT INTO events(integration_id,external_id,event_type,payload,status,created_at) VALUES (?,?,?,?,'received',?)",[$integration['id'],$eventId,$kind,$raw,now()]);
        $result=['ok'=>true,'event_id'=>$eventId,'duplicate'=>false,'status'=>'received'];
        if(in_array($kind,['order.created','registration.created'],true)){
            $order=(string)($payload['external_order_id']??$eventId);
            $email=strtolower(trim((string)($payload['email']??'')));
            $name=trim((string)($payload['name']??''));
            $pid=(int)($payload['plan_id']??0);
            $plan=one("SELECT * FROM plans WHERE id=? AND kind='user' AND status='active'",[$pid]);
            if(!$plan||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($name)>120||$name===''||mb_strlen($order)>120)
                throw new InvalidArgumentException('invalid_order');
            $exists=one('SELECT id FROM orders WHERE integration_id=? AND external_order_id=?',[$integration['id'],$order]);
            $orderId=$exists['id']??insert("INSERT INTO orders(account_id,integration_id,external_order_id,plan_id,customer_name,customer_email,amount_minor,status,created_at) VALUES (?,?,?,?,?,?,?,'pending_payment',?)",[$reseller,$integration['id'],$order,$pid,$name,$email,(int)$plan['price_minor'],now()]);
            $result['order_id']=$orderId;$result['status']='pending_payment';
        }elseif($kind==='payment.confirmed'){
            $orderNo=(string)($payload['external_order_id']??'');
            $order=one("SELECT o.*,p.price_minor,p.kind plan_kind FROM orders o JOIN plans p ON p.id=o.plan_id WHERE o.integration_id=? AND o.external_order_id=?",[$integration['id'],$orderNo]);
            if(!$order)throw new InvalidArgumentException('order_missing');
            if((int)($payload['amount_minor']??-1)!==(int)$order['amount_minor']||
                ($payload['currency']??'')!=='EGP'||
                ($payload['payment_status']??'')!=='paid'||
                !preg_match('/^[a-zA-Z0-9_-]{8,160}$/D',(string)($payload['payment_reference']??'')))
                throw new InvalidArgumentException('payment_not_verified');
            if($order['status']==='active'){
                $result['status']='active';$result['account_id']=$order['account_id'];
            }else{
                // The caller is the trusted server-side WHMCS/Laravel integration;
                // browser success redirects never access this endpoint.
                $parent=$resellerAccount['kind']==='reseller'?$reseller:null;
                if($parent){
                    $quota=(int)one("SELECT p.call_quota n FROM subscriptions s JOIN plans p ON s.plan_id=p.id WHERE s.account_id=? AND s.status='active' ORDER BY s.id DESC LIMIT 1",[$parent])['n'];
                    $allocated=(int)one("SELECT COALESCE(SUM(p.call_quota),0) n FROM subscriptions s JOIN plans p ON p.id=s.plan_id JOIN accounts a ON a.id=s.account_id WHERE a.parent_id=? AND s.status='active'",[$parent])['n'];
                    $want=(int)one('SELECT call_quota FROM plans WHERE id=?',[$order['plan_id']])['call_quota'];
                    if($allocated+$want>$quota)throw new RuntimeException('reseller_pool_exhausted');
                }
                $slug='customer-'.bin2hex(random_bytes(7));
                $customer=insert("INSERT INTO accounts(kind,parent_id,name,slug,status,created_at) VALUES ('customer',?,?,?,'active',?)",[$parent,$order['customer_name'],$slug,now()]);
                $user=one('SELECT id FROM users WHERE email=?',[$order['customer_email']]);
                if($user)$uid=(int)$user['id'];
                else{
                    // Invitation is generated but cannot be claimed without a verified
                    // delivery bridge; no email is sent to an untrusted address.
                    $randomPassword=bin2hex(random_bytes(32));
                    $uid=insert("INSERT INTO users(name,email,password_hash,status,created_at) VALUES (?,?,?,'active',?)",[$order['customer_name'],$order['customer_email'],hash_password($randomPassword),now()]);
                }
                insert('INSERT INTO memberships(user_id,account_id,role,created_at) VALUES (?,?,?,?)',[$uid,$customer,'owner',now()]);
                insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at) VALUES (?,?,'active',?,?)",[$customer,$order['plan_id'],now(),gmdate('Y-m-d H:i:s',time()+30*86400)]);
                execute("UPDATE orders SET account_id=?,status='active',payment_reference=? WHERE id=?",[$customer,$payload['payment_reference'],$order['id']]);
                insert("INSERT INTO outbox(account_id,integration_id,kind,payload,status,created_at) VALUES (?,?,?,?,'pending',?)",[$customer,$integration['id'],'subscription.activated',json_encode(['account_id'=>$customer,'external_order_id'=>$orderNo,'email'=>$order['customer_email']],JSON_UNESCAPED_UNICODE),now()]);
                $result['status']='active';$result['account_id']=$customer;
            }
        }elseif($kind==='booking.created'){
            $siteId=(int)($payload['site_id']??0);
            $site=one("SELECT id FROM sites WHERE id=? AND account_id=? AND status='active'",[$siteId,$reseller]);
            if(!$site)throw new InvalidArgumentException('site_not_found');
            $name=trim((string)($payload['name']??''));
            $phone=trim((string)($payload['phone']??''));
            if($name===''||mb_strlen($name)>120||!preg_match('/^\\+?[0-9 ()-]{8,30}$/D',$phone))
                throw new InvalidArgumentException('invalid_booking_contact');
            $consent=($payload['consent']??false)===true;
            // An unconsented booking is recorded in DNC to prevent automated contact.
            $lead=insert('INSERT INTO contacts(account_id,site_id,name,phone,dnc,created_at) VALUES (?,?,?,?,?,?)',
                [$reseller,$siteId,$name,$phone,$consent?0:1,now()]);
            $result['status']='lead_created_pending_review';
            $result['contact_id']=$lead;
            $result['dial_requested']=false;
        }
        execute("UPDATE events SET status=? WHERE id=?",[$result['status'],$event]);
        db()->commit();
        respond(200,$result);
    }catch(Throwable $error){
        if(db()->inTransaction())db()->rollBack();
        respond($error instanceof InvalidArgumentException?422:409,['ok'=>false,'error'=>$error instanceof InvalidArgumentException?$error->getMessage():'unable_to_provision']);
    }
}catch(Throwable $e){respond(503,['ok'=>false,'error'=>'server_unavailable']);}
