<?php
declare(strict_types=1);
require __DIR__.'/../_core/Bootstrap.php';
require __DIR__.'/../_core/OrderClaim.php';
require __DIR__.'/../_core/DncGuard.php';
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
    // The first-run ledger backfill must complete before the booking transaction.
    if($kind==='booking.created')dnc_init_ledger();
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
            // A server-signed payment callback is NOT a verified customer login.
            // Record settlement without attaching an existing OnTrack user by email.
            $result=array_merge($result,record_verified_external_payment($order,(string)$payload['payment_reference']));
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
            $dnc=dnc_effective_flag($reseller,$phone,!$consent,'booking_no_consent');
            $lead=insert('INSERT INTO contacts(account_id,site_id,name,phone,dnc,created_at) VALUES (?,?,?,?,?,?)',
                [$reseller,$siteId,$name,$phone,$dnc,now()]);
            $result['status']='lead_created_pending_review';
            $result['contact_id']=$lead;
            $result['dial_requested']=false;
            $result['dnc']=$dnc===1;
        }
        execute("UPDATE events SET status=? WHERE id=?",[$result['status'],$event]);
        db()->commit();
        respond(200,$result);
    }catch(Throwable $error){
        if(db()->inTransaction())db()->rollBack();
        respond($error instanceof InvalidArgumentException?422:409,['ok'=>false,'error'=>$error instanceof InvalidArgumentException?$error->getMessage():'unable_to_provision']);
    }
}catch(Throwable $e){respond(503,['ok'=>false,'error'=>'server_unavailable']);}
