<?php
declare(strict_types=1);
require_once __DIR__.'/TenantAccountBridge.php';

/**
 * Durable account-scoped opt-out ledger. Unlike contacts, these records are
 * not removed by routine contact CRUD or campaign imports.
 *
 * The one-time additive migration is deliberately separate from Bootstrap:
 * the open SSO PR also edits Bootstrap.php. Initialize before transactions
 * (MySQL DDL implicitly commits) and fail closed if initialization fails.
 */
function dnc_init_ledger():void {
    static $ready=false;
    if($ready)return;
    $marker=private_path().'/schema.dnc.v1';
    if(is_file($marker)){$ready=true;return;}
    if(db()->inTransaction())throw new RuntimeException('dnc_ledger_requires_pretransaction_migration');
    if(driver()==='mysql'){
        db()->exec("CREATE TABLE IF NOT EXISTS dnc_suppressions (
            account_id BIGINT UNSIGNED NOT NULL,
            phone_key VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            source VARCHAR(32) NOT NULL,
            created_at VARCHAR(32) NOT NULL,
            PRIMARY KEY(account_id,phone_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }else{
        db()->exec("CREATE TABLE IF NOT EXISTS dnc_suppressions (
            account_id INTEGER NOT NULL,
            phone_key TEXT NOT NULL,
            source TEXT NOT NULL,
            created_at TEXT NOT NULL,
            PRIMARY KEY(account_id,phone_key)
        )");
    }
    // Backfill opted-out contacts on upgrades. Bounded batches prevent loading
    // an entire tenant's contact list into memory. Safe to replay after a crash.
    $lastId=0;
    do {
        $batch=all("SELECT id,account_id,phone FROM contacts WHERE dnc=1 AND id>? ORDER BY id LIMIT 250",[$lastId]);
        foreach($batch as $row){
            $lastId=(int)$row['id'];
            $key=dnc_phone_key((string)$row['phone']);
            if((int)$row['account_id']>0 && strlen($key)>=8 && strlen($key)<=40)
                dnc_insert_ledger_key((int)$row['account_id'],$key,'legacy_contact');
        }
    }while(count($batch)===250);
    if(file_put_contents($marker,now(),LOCK_EX)===false)
        throw new RuntimeException('dnc_ledger_migration_marker_failed');
    @chmod($marker,0600);
    $ready=true;
}

/** Idempotent, append-only suppression. Never update an existing opt-out. */
function dnc_insert_ledger_key(int $accountId,string $key,string $source):void {
    if($accountId<=0 || !preg_match('/^[0-9]{8,40}$/D',$key) ||
        !in_array($source,['legacy_contact','contact_optout','booking_no_consent'],true))
        throw new InvalidArgumentException('invalid_dnc_ledger_entry');
    $sql=driver()==='mysql'
        ?'INSERT IGNORE INTO dnc_suppressions(account_id,phone_key,source,created_at) VALUES (?,?,?,?)'
        :'INSERT OR IGNORE INTO dnc_suppressions(account_id,phone_key,source,created_at) VALUES (?,?,?,?)';
    execute($sql,[$accountId,$key,$source,now()]);
}

function dnc_record_suppression(int $accountId,string $phone,string $source='contact_optout'):void {
    dnc_init_ledger();
    $key=dnc_phone_key($phone);
    dnc_insert_ledger_key($accountId,$key,$source);
    TenantAccountBridge::mirrorDncToLaravel($accountId,$key,$source);
}

function dnc_ledger_contains(int $accountId,string $key):bool {
    dnc_init_ledger();
    return one('SELECT phone_key FROM dnc_suppressions WHERE account_id=? AND phone_key=?',
        [$accountId,$key])!==null;
}
