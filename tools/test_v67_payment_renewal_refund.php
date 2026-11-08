<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/Checkout.php';
require_once __DIR__.'/../saas/console/_core/KashierWebhook.php';
require_once __DIR__.'/../saas/console/_core/OrderClaim.php';
if(is_dir('/home/appontrack')&&!getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('isolated_fixture_required');
function pay_check(bool $v,string $why):void{
    if(!$v)throw new RuntimeException('V67_PAYMENT_REGRESSION '.$why);
}
function paid_order(int $account,int $plan,string $amount):array{
    $orderNo='ovc_'.bin2hex(random_bytes(16));
    $tx='TX_'.bin2hex(random_bytes(16));
    $orderId=insert("INSERT INTO orders(account_id,integration_id,external_order_id,plan_id,
      customer_name,customer_email,amount_minor,status,created_at)
      VALUES (?,NULL,?,?,?,?,?,'pending_payment',?)",[
      $account,$orderNo,$plan,'Regression','renew@example.invalid',
      kashier_minor_units($amount),now()]);
    $data=['amount'=>$amount,'currency'=>'EGP','merchantOrderId'=>$orderNo,
       'status'=>'SUCCESS','transactionId'=>$tx,'method'=>'card'];
    $data['signatureKeys']=array_keys($data);
    return ['id'=>$orderId,'data'=>$data,'tx'=>$tx,'ref'=>$orderNo];
}
function refund_body(array $order,string $event='refund',?string $amount=null):array {
    $d=$order['data'];
    $d['transactionId']='RF_'.bin2hex(random_bytes(16));
    $d['originalTransactionId']=$order['tx'];
    $d['eventType']=$event; // Signed event type, not just untrusted outer JSON metadata.
    if($amount!==null)$d['amount']=$amount;
    unset($d['signatureKeys']);
    $d['signatureKeys']=array_values(array_filter(array_keys($d),'is_string'));
    return ['event'=>$event,'data'=>$d];
}
seed_console();
$a=4;
$p=insert("INSERT INTO plans(kind,name,call_quota,site_limit,agent_limit,staff_limit,
  reseller_customers,price_minor,status,created_at)
  VALUES ('user','Regression paid renewal',550,3,3,3,0,2575,'active',?)",[now()]);
$before=one("SELECT * FROM subscriptions WHERE account_id=? AND status='active'
  ORDER BY ends_at DESC,id DESC LIMIT 1",[$a]);
pay_check($before!==null,'preexisting active term');
$oldEnd=gmdate('Y-m-d H:i:s',time()+17*86400);
execute('UPDATE subscriptions SET ends_at=? WHERE id=?',[$oldEnd,$before['id']]);
$first=paid_order($a,$p,'25.75');
pay_check(kashier_verified(['event'=>'pay','data'=>$first['data']],
    kashier_signature($first['data'],'v67-test-key'),'v67-test-key'),'pay signed');
pay_check(kashier_settle(['event'=>'pay','data'=>$first['data']],null)['status']==='active','first payment');
$one=one("SELECT * FROM subscriptions WHERE account_id=? AND status='active' ORDER BY id DESC LIMIT 1",[$a]);
pay_check((int)$one['plan_id']===$p,'new paid plan');
pay_check(abs(strtotime($one['ends_at'].' UTC')-(strtotime($oldEnd.' UTC')+30*86400))<=3,
    'unused 17 days carried into paid renewal');
pay_check(kashier_settle(['event'=>'pay','data'=>$first['data']],null)['duplicate'],
    'replayed pay cannot extend days');
$second=paid_order($a,$p,'25.75');
pay_check(kashier_settle(['event'=>'pay','data'=>$second['data']],null)['status']==='active','second payment');
$two=one("SELECT * FROM subscriptions WHERE account_id=? AND status='active' ORDER BY id DESC LIMIT 1",[$a]);
pay_check(abs(strtotime($two['ends_at'].' UTC')-(strtotime($one['ends_at'].' UTC')+30*86400))<=3,
    'multiple early renewals add paid time, do not reset');
$partial=refund_body($first,'partial_refund','1.00');
pay_check(kashier_verified($partial,kashier_signature($partial['data'],'test-secret'),'test-secret'),
    'signed partial refund');
pay_check(kashier_settle($partial,null)['status']==='review_required',
    'partial refund requires review; no arbitrary entitlement change');
pay_check((int)one("SELECT COUNT(*) n FROM subscriptions WHERE account_id=? AND id=?
    AND status='active'",[$a,$two['id']])['n']===1,
    'partial refund cannot revoke newer subscription');
$rev=refund_body($first);
pay_check(kashier_settle($rev,null)['status']==='review_required',
    'historical carried-forward full refund requires human reconciliation');
pay_check((string)one('SELECT status FROM orders WHERE id=?',[$first['id']])['status']==='active',
    'historical payment cannot be falsely marked fully reversed while time is carried forward');
pay_check((string)one('SELECT status FROM subscriptions WHERE id=?',[$two['id']])['status']==='active',
    'older refund cannot revoke newer live subscription');
pay_check(kashier_settle($rev,null)['duplicate'],'refund replay idempotent');
$bad=refund_body($second);
$bad['data']['originalTransactionId']='TX_'.bin2hex(random_bytes(16));
$bad['data']['signatureKeys']=array_keys($bad['data']);
$denied=false;
try{kashier_settle($bad,null);}catch(InvalidArgumentException){$denied=true;}
pay_check($denied,'forged original reference fails even if signed structure valid');
$secondRev=refund_body($second,'reversal');
// An attacker can relabel the unsigned outer event but cannot alter the
// signed eventType; that mismatch must not revoke a paid entitlement.
$unsafe=$secondRev;$unsafe['event']='refund';
pay_check(kashier_settle($unsafe,null)['status']==='review_required',
    'unsigned outer event mismatch requires manual review rather than auto-reversal');
pay_check((string)one('SELECT status FROM subscriptions WHERE id=?',[$two['id']])['status']==='active',
    'relabelled webhook cannot revoke paid time');
// A separate provider refund event for the same payment is now ambiguous
// because one event is already under review, and must never double-reverse.
pay_check(kashier_settle($secondRev,null)['status']==='review_required',
    'multiple refund deliveries for one payment cannot silently revoke twice');
pay_check((string)one('SELECT status FROM subscriptions WHERE id=?',[$two['id']])['status']==='active',
    'ambiguous signed reversal requires reconciliation before cancellation');
pay_check((int)one('SELECT COUNT(*) n FROM kashier_refund_events')['n']===4,
    'four signed refund/review receipts retained without duplicate cancellation');
echo "V67_PAID_TERM_CARRY_AND_TRANSACTION_LINKED_REVERSAL_PASS\n";
