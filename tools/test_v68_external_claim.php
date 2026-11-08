<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/ExternalOrderClaim.php';
require_once __DIR__.'/../saas/console/_core/OrderClaim.php';
function claim68(bool $test,string $label):void {
    if(!$test)throw new RuntimeException('V68_EXTERNAL_CLAIM_FAIL '.$label);
}
seed_console();
$uid=(int)one("SELECT id FROM users WHERE email='pharmacy@delta.test'")['id'];
$foreign=(int)one("SELECT id FROM users WHERE email='user@nile.test'")['id'];
$secret='otint_'.bin2hex(random_bytes(32));
$integration=insert("INSERT INTO integrations
    (account_id,name,provider,token_hash,token_prefix,status,created_at,secret_enc)
    VALUES (2,'V68 Signed WHMCS','whmcs',?,?,'active',?,?)",[
    hash('sha256',$secret),substr($secret,0,12),now(),seal_secret($secret)]);
$plan=insert("INSERT INTO plans(kind,name,call_quota,site_limit,agent_limit,staff_limit,
    reseller_customers,price_minor,status,created_at)
    VALUES ('user','V68 External Paid',250,1,2,2,0,1575,'active',?)",[now()]);
$reference='WHMCS-V68-'.bin2hex(random_bytes(6));
$orderId=insert("INSERT INTO orders(account_id,integration_id,external_order_id,plan_id,
    customer_name,customer_email,amount_minor,status,created_at)
    VALUES (2,?,?,?,?,1575,'pending_payment',?)",[
    $integration,$reference,$plan,'Wrong untrusted email','nobody@example.test',now()]);
$order=one('SELECT * FROM orders WHERE id=?',[$orderId]);
$issuer=one('SELECT * FROM integrations WHERE id=?',[$integration]);
$subject='whmcs-client-'.bin2hex(random_bytes(6));
external_order_subject_bind($order,$issuer,$subject);
external_order_subject_bind($order,$issuer,$subject);
$denied=false;
try{external_order_subject_bind($order,$issuer,'attacker-other-subject');}
catch(InvalidArgumentException){$denied=true;}
claim68($denied,'immutable signed order subject');
$transaction='TX_'.bin2hex(random_bytes(16));
$out=record_verified_external_payment($order,$transaction);
claim68($out['status']==='paid_pending_claim','verified external payment remains unclaimed');
$before=(int)one("SELECT COUNT(*) n FROM subscriptions WHERE account_id=5 AND status='active'")['n'];
$denied=false;
try{external_order_claim_paid($uid,5,$orderId);}
catch(InvalidArgumentException){$denied=true;}
claim68($denied && (int)one("SELECT COUNT(*) n FROM subscriptions
    WHERE account_id=5 AND status='active'")['n']===$before,
    'matching email not enough for identity proof');
$linked=insert("INSERT INTO external_identity_links
    (integration_id,external_subject,user_id,created_at) VALUES (?,?,?,?)",
    [$integration,$subject,$uid,now()]);
claim68(count(external_order_claim_candidates($uid,5))===1,
    'only SSO-linked correct-account owner sees paid order');
claim68(count(external_order_claim_candidates($foreign,7))===0,
    'foreign reseller never sees paid order even with same email');
$denied=false;
try{external_order_claim_paid($uid,6,$orderId);}
catch(InvalidArgumentException){$denied=true;}
claim68($denied,'other child tenant cannot claim without membership');
$beforeTerm=one("SELECT ends_at FROM subscriptions WHERE account_id=5
  AND status='active' ORDER BY ends_at DESC LIMIT 1",[]);
$done=external_order_claim_paid($uid,5,$orderId);
claim68($done['status']==='active' && $done['account_id']===5,
    'verified signed SSO subject activates correct customer');
$current=one("SELECT * FROM subscriptions WHERE account_id=5
    AND status='active' ORDER BY id DESC LIMIT 1");
claim68((int)$current['id']===$done['subscription_id']&&
    (int)$current['plan_id']===$plan,'exact paid plan and new subscription');
claim68(strtotime($current['ends_at'].' UTC')>=
    strtotime($beforeTerm['ends_at'].' UTC')+30*86400-2,
    'previous paid days preserved on external claim');
claim68((int)one("SELECT account_id FROM orders WHERE id=?",[$orderId])['account_id']===5,
    'order reassigned only after verified identity');
$invoice=one("SELECT account_id,issuer_account_id FROM billing_invoices WHERE order_id=?",[$orderId]);
claim68($invoice && (int)$invoice['account_id']===5 && (int)$invoice['issuer_account_id']===2,
    'paid invoice transferred to customer but issued by correct reseller');
$denied=false;try{external_order_claim_paid($uid,5,$orderId);}
catch(InvalidArgumentException){$denied=true;}
claim68($denied,'replayed customer claim cannot create second active subscription');
claim68(count(external_order_claim_candidates($uid,5))===0,
    'claimed order disappears from unclaimed list');
echo "V68_EXTERNAL_SIGNED_SUBJECT_PAID_ORDER_CLAIM_AND_INVOICE_PASS\n";
