<?php
declare(strict_types=1);

/**
 * Google Live key entitlements, tenant overrides and safe pool resolution.
 * A key belongs to exactly one account; platform and reseller keys are NOT
 * readable by their customer accounts. Only short-lived Google auth tokens
 * are returned to the paired device, never master API keys.
 */
function google_voice_key_schema():void {
    $marker=private_path().'/.schema.google-voice-tenant-keys.v1';
    if(is_file($marker))return;
    $columns=driver()==='mysql'
        ?array_column(all('SHOW COLUMNS FROM plans'),'Field')
        :array_column(all('PRAGMA table_info(plans)'),'name');
    if(!in_array('google_key_limit',$columns,true)){
        db()->exec('ALTER TABLE plans ADD COLUMN google_key_limit INTEGER NOT NULL DEFAULT 1');
        // Resellers have an unlimited Google Live key pool by default.
        execute("UPDATE plans SET google_key_limit=0 WHERE kind='reseller'");
    }
    $sql=driver()==='mysql'
        ?"CREATE TABLE IF NOT EXISTS google_voice_key_overrides (
            account_id BIGINT NOT NULL PRIMARY KEY,
            max_keys INTEGER NOT NULL,
            updated_at VARCHAR(19) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        :"CREATE TABLE IF NOT EXISTS google_voice_key_overrides (
            account_id INTEGER PRIMARY KEY,
            max_keys INTEGER NOT NULL,
            updated_at VARCHAR(19) NOT NULL
        )";
    db()->exec($sql);
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** 0 means unlimited for platform/reseller only; customer 0 means disabled. */
function google_voice_key_limit(int $accountId):int {
    google_voice_key_schema();
    $account=account($accountId);
    if(!$account||($account['status']??'')!=='active')return 0;
    if(in_array($account['kind'],['platform','reseller'],true))return 0;
    $subscription=one("SELECT p.google_key_limit FROM subscriptions s
        JOIN plans p ON p.id=s.plan_id AND p.kind='user' AND p.status='active'
        WHERE s.account_id=? AND s.status='active'
          AND s.starts_at<=? AND s.ends_at>?
        ORDER BY s.id DESC LIMIT 1",[$accountId,now(),now()]);
    if(!$subscription)return 0;
    $planLimit=max(0,min(1000,(int)$subscription['google_key_limit']));
    $override=one('SELECT max_keys FROM google_voice_key_overrides WHERE account_id=?',[$accountId]);
    if($override!==null)$planLimit=min($planLimit,max(0,(int)$override['max_keys']));
    return $planLimit;
}

function google_voice_key_set_customer_limit(int $resellerId,int $customerId,int $limit):void {
    google_voice_key_schema();
    if($limit<0||$limit>1000)throw new InvalidArgumentException('invalid_google_key_limit');
    $reseller=account($resellerId);
    $customer=account($customerId);
    if(!$reseller||$reseller['kind']!=='reseller'||$reseller['status']!=='active'||
        !$customer||$customer['kind']!=='customer'||(int)$customer['parent_id']!==$resellerId||
        $customer['status']!=='active')
        throw new InvalidArgumentException('google_key_limit_owner_mismatch');
    // A reseller can reduce a customer's plan allowance, never raise it.
    $current=one('SELECT max_keys FROM google_voice_key_overrides WHERE account_id=?',[$customerId]);
    if($current)execute('DELETE FROM google_voice_key_overrides WHERE account_id=?',[$customerId]);
    $plan=one("SELECT p.google_key_limit FROM subscriptions s JOIN plans p ON p.id=s.plan_id
        WHERE s.account_id=? AND s.status='active' AND p.kind='user'
        AND s.starts_at<=? AND s.ends_at>? ORDER BY s.id DESC LIMIT 1",
        [$customerId,now(),now()]);
    $ceiling=max(0,(int)($plan['google_key_limit']??0));
    if($limit>$ceiling)throw new InvalidArgumentException('google_key_limit_exceeds_plan');
    insert('INSERT INTO google_voice_key_overrides(account_id,max_keys,updated_at)
        VALUES (?,?,?)',[$customerId,$limit,now()]);
}

/** A customer with any own keys is BYOK-only, even when all are disabled.
 * Otherwise use their reseller's pool if configured; lastly platform.
 * Never mix credentials across pools during a single session allocation.
 */
function google_voice_pool_owner(int $accountId):int {
    $account=account($accountId);
    if(!$account||($account['status']??'')!=='active')
        throw new RuntimeException('voice_account_inactive');
    if(ai_relay_rows('voice',false,$accountId)!==[])return $accountId;
    if($account['kind']==='customer'&&(int)($account['parent_id']??0)>0){
        $parent=account((int)$account['parent_id']);
        if($parent&&$parent['kind']==='reseller'&&$parent['status']==='active' &&
           ai_relay_rows('voice',false,(int)$parent['id'])!==[])
            return (int)$parent['id'];
    }
    return ai_relay_platform();
}
