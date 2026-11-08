<?php
declare(strict_types=1);
/**
 * Explicit owner/admin issuance; it is an administrative grant, NEVER proof
 * of Kashier payment or a settled invoice. Existing plan dates are preserved.
 * Resellers must still observe their parent quota for customer assignments.
 */
function subscription_admin_grant_allowed(array $actor,array $target,array $plan,bool $support=false,string $view='admin'):bool {
    return ($actor['kind']??'')==='platform'
        && in_array((string)($actor['role']??''),['owner','admin'],true)
        && !$support && $view==='admin'
        && ($target['status']??'')==='active'
        && in_array((string)($target['kind']??''),['reseller','customer'],true)
        && ($plan['status']??'')==='active'
        && ($plan['kind']??'')===(($target['kind']??'')==='reseller'?'reseller':'user');
}

/** Public self-activation is ONLY allowed for an advertised zero-price plan.
 * Paid subscriptions MUST continue through the verified checkout flow.
 */
function subscription_self_free_allowed(array $actor,array $plan,bool $support=false):bool {
    return !$support
        && in_array((string)($actor['role']??''),['owner','admin'],true)
        && in_array((string)($actor['kind']??''),['reseller','customer'],true)
        && ($actor['status']??'')==='active'
        && ($plan['status']??'')==='active'
        && (int)($plan['price_minor']??-1)===0
        && ($plan['kind']??'')===(($actor['kind']??'')==='reseller'?'reseller':'user');
}


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
