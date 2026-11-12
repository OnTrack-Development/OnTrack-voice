<?php
declare(strict_types=1);
/**
 * Tenant-safe invoice documents and immutable payment references.
 * Financial state is never inferred from the front-end success redirect.
 * Existing order/payment settlement flows remain authoritative.
 */
function billing_invoice_init():void {
    static $ready=false;
    if($ready)return;
    $engine=driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $primary=driver()==='mysql'?'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $int=driver()==='mysql'?'BIGINT':'INTEGER';
    db()->exec("CREATE TABLE IF NOT EXISTS billing_invoices (
        id $primary,
        invoice_no VARCHAR(42) UNIQUE,
        account_id $int NOT NULL,
        issuer_account_id $int NOT NULL,
        order_id $int NOT NULL UNIQUE,
        plan_id $int NOT NULL,
        bill_to_name VARCHAR(190) NOT NULL,
        bill_to_email VARCHAR(190) NOT NULL,
        issuer_name VARCHAR(190) NOT NULL,
        plan_name VARCHAR(190) NOT NULL,
        amount_minor BIGINT NOT NULL,
        currency CHAR(3) NOT NULL DEFAULT 'EGP',
        status VARCHAR(24) NOT NULL,
        issued_at VARCHAR(19) NOT NULL,
        due_at VARCHAR(19) NOT NULL,
        paid_at VARCHAR(19) NULL,
        payment_reference VARCHAR(190) NULL,
        cancelled_at VARCHAR(19) NULL
    )".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS billing_invoice_events (
        id $primary,
        invoice_id $int NOT NULL,
        actor_user_id $int NULL,
        event VARCHAR(32) NOT NULL,
        reference VARCHAR(190) NULL,
        created_at VARCHAR(19) NOT NULL
    )".$engine);
    $ready=true;
}
function billing_invoice_event(int $invoice,string $event,?int $actor=null,?string $reference=null):void {
    insert('INSERT INTO billing_invoice_events(invoice_id,actor_user_id,event,reference,created_at)
        VALUES (?,?,?,?,?)',[$invoice,$actor,$event,$reference,now()]);
}
function billing_invoice_issuer(array $target):array {
    $parent=(int)($target['parent_id']??0);
    if($parent>0){
        $reseller=account($parent);
        if($reseller&&($reseller['kind']??'')==='reseller')
            return ['id'=>(int)$reseller['id'],'name'=>(string)($reseller['brand_name']?:$reseller['name'])];
    }
    $platform=one("SELECT id,name,brand_name FROM accounts WHERE kind='platform' ORDER BY id LIMIT 1");
    return ['id'=>(int)($platform['id']??0),
        'name'=>(string)($platform?($platform['brand_name']?:$platform['name']):'OnTrack Voice')];
}
function billing_invoice_by_order(int $orderId):?array {
    billing_invoice_init();
    return one('SELECT * FROM billing_invoices WHERE order_id=?',[$orderId]);
}
/** Called within an existing settlement transaction or an explicit issue action. */
function billing_invoice_for_order(array $order):array {
    billing_invoice_init();
    $orderId=(int)$order['id'];
    $existing=billing_invoice_by_order($orderId);
    if($existing)return $existing;
    $account=account((int)$order['account_id']);
    $plan=one('SELECT name FROM plans WHERE id=?',[$order['plan_id']]);
    if(!$account||!$plan)throw new RuntimeException('invoice_account_or_plan_absent');
    $issuer=billing_invoice_issuer($account);
    if($issuer['id']<1)throw new RuntimeException('invoice_issuer_missing');
    $issued=(string)($order['created_at']?:now());
    $timestamp=strtotime($issued);
    $due=gmdate('Y-m-d H:i:s',($timestamp!==false?$timestamp:time())+7*86400);
    $original=(string)$order['status'];
    $status=in_array($original,['active','approved','paid_pending_claim'],true)&&
        (string)($order['payment_reference']??'')!==''?'paid':
        (in_array($original,['cancelled','void'],true)?'cancelled':
            ($original==='paid_demo'?'demo':'unpaid'));
    $id=insert("INSERT INTO billing_invoices
        (invoice_no,account_id,issuer_account_id,order_id,plan_id,bill_to_name,
         bill_to_email,issuer_name,plan_name,amount_minor,currency,status,issued_at,due_at,
         paid_at,payment_reference,cancelled_at)
        VALUES (NULL,?,?,?,?,?,?,?,?,?,'EGP',?,?,?,?,?,?)",[
            (int)$account['id'],$issuer['id'],$orderId,(int)$order['plan_id'],
            (string)($order['customer_name']?:$account['name']),
            (string)($order['customer_email']?:''),
            $issuer['name'],(string)$plan['name'],(int)$order['amount_minor'],
            $status,$issued,$due,$status==='paid'?now():null,
            $status==='paid'?(string)$order['payment_reference']:null,
            $status==='cancelled'?now():null
        ]);
    execute('UPDATE billing_invoices SET invoice_no=? WHERE id=?',[
        'OTV-'.gmdate('Y',($timestamp!==false?$timestamp:time())).'-'.str_pad((string)$id,7,'0',STR_PAD_LEFT),$id]);
    billing_invoice_event($id,$status==='paid'?'legacy_payment_recorded':'issued');
    return one('SELECT * FROM billing_invoices WHERE id=?',[$id]);
}
/** Only call after original gateway webhook or manually verified payment settled the order. */
function billing_invoice_settle(int $orderId,string $reference,?int $actor=null):array {
    billing_invoice_init();
    $order=one('SELECT * FROM orders WHERE id=?',[$orderId]);
    if(!$order||!in_array($order['status'],['active','approved','paid_pending_claim'],true)||
        $reference===''||!hash_equals((string)$order['payment_reference'],$reference))
        throw new InvalidArgumentException('payment_not_settled_by_authoritative_order');
    $invoice=billing_invoice_for_order($order);
    if($invoice['status']==='paid'){
        if(!hash_equals((string)($invoice['payment_reference']??''),$reference))
            throw new InvalidArgumentException('invoice_payment_reference_conflict');
        return $invoice;
    }
    if($invoice['status']==='cancelled'||$invoice['status']==='demo')
        throw new InvalidArgumentException('invoice_not_settleable');
    if(execute("UPDATE billing_invoices SET status='paid',paid_at=?,payment_reference=?
        WHERE id=? AND status='unpaid'",[now(),$reference,$invoice['id']])!==1)
        throw new RuntimeException('invoice_settlement_conflict');
    billing_invoice_event((int)$invoice['id'],'payment_received',$actor,$reference);
    return one('SELECT * FROM billing_invoices WHERE id=?',[$invoice['id']]);
}
function billing_invoice_status(array $invoice):string {
    if($invoice['status']==='unpaid' && $invoice['due_at']<now())return 'overdue';
    return (string)$invoice['status'];
}
function billing_invoice_visible(array $actor,array $invoice):bool {
    if(($actor['kind']??'')==='platform')return true;
    $accountId=(int)($actor['account_id']??0);
    if($accountId<1)return false;
    if((int)$invoice['account_id']===$accountId)return true;
    return ($actor['kind']??'')==='reseller'
        &&(int)$invoice['issuer_account_id']===$accountId
        &&is_reseller_child($accountId,(int)$invoice['account_id']);
}
function billing_invoice_get_visible(array $actor,int $invoiceId):array {
    billing_invoice_init();
    $invoice=$invoiceId>0?one('SELECT * FROM billing_invoices WHERE id=?',[$invoiceId]):null;
    if(!$invoice||!billing_invoice_visible($actor,$invoice))
        throw new InvalidArgumentException('invoice_not_found_or_forbidden');
    return $invoice;
}
function billing_invoice_rows(array $actor,int $limit=120,?int $targetId=null):array {
    billing_invoice_init();
    $id=(int)$actor['account_id'];
    if($targetId!==null){
        if($targetId!==$id && !(($actor['kind']??'')==='platform' ||
            (($actor['kind']??'')==='reseller'&&is_reseller_child($id,$targetId))))
            throw new InvalidArgumentException('invoice_customer_scope_denied');
        $rows=all('SELECT * FROM billing_invoices WHERE account_id=? ORDER BY id DESC LIMIT '.max(1,min(500,$limit)),[$targetId]);
    }elseif($actor['kind']==='platform'){
        $rows=all('SELECT * FROM billing_invoices ORDER BY id DESC LIMIT '.max(1,min(500,$limit)));
    }elseif($actor['kind']==='reseller'){
        $rows=all('SELECT i.* FROM billing_invoices i JOIN accounts a ON a.id=i.account_id
            WHERE i.account_id=? OR (a.parent_id=? AND i.issuer_account_id=?)
            ORDER BY i.id DESC LIMIT '.max(1,min(500,$limit)),[$id,$id,$id]);
    }else{
        $rows=all('SELECT * FROM billing_invoices WHERE account_id=? ORDER BY id DESC LIMIT '.max(1,min(500,$limit)),[$id]);
    }
    return $rows;
}
/** Issue a pending manually reviewed order; never activate an entitlement. */
function billing_invoice_create_manual(array $actor,int $customerId,int $planId,int $userId):array {
    if(!in_array($actor['kind'],['platform','reseller'],true)||!in_array($actor['role'],['owner','admin','finance'],true))
        throw new InvalidArgumentException('invoice_create_denied');
    $target=account($customerId);
    if(!$target||$target['status']!=='active'||!in_array($target['kind'],['reseller','customer'],true))
        throw new InvalidArgumentException('invoice_customer_invalid');
    if($actor['kind']==='reseller'&&!is_reseller_child((int)$actor['account_id'],$customerId))
        throw new InvalidArgumentException('invoice_reseller_scope_denied');
    $plan=one("SELECT * FROM plans WHERE id=? AND status='active'",[$planId]);
    require_once __DIR__.'/SubscriptionPlans.php';
    if(!$plan||!plan_catalog_allowed($plan,$target)||$plan['price_minor']<=0)
        throw new InvalidArgumentException('invoice_paid_plan_not_available');
    $owner=one("SELECT u.email FROM memberships m JOIN users u ON u.id=m.user_id
        WHERE m.account_id=? AND m.role='owner' ORDER BY m.id LIMIT 1",[$customerId]);
    $email=(string)($owner['email']??'');
    if($email===''||!filter_var($email,FILTER_VALIDATE_EMAIL))
        throw new InvalidArgumentException('invoice_customer_email_missing');
    if(db()->inTransaction())throw new RuntimeException('nested_manual_invoice_transaction');
    db()->beginTransaction();
    try{
        $ref='otinv_'.bin2hex(random_bytes(16));
        $orderId=insert("INSERT INTO orders
            (account_id,integration_id,external_order_id,plan_id,customer_name,
             customer_email,amount_minor,status,created_at)
            VALUES (?,NULL,?,?,?,?,?,'pending_manual_review',?)",
            [$customerId,$ref,$planId,$target['name'],$email,(int)$plan['price_minor'],now()]);
        $order=one('SELECT * FROM orders WHERE id=?',[$orderId]);
        $invoice=billing_invoice_for_order($order);
        billing_invoice_event((int)$invoice['id'],'manual_approval_requested',$userId);
        db()->commit();return $invoice;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
/** Cancelling an unpaid manual invoice must not pretend that a bank debit was voided. */
function billing_invoice_cancel_manual(array $actor,int $invoiceId,int $actorUser):array {
    $invoice=billing_invoice_get_visible($actor,$invoiceId);
    if(!in_array($actor['kind'],['platform','reseller'],true)||
       !in_array($actor['role'],['owner','admin','finance'],true)||
       $invoice['status']!=='unpaid')throw new InvalidArgumentException('invoice_cancellation_denied');
    $order=one('SELECT id,status FROM orders WHERE id=?',[$invoice['order_id']]);
    if(!$order||$order['status']!=='pending_manual_review')
        throw new InvalidArgumentException('gateway_invoice_cannot_be_cancelled_here');
    db()->beginTransaction();
    try{
        if(execute("UPDATE orders SET status='cancelled' WHERE id=? AND status='pending_manual_review'",
            [$order['id']])!==1)throw new RuntimeException('invoice_order_state_changed');
        if(execute("UPDATE billing_invoices SET status='cancelled',cancelled_at=? WHERE id=? AND status='unpaid'",
            [now(),$invoiceId])!==1)throw new RuntimeException('invoice_state_changed');
        billing_invoice_event($invoiceId,'cancelled',$actorUser);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    return one('SELECT * FROM billing_invoices WHERE id=?',[$invoiceId]);
}
function billing_invoice_reissue_manual(array $actor,int $invoiceId,int $userId):array {
    $invoice=billing_invoice_get_visible($actor,$invoiceId);
    if($invoice['status']!=='cancelled')throw new InvalidArgumentException('only_cancelled_invoice_can_be_reissued');
    $order=one('SELECT status FROM orders WHERE id=?',[$invoice['order_id']]);
    if(!$order||$order['status']!=='cancelled')throw new InvalidArgumentException('original_order_not_cancelled');
    return billing_invoice_create_manual($actor,(int)$invoice['account_id'],(int)$invoice['plan_id'],$userId);
}
/** Backfill legacy orders without fabricating a payment status. */
function billing_invoice_backfill(?int $accountId=null,int $limit=100):int {
    billing_invoice_init();
    $limit=max(1,min(500,$limit));
    $sql='SELECT o.* FROM orders o LEFT JOIN billing_invoices i ON i.order_id=o.id
       WHERE i.id IS NULL'.($accountId===null?'':' AND o.account_id=?').
       ' ORDER BY o.id ASC LIMIT '.$limit;
    $orders=all($sql,$accountId===null?[]:[$accountId]);
    $count=0;
    foreach($orders as $o){
        $target=account((int)$o['account_id']);
        $plan=one('SELECT id FROM plans WHERE id=?',[$o['plan_id']]);
        if(!$target||!$plan)continue;
        billing_invoice_for_order($o);$count++;
    }
    return $count;
}
function billing_invoice_money(int $amount):string {
    return number_format($amount/100,2).' ج.م';
}
function billing_invoice_html(array $invoice):string {
    $status=['paid'=>'مدفوعة','unpaid'=>'غير مدفوعة','overdue'=>'متأخرة',
        'cancelled'=>'ملغاة','demo'=>'تجريبية — ليست إثبات دفع'];
    $state=billing_invoice_status($invoice);
    $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $h='<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>فاتورة '.$esc($invoice['invoice_no']).'</title>'.
      '<style>body{font-family:system-ui,Arial,sans-serif;color:#181b22;background:#f5f6f8;margin:0;padding:28px;line-height:1.9}main{max-width:850px;margin:auto;background:white;padding:36px;border:1px solid #e1e4eb;border-radius:15px}header{display:flex;justify-content:space-between;gap:25px;align-items:flex-start}h1{margin:0;font-size:26px}h2{font-size:16px}small{color:#647084}table{border-collapse:collapse;width:100%;margin:24px 0}td,th{padding:12px;border-bottom:1px solid #ddd;text-align:right}th{background:#f3f4f7}.total{font-size:22px;font-weight:800}.actions{margin:12px auto;max-width:850px;display:flex;gap:8px}button{padding:10px 20px;cursor:pointer}.muted{color:#566078}@media print{body{background:#fff;padding:0}main{border:0;padding:0}.actions{display:none} @page{size:A4;margin:15mm}}</style></head><body>'.
      '<div class="actions"><button onclick="window.print()">طباعة / حفظ PDF</button></div><main><header><div><h1>فاتورة '.$esc($invoice['invoice_no']).'</h1><small>'.$esc($status[$state]??$state).'</small></div><div><strong>'.$esc($invoice['issuer_name']).'</strong><br><small>فاتورة خدمات OnTrack Voice</small></div></header>'.
      '<h2>بيانات الفاتورة</h2><p>العميل: <strong>'.$esc($invoice['bill_to_name']).'</strong><br>البريد: <span dir="ltr">'.$esc($invoice['bill_to_email']).'</span><br>تاريخ الإصدار: '.$esc($invoice['issued_at']).'<br>الاستحقاق: '.$esc($invoice['due_at']).'</p>'.
      '<table><thead><tr><th>البند</th><th>المدة</th><th>القيمة</th></tr></thead><tbody><tr><td>'.$esc($invoice['plan_name']).'</td><td>30 يومًا</td><td>'.$esc(billing_invoice_money((int)$invoice['amount_minor'])).'</td></tr></tbody></table>'.
      '<p class="total">الإجمالي: '.$esc(billing_invoice_money((int)$invoice['amount_minor'])).'</p>'.
      ($invoice['payment_reference']?'<p>مرجع السداد: <code dir="ltr">'.$esc($invoice['payment_reference']).'</code></p>':'<p class="muted">هذه الفاتورة ليست إثبات سداد. يتحول وضعها إلى مدفوعة بعد تحقق المعاملة.</p>').
      '<p class="muted">العملة: الجنيه المصري (EGP). لا تتضمن الفاتورة ضريبة منفصلة ما لم يتم إعدادها وتوثيقها ضمن النظام.</p></main></body></html>';
    return $h;
}
