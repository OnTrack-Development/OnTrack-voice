<?php
declare(strict_types=1);

/**
 * Assign a plan in the owner-controlled DEMO workspace. External verified
 * payment activations use Checkout / ManualPayment instead.
 *
 * The transition is atomic: a failed INSERT must not leave a working account
 * without the previous subscription. Reapplying the same valid plan is a no-op
 * and cannot silently extend its paid period on each repeated click.
 */
function subscription_assign_demo(int $accountId,int $planId):array {
    $account=account($accountId);
    if(!$account||$account['status']!=='active'||
       !in_array($account['kind'],['reseller','customer'],true))
        throw new InvalidArgumentException('subscription_account_unavailable');
    $kind=$account['kind']==='reseller'?'reseller':'user';
    $plan=one("SELECT id FROM plans WHERE id=? AND kind=? AND status='active'",
        [$planId,$kind]);
    if(!$plan)throw new InvalidArgumentException('subscription_plan_unavailable');
    if(db()->inTransaction())
        throw new RuntimeException('subscription_nested_transaction_denied');
    db()->beginTransaction();
    try{
        $current=one("SELECT id,plan_id,ends_at FROM subscriptions
            WHERE account_id=? AND status='active' AND starts_at<=? AND ends_at>?
            ORDER BY id DESC LIMIT 1",[$accountId,now(),now()]);
        if($current && (int)$current['plan_id']===$planId){
            db()->commit();
            return ['subscription_id'=>(int)$current['id'],'changed'=>false];
        }
        execute("UPDATE subscriptions SET status='replaced'
            WHERE account_id=? AND status='active'",[$accountId]);
        // Changing the demo plan is not a renewal: preserve the paid/trial
        // period already granted instead of silently shortening or extending it.
        $endsAt=$current?(string)$current['ends_at']:
            gmdate('Y-m-d H:i:s',time()+30*86400);
        $new=insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at)
            VALUES (?,?,'active',?,?)",
            [$accountId,$planId,now(),$endsAt]);
        db()->commit();
        return ['subscription_id'=>$new,'changed'=>true];
    }catch(Throwable $error){
        if(db()->inTransaction())db()->rollBack();
        throw $error;
    }
}
