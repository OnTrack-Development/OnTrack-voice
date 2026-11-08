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
    $full=one('SELECT * FROM plans WHERE id=?',[$planId]);
    if(!$full||!plan_catalog_allowed($full,$account))
        throw new InvalidArgumentException('subscription_plan_outside_reseller_catalog');
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


/**
 * Tenant-owned plan catalogue. NULL owner = global platform plan.
 * This separate mapping avoids invasive ALTER TABLE on live payment data.
 * All catalogue writes are initiated from authenticated owner/admin actions.
 */
function plan_catalog_init():void {
    static $ready=false;
    if($ready)return;
    $engine=driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    db()->exec("CREATE TABLE IF NOT EXISTS reseller_plan_owners(
        plan_id BIGINT PRIMARY KEY,
        reseller_account_id BIGINT NOT NULL,
        source_plan_id BIGINT NULL,
        created_at VARCHAR(19) NOT NULL
    )".$engine);
    $ready=true;
}
function plan_catalog_owner(int $planId):int {
    plan_catalog_init();
    $row=one('SELECT reseller_account_id FROM reseller_plan_owners WHERE plan_id=?',[$planId]);
    return (int)($row['reseller_account_id']??0);
}
function plan_catalog_allowed(array $plan,array $account):bool {
    if(($plan['status']??'')!=='active')return false;
    $kind=(string)($account['kind']??'');
    if(($plan['kind']??'')!==($kind==='reseller'?'reseller':'user'))return false;
    if(!in_array($kind,['customer','reseller'],true))return false;
    $owner=plan_catalog_owner((int)$plan['id']);
    return $owner===0 || ($kind==='customer' && $owner===(int)($account['parent_id']??0));
}
function plan_catalog_available(array $account,bool $includeArchived=false):array {
    plan_catalog_init();
    $kind=($account['kind']??'')==='reseller'?'reseller':'user';
    $sql="SELECT p.* FROM plans p LEFT JOIN reseller_plan_owners o ON o.plan_id=p.id
        WHERE p.kind=? AND (o.plan_id IS NULL OR o.reseller_account_id=?)";
    if(!$includeArchived)$sql.=" AND p.status='active'";
    $sql.=' ORDER BY p.price_minor,p.id';
    return all($sql,[$kind,(int)($account['parent_id']??0)]);
}
function plan_catalog_reseller_owned(int $reseller):array {
    plan_catalog_init();
    return all("SELECT p.*,o.source_plan_id FROM plans p
        JOIN reseller_plan_owners o ON o.plan_id=p.id
        WHERE o.reseller_account_id=? ORDER BY p.id DESC",[$reseller]);
}
function plan_catalog_validate_fields(array $fields):array {
    $name=trim((string)($fields['name']??''));
    if($name===''||mb_strlen($name)>120)throw new InvalidArgumentException('اسم الباقة مطلوب (حتى 120 حرف)');
    $limits=['call_quota'=>100000000,'site_limit'=>100000,'agent_limit'=>100000,
       'staff_limit'=>100000,'reseller_customers'=>100000,
       'price_minor'=>1000000000,'overage_minor'=>10000000,
       'google_key_limit'=>1000,'assistant_key_limit'=>1000];
    $out=['name'=>$name];
    foreach($limits as $field=>$max){
        $value=$fields[$field]??0;
        if(!is_int($value)||$value<0||$value>$max)
            throw new InvalidArgumentException('قيمة الباقة غير صالحة: '.$field);
        $out[$field]=$value;
    }
    return $out;
}
/** Reseller may edit only its own plans; global plans are immutable to it. */
function plan_catalog_save_reseller(int $reseller,array $values,int $planId=0,int $sourcePlanId=0):int {
    $account=account($reseller);
    if(!$account||$account['kind']!=='reseller'||$account['status']!=='active')
        throw new InvalidArgumentException('reseller_account_unavailable');
    plan_catalog_init();
    $v=plan_catalog_validate_fields($values);
    if($planId!==0 && plan_catalog_owner($planId)!==$reseller)
        throw new InvalidArgumentException('لا يمكنك تعديل باقة عامة أو باقة ريسيلر آخر');
    if($sourcePlanId!==0){
        $source=one('SELECT id FROM plans WHERE id=? AND kind=? AND status=?',
            [$sourcePlanId,'user','active']);
        if(!$source || plan_catalog_owner($sourcePlanId)!==0)
            throw new InvalidArgumentException('القالب العام غير متاح');
    }
    if(db()->inTransaction())throw new RuntimeException('nested_plan_catalog_transaction');
    db()->beginTransaction();
    try{
        if($planId===0){
            $planId=insert("INSERT INTO plans
                (kind,name,call_quota,site_limit,agent_limit,staff_limit,reseller_customers,
                 price_minor,overage_minor,google_key_limit,assistant_key_limit,status,created_at)
                 VALUES ('user',?,?,?,?,?,?,?,?,?,?,'active',?)",[
                $v['name'],$v['call_quota'],$v['site_limit'],$v['agent_limit'],
                $v['staff_limit'],$v['reseller_customers'],$v['price_minor'],$v['overage_minor'],
                $v['google_key_limit'],$v['assistant_key_limit'],now()]);
            insert('INSERT INTO reseller_plan_owners(plan_id,reseller_account_id,source_plan_id,created_at) VALUES(?,?,?,?)',
                [$planId,$reseller,$sourcePlanId?:null,now()]);
        }else{
            $row=one("SELECT * FROM plans WHERE id=? AND kind='user' AND status='active'",[$planId]);
            if(!$row)throw new InvalidArgumentException('الباقة غير نشطة أو غير موجودة');
            $changed=false;
            foreach($v as $column=>$value){
                if((string)$row[$column] !== (string)$value){$changed=true;break;}
            }
            if($changed){
                $pending=(int)(one("SELECT COUNT(*) n FROM orders WHERE plan_id=?
                    AND status IN ('pending_payment','pending_manual_review')",[$planId])['n']??0);
                if($pending>0)throw new InvalidArgumentException(
                    'هناك طلب دفع معلّق للباقة؛ احسم الطلب قبل تعديل السعر أو الاستحقاقات');
                $uses=(int)(one('SELECT COUNT(*) n FROM subscriptions WHERE plan_id=?',[$planId])['n']??0)
                    +(int)(one('SELECT COUNT(*) n FROM orders WHERE plan_id=?',[$planId])['n']??0);
                if($uses>0){
                    // Keep every existing subscriber and paid receipt tied to its
                    // original immutable plan version.
                    execute("UPDATE plans SET status='archived' WHERE id=?",[$planId]);
                    $source=one('SELECT source_plan_id FROM reseller_plan_owners WHERE plan_id=?',[$planId]);
                    $newId=insert("INSERT INTO plans
                       (kind,name,call_quota,site_limit,agent_limit,staff_limit,reseller_customers,
                        price_minor,overage_minor,google_key_limit,assistant_key_limit,status,created_at)
                        VALUES ('user',?,?,?,?,?,?,?,?,?,?,'active',?)",[
                        $v['name'],$v['call_quota'],$v['site_limit'],$v['agent_limit'],
                        $v['staff_limit'],$v['reseller_customers'],$v['price_minor'],$v['overage_minor'],
                        $v['google_key_limit'],$v['assistant_key_limit'],now()]);
                    insert('INSERT INTO reseller_plan_owners(plan_id,reseller_account_id,source_plan_id,created_at) VALUES(?,?,?,?)',
                        [$newId,$reseller,$source['source_plan_id']??null,now()]);
                    $planId=$newId;
                }else{
                    execute('UPDATE plans SET name=?,call_quota=?,site_limit=?,agent_limit=?,
                        staff_limit=?,reseller_customers=?,price_minor=?,overage_minor=?,
                        google_key_limit=?,assistant_key_limit=? WHERE id=?',[
                        $v['name'],$v['call_quota'],$v['site_limit'],$v['agent_limit'],
                        $v['staff_limit'],$v['reseller_customers'],$v['price_minor'],$v['overage_minor'],
                        $v['google_key_limit'],$v['assistant_key_limit'],$planId]);
                }
            }
        }
        db()->commit();
        return $planId;
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        throw $e;
    }
}
/** Referenced plans retain financial history; unreferenced ones may be erased. */
function plan_catalog_dispose(int $planId,int $owner,string $operation):string {
    if(!in_array($operation,['archive','restore','delete'],true))throw new InvalidArgumentException('invalid_plan_action');
    plan_catalog_init();
    $plan=one('SELECT id,status FROM plans WHERE id=?',[$planId]);
    if(!$plan || ($owner>0 && plan_catalog_owner($planId)!==$owner) ||
        ($owner===0 && plan_catalog_owner($planId)!==0))
        throw new InvalidArgumentException('الباقة ليست ضمن صلاحيات حسابك');
    if($operation==='restore'){
        execute("UPDATE plans SET status='active' WHERE id=?",[$planId]);
        return 'restored';
    }
    $uses=(int)(one('SELECT COUNT(*) n FROM subscriptions WHERE plan_id=?',[$planId])['n']??0)
        +(int)(one('SELECT COUNT(*) n FROM orders WHERE plan_id=?',[$planId])['n']??0);
    if($operation==='delete' && $uses===0){
        db()->beginTransaction();
        try{
            execute('DELETE FROM reseller_plan_owners WHERE plan_id=?',[$planId]);
            execute('DELETE FROM plans WHERE id=?',[$planId]);
            db()->commit();return 'deleted';
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    }
    execute("UPDATE plans SET status='archived' WHERE id=?",[$planId]);
    return 'archived';
}
/** Owner-controlled stop: never erase previous transactions or extend a paid period. */
function subscription_cancel_now(int $accountId):bool {
    $a=account($accountId);
    if(!$a||!in_array($a['kind'],['reseller','customer'],true))
        throw new InvalidArgumentException('invalid_subscription_account');
    return execute("UPDATE subscriptions SET status='cancelled'
        WHERE account_id=? AND status='active'",[$accountId])>0;
}
function subscription_renew_free(int $accountId,int $planId):array {
    $account=account($accountId);
    $plan=one('SELECT * FROM plans WHERE id=?',[$planId]);
    if(!$account||!$plan||!plan_catalog_allowed($plan,$account)||
        (int)$plan['price_minor']!==0)
        throw new InvalidArgumentException('التجديد المجاني متاح للباقات المجانية فقط');
    if(db()->inTransaction())throw new RuntimeException('nested_subscription_transaction');
    db()->beginTransaction();
    try{
        $active=one("SELECT id,plan_id,ends_at FROM subscriptions
            WHERE account_id=? AND status='active' AND starts_at<=? AND ends_at>?
            ORDER BY id DESC LIMIT 1",[$accountId,now(),now()]);
        if($active){
            db()->commit();
            return ['changed'=>false,'subscription_id'=>(int)$active['id']];
        }
        execute("UPDATE subscriptions SET status='expired' WHERE account_id=? AND status='active'",
            [$accountId]);
        $sub=insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at)
            VALUES (?,?,'active',?,?)",[$accountId,$planId,now(),
            gmdate('Y-m-d H:i:s',time()+30*86400)]);
        db()->commit();return ['changed'=>true,'subscription_id'=>$sub];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
/** Informational amount estimate; NOT a gateway charge or settled receipt. */
function subscription_change_quote(?array $current,array $target):array {
    $old=(int)($current['price_minor']??0);
    $new=(int)($target['price_minor']??0);
    $remaining=0;
    if($current && !empty($current['ends_at']))
        $remaining=max(0,strtotime((string)$current['ends_at'])-time());
    return ['direction'=>$new>$old?'upgrade':($new<$old?'downgrade':'switch'),
        'old_minor'=>$old,'new_minor'=>$new,
        'remaining_days'=>(int)ceil($remaining/86400),
        'full_period_difference_minor'=>$new-$old];
}
