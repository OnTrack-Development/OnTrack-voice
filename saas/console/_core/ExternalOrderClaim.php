<?php
declare(strict_types=1);
require_once __DIR__.'/SubscriptionPlans.php';
require_once __DIR__.'/ExternalSso.php';

/**
 * External gateway proof AND a signed external subject AND a verified local
 * membership are all required. An email address is NEVER an identity proof.
 */
function external_order_claim_init():void {
    $engine=driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS external_order_subjects(
        order_id BIGINT PRIMARY KEY,
        integration_id BIGINT NOT NULL,
        external_subject VARCHAR(128) NOT NULL,
        created_at VARCHAR(19) NOT NULL
    )".$engine);
}
function external_order_subject_assert(string $subject):string{
    if(!preg_match('/^[A-Za-z0-9._:-]{1,128}$/D',$subject))
        throw new InvalidArgumentException('external_order_subject_invalid');
    return $subject;
}
function external_order_subject_bind(array $order,array $integration,string $subject):void{
    external_order_claim_init();
    $subject=external_order_subject_assert($subject);
    if((int)$order['integration_id']!==(int)$integration['id']||
       (int)$order['account_id']!==(int)$integration['account_id']||
       !in_array($order['status'],['pending_payment','paid_pending_claim'],true))
        throw new InvalidArgumentException('external_order_subject_scope_denied');
    $existing=one('SELECT external_subject FROM external_order_subjects WHERE order_id=?',
        [(int)$order['id']]);
    if($existing){
        if(!hash_equals((string)$existing['external_subject'],$subject))
            throw new InvalidArgumentException('external_order_subject_immutable');
        return;
    }
    insert("INSERT INTO external_order_subjects(order_id,integration_id,external_subject,created_at)
        VALUES (?,?,?,?)",[(int)$order['id'],(int)$integration['id'],$subject,now()]);
}
/** Paid unclaimed orders visible only to their linked, authenticated subject. */
function external_order_claim_candidates(int $userId,int $accountId):array{
    external_order_claim_init();
    if($userId<1||$accountId<1)return [];
    $target=account($accountId);
    if(!$target||$target['kind']!=='customer'||$target['status']!=='active')return [];
    return all("SELECT o.id,o.external_order_id,o.amount_minor,o.plan_id,
      o.payment_reference,o.created_at,i.name integration_name
      FROM orders o
      JOIN external_order_subjects es ON es.order_id=o.id AND es.integration_id=o.integration_id
      JOIN integrations i ON i.id=o.integration_id AND i.status='active'
      JOIN external_identity_links link ON link.integration_id=i.id
        AND link.external_subject=es.external_subject AND link.user_id=?
      JOIN memberships m ON m.user_id=link.user_id AND m.account_id=?
        AND m.role IN ('owner','admin')
      JOIN users u ON u.id=m.user_id AND u.status='active'
      JOIN accounts parent ON parent.id=i.account_id AND parent.status='active'
      WHERE o.status='paid_pending_claim'
        AND parent.id=? AND parent.kind IN ('reseller','platform')
      ORDER BY o.id DESC LIMIT 40",[$userId,$accountId,(int)$target['parent_id']]);
}
/**
 * Cannot be called from payment callbacks. The authenticated customer owner
 * must explicitly claim a pre-paid order owned by the SAME linked SSO subject.
 */
function external_order_claim_paid(int $userId,int $targetId,int $orderId):array{
    external_order_claim_init();
    if($userId<1||$targetId<1||$orderId<1||active_support())
        throw new InvalidArgumentException('external_order_claim_not_authorized');
    $target=account($targetId);
    if(!$target||$target['kind']!=='customer'||$target['status']!=='active')
        throw new InvalidArgumentException('external_order_claim_account_inactive');
    $owner=one("SELECT m.role FROM memberships m JOIN users u ON u.id=m.user_id
        WHERE m.account_id=? AND m.user_id=? AND u.status='active'
            AND m.role IN ('owner','admin')",[$targetId,$userId]);
    if(!$owner)throw new InvalidArgumentException('external_order_claim_membership_required');
    db()->beginTransaction();
    try{
        $sql='SELECT * FROM orders WHERE id=?';
        if(driver()==='mysql')$sql.=' FOR UPDATE';
        $order=one($sql,[$orderId]);
        if(!$order||$order['status']!=='paid_pending_claim'||
           empty($order['payment_reference'])||empty($order['integration_id']))
            throw new InvalidArgumentException('external_order_not_claimable');
        $integration=one("SELECT * FROM integrations WHERE id=? AND status='active'",
            [(int)$order['integration_id']]);
        $issuer=$integration?account((int)$integration['account_id']):null;
        if(!$integration||!in_array((string)$integration['provider'],['whmcs','laravel'],true)||
           !$issuer||$issuer['status']!=='active'||
           (int)$target['parent_id']!==(int)$issuer['id']||
           !in_array((string)$issuer['kind'],['platform','reseller'],true)||
           (int)$order['account_id']!==(int)$issuer['id'])
            throw new InvalidArgumentException('external_order_claim_issuer_mismatch');
        $subject=one("SELECT es.external_subject FROM external_order_subjects es
            JOIN external_identity_links l ON l.integration_id=es.integration_id
             AND l.external_subject=es.external_subject AND l.user_id=?
            WHERE es.order_id=? AND es.integration_id=?",
            [$userId,$orderId,(int)$integration['id']]);
        if(!$subject)throw new InvalidArgumentException('external_order_sso_identity_proof_missing');
        $plan=one("SELECT * FROM plans WHERE id=? AND kind='user' AND status='active'",
            [(int)$order['plan_id']]);
        if(!$plan||(int)$plan['price_minor']!==(int)$order['amount_minor']||
           !plan_catalog_allowed($plan,$target))
            throw new InvalidArgumentException('external_order_claim_plan_unavailable');
        if($issuer['kind']==='reseller'){
            $other=(int)one("SELECT COALESCE(SUM(p.call_quota),0) n FROM subscriptions s
                JOIN plans p ON p.id=s.plan_id JOIN accounts a ON a.id=s.account_id
                WHERE a.parent_id=? AND a.id!=? AND s.status='active'
                  AND s.starts_at<=? AND s.ends_at>?",[
                $issuer['id'],$targetId,now(),now()])['n'];
            if($other+(int)$plan['call_quota']>site_limit((int)$issuer['id'],'call_quota'))
                throw new InvalidArgumentException('external_order_claim_reseller_quota');
        }
        $current=one("SELECT ends_at FROM subscriptions WHERE account_id=? AND status='active'
           AND starts_at<=? AND ends_at>? ORDER BY ends_at DESC,id DESC LIMIT 1",
           [$targetId,now(),now()]);
        $baseline=time();
        if($current){
            $ends=strtotime((string)$current['ends_at'].' UTC');
            if($ends!==false)$baseline=max($baseline,$ends);
        }
        if(execute("UPDATE orders SET account_id=?,status='active'
            WHERE id=? AND integration_id=? AND account_id=? AND status='paid_pending_claim'",
            [$targetId,$orderId,(int)$integration['id'],(int)$issuer['id']])!==1)
            throw new RuntimeException('external_order_claim_race');
        execute("UPDATE subscriptions SET status='replaced'
            WHERE account_id=? AND status='active'",[$targetId]);
        $newId=insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at)
            VALUES (?,?,'active',?,?)",[$targetId,$plan['id'],now(),
            gmdate('Y-m-d H:i:s',$baseline+30*86400)]);
        // Existing invoice may have been provisionally attributed to the
        // integration owner before the customer's SSO claim.
        require_once __DIR__.'/BillingInvoices.php';
        billing_invoice_init();
        execute("UPDATE billing_invoices SET account_id=?,issuer_account_id=?
           WHERE order_id=? AND account_id=?",
           [$targetId,(int)$issuer['id'],$orderId,(int)$issuer['id']]);
        insert("INSERT INTO outbox(account_id,integration_id,kind,payload,status,created_at)
            VALUES (?,?,?,?, 'pending',?)",[
            $targetId,(int)$integration['id'],'subscription.activated',
            json_encode(['order_id'=>$orderId,'subscription_id'=>$newId,
                'plan_id'=>(int)$plan['id'],'source'=>'external_sso_claim'],
                JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),now()]);
        db()->commit();
        audit('external_order.paid_claimed',$targetId,[
            'order_id'=>$orderId,'subscription_id'=>$newId,'integration_id'=>(int)$integration['id']]);
        return ['status'=>'active','account_id'=>$targetId,
            'subscription_id'=>$newId,'order_id'=>$orderId,'source'=>'verified_sso_subject'];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
