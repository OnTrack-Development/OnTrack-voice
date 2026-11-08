<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/SubscriptionPlans.php';
require_once __DIR__.'/../saas/console/_core/BillingInvoices.php';
if(is_dir('/home/appontrack')&&!getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('isolated_billing_fixture_only');
function invoice_check(bool $b,string $msg):void {
    if(!$b)throw new RuntimeException('INVOICE_V58_FAIL '.$msg);
}
seed_console();
$platform=['kind'=>'platform','account_id'=>1,'role'=>'owner'];
$reseller=['kind'=>'reseller','account_id'=>2,'role'=>'owner'];
$other=['kind'=>'reseller','account_id'=>3,'role'=>'owner'];
$customer=['kind'=>'customer','account_id'=>5,'role'=>'owner'];
$direct=['kind'=>'customer','account_id'=>4,'role'=>'owner'];
execute('UPDATE plans SET price_minor=? WHERE id=?',[129900,3]);
$invoice=billing_invoice_create_manual($reseller,5,3,2);
invoice_check((bool)preg_match('/^OTV-[0-9]{4}-[0-9]{7}$/D',$invoice['invoice_no']),
    'unique invoice numbering');
invoice_check($invoice['status']==='unpaid'&&(int)$invoice['amount_minor']===129900,
    'issue before payment never grants entitlement');
invoice_check($invoice['bill_to_name']!=='','customer snapshot');
invoice_check(billing_invoice_status($invoice)==='unpaid','new invoice not overdue');
invoice_check(count(billing_invoice_rows($customer,100))>=1,'owner sees own invoices');
invoice_check(count(billing_invoice_rows($reseller,100))>=1,'reseller sees own child');
invoice_check(count(billing_invoice_rows($platform,100))>=1,'platform sees all invoices');
invoice_check(count(billing_invoice_rows($other,100))===0,'other reseller cannot see');
invoice_check(count(billing_invoice_rows($direct,100))===0,'unrelated user cannot see');
$fail=false;
try{billing_invoice_get_visible($other,(int)$invoice['id']);}catch(InvalidArgumentException){$fail=true;}
invoice_check($fail,'invoice access must be scoped');
$fail=false;
try{billing_invoice_create_manual($other,5,3,3);}catch(InvalidArgumentException){$fail=true;}
invoice_check($fail,'reseller cannot create invoice for unrelated customer');
$fail=false;
try{billing_invoice_settle((int)$invoice['order_id'],'fake-ref-1234');}catch(InvalidArgumentException){$fail=true;}
invoice_check($fail,'cannot mark unpaid invoice as paid without verified order');
$cancelled=billing_invoice_cancel_manual($reseller,(int)$invoice['id'],2);
invoice_check($cancelled['status']==='cancelled','unpaid manual invoice cancellation');
invoice_check((string)one('SELECT status FROM orders WHERE id=?',[$invoice['order_id']])['status']==='cancelled',
    'order voiding with invoice');
$reissue=billing_invoice_reissue_manual($reseller,(int)$invoice['id'],2);
invoice_check($reissue['invoice_no']!==$invoice['invoice_no'] &&
    (int)$reissue['order_id']!==(int)$invoice['order_id'],'reissue is a new order');
$ref='BANK-REF-123456789';
execute("UPDATE orders SET status='approved',payment_reference=? WHERE id=?",
    [$ref,$reissue['order_id']]);
$settled=billing_invoice_settle((int)$reissue['order_id'],$ref,2);
invoice_check($settled['status']==='paid'&&$settled['payment_reference']===$ref,'verified settle');
$again=billing_invoice_settle((int)$reissue['order_id'],$ref,2);
invoice_check($again['id']===$settled['id'],'replay does not duplicate');
$count=(int)one("SELECT COUNT(*) n FROM billing_invoice_events WHERE invoice_id=? AND event='payment_received'",[$settled['id']])['n'];
invoice_check($count===1,'one unique payment event on idempotent replay');
$fail=false;
try{billing_invoice_cancel_manual($reseller,(int)$settled['id'],2);}
catch(InvalidArgumentException){$fail=true;}
invoice_check($fail,'paid invoice cannot be cancelled');
$fail=false;
try{billing_invoice_settle((int)$reissue['order_id'],'BANK-OTHER-REF');}
catch(InvalidArgumentException){$fail=true;}
invoice_check($fail,'mismatched payment ref rejected');
$html=billing_invoice_html($settled);
invoice_check(str_contains($html,'window.print()') &&
    str_contains($html,'OTV-') && str_contains($html,'BANK-REF-'),
    'printable HTML provides save PDF with payment proof');
$backfill=billing_invoice_backfill(null,100);
invoice_check($backfill>=1,'backfills historic orders');
$demo=one("SELECT i.* FROM billing_invoices i JOIN orders o ON o.id=i.order_id
    WHERE o.status='paid_demo' ORDER BY i.id LIMIT 1");
invoice_check($demo!==null && $demo['status']==='demo',
    'demo order never treated as paid');
$now=gmdate('Y-m-d H:i:s',time()-5*86400);
execute('UPDATE billing_invoices SET due_at=? WHERE id=?',[$now,$invoice['id']]);
invoice_check(billing_invoice_status(one('SELECT * FROM billing_invoices WHERE id=?',[$invoice['id']]))
    ==='cancelled','cancelled invoice never overdue');
invoice_check($backfill===billing_invoice_backfill(null,100)+$backfill||
    billing_invoice_backfill(null,100)===0,'backfill is idempotent');
echo "INVOICE_V58_TENANT_ISOLATION_PAYMENT_REFERENCE_HISTORY_PRINT_PASS\n";
