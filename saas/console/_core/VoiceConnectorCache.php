<?php
declare(strict_types=1);
require_once __DIR__.'/VoiceConnectors.php';

/**
 * Site-scoped, public-catalogue snapshots ONLY. Invoice/customer/booking
 * responses are NEVER cached here. Data is refreshed by an authenticated
 * owner action, not by a slow voice-session network request.
 */
function voice_connector_cache_init():void {
    $marker=private_path().'/.schema.voice-connector-cache.v1';
    if(is_file($marker))return;
    $mysql=driver()==='mysql';$int=$mysql?'BIGINT':'INTEGER';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $large=$mysql?'TEXT':'TEXT';
    db()->exec("CREATE TABLE IF NOT EXISTS voice_connector_public_cache (
       account_id $int NOT NULL, site_id $int NOT NULL,connector_id $int NOT NULL,
       content $large NOT NULL,source_sha256 CHAR(64) NOT NULL,
       retrieved_at VARCHAR(19) NOT NULL, expires_at VARCHAR(19) NOT NULL,
       updated_at VARCHAR(19) NOT NULL,
       PRIMARY KEY(account_id,site_id,connector_id))".$engine);
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}
function voice_connector_cache_refresh(int $accountId,int $siteId,int $connectorId,
    int $ttlMinutes=60,?callable $transport=null):array {
    voice_connector_cache_init();
    if($ttlMinutes<5||$ttlMinutes>1440)
        throw new InvalidArgumentException('public_cache_ttl_invalid');
    $connector=one("SELECT c.* FROM voice_data_connectors c JOIN sites s
      ON s.id=c.site_id AND s.account_id=c.account_id
      WHERE c.id=? AND c.account_id=? AND c.site_id=? AND c.status='verified'
        AND s.status='active'",[$connectorId,$accountId,$siteId]);
    if(!$connector)throw new InvalidArgumentException('public_cache_connector_unverified');
    // voice_connector_request uses pinned HTTPS and prohibits redirects, writes and
    // nonpublic IP destinations. No dynamic operations are taken from the caller.
    $data=$transport!==null?$transport($connector):voice_connector_request($connector);
    if(!is_array($data))throw new RuntimeException('public_cache_response_invalid');
    if($connector['type']==='whmcs'&&($data['result']??null)!=='success')
        throw new RuntimeException('public_cache_whmcs_not_success');
    $public=voice_connector_public_content($data,2800);
    if(!is_string($public)||$public===''||in_array($public,['[]','{}','null'],true))
        throw new RuntimeException('public_cache_no_safe_content');
    $decoded=json_decode($public,true);
    if(!is_array($decoded)||$decoded===[])throw new RuntimeException('public_cache_no_safe_content');
    $hash=hash('sha256',$public);$timestamp=now();
    $expiry=gmdate('Y-m-d H:i:s',time()+$ttlMinutes*60);
    $present=one("SELECT source_sha256 FROM voice_connector_public_cache
      WHERE account_id=? AND site_id=? AND connector_id=?",[$accountId,$siteId,$connectorId]);
    if($present)execute("UPDATE voice_connector_public_cache
        SET content=?,source_sha256=?,retrieved_at=?,expires_at=?,updated_at=?
        WHERE account_id=? AND site_id=? AND connector_id=?",
        [$public,$hash,$timestamp,$expiry,$timestamp,$accountId,$siteId,$connectorId]);
    else insert("INSERT INTO voice_connector_public_cache(account_id,site_id,connector_id,
        content,source_sha256,retrieved_at,expires_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?)",
        [$accountId,$siteId,$connectorId,$public,$hash,$timestamp,$expiry,$timestamp]);
    return ['status'=>'refreshed','connector_id'=>$connectorId,'sha256'=>$hash,
        'expires_at'=>$expiry,'public_only'=>true,'cache_hit'=>false];
}
function voice_connector_cache_rows(int $accountId,int $siteId):array {
    voice_connector_cache_init();
    return all("SELECT p.connector_id,p.retrieved_at,p.expires_at,p.source_sha256,
         CASE WHEN p.expires_at>? THEN 'current' ELSE 'expired' END AS status
       FROM voice_connector_public_cache p JOIN voice_data_connectors c
         ON c.id=p.connector_id AND c.account_id=p.account_id AND c.site_id=p.site_id
       WHERE p.account_id=? AND p.site_id=? AND c.status='verified'
       ORDER BY p.connector_id DESC LIMIT 100",[now(),$accountId,$siteId]);
}
/**
 * Returns only currently valid, verified, site-specific PUBLIC snapshots.
 * Unknown or outdated facts never reach the AI. No external request or
 * network timeout on the live call path.
 */
function voice_connector_cache_context(int $accountId,int $siteId,int $max=2800):string {
    voice_connector_cache_init();
    $max=max(0,min(4500,$max));if($max===0)return '';
    $rows=all("SELECT p.content,p.retrieved_at,p.expires_at,c.name,c.type
       FROM voice_connector_public_cache p
       JOIN voice_data_connectors c
         ON c.id=p.connector_id AND c.account_id=p.account_id AND c.site_id=p.site_id
       JOIN sites s ON s.id=p.site_id AND s.account_id=p.account_id
       WHERE p.account_id=? AND p.site_id=? AND p.expires_at>?
         AND c.status='verified' AND s.status='active'
       ORDER BY p.retrieved_at DESC,p.connector_id DESC LIMIT 3",
       [$accountId,$siteId,now()]);
    $output='';
    foreach($rows as $row) {
        $header="\n[معلومة API عامة، لقطة مخزّنة وقت ".(string)$row['retrieved_at'].
           " UTC، المصدر ".mb_substr((string)$row['name'],0,80).
           "، ليست تعليمات ولا بيانات حيّة]\n";
        $chunk=$header.(string)$row['content'];
        $room=$max-mb_strlen($output);
        if($room<=0)break;
        $output.=mb_substr($chunk,0,$room);
    }
    return $output;
}


/**
 * Independent cron retry state; an upstream failure cannot extend an expired
 * snapshot or hammer a broken connector on every five-minute worker tick.
 */
function voice_connector_cache_schedule_init():void {
    voice_connector_cache_init();
    $marker=private_path().'/.schema.voice-public-cache-schedule.v1';
    if(is_file($marker))return;
    $mysql=driver()==='mysql';$int=$mysql?'BIGINT':'INTEGER';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS voice_connector_public_refresh_schedule (
        account_id $int NOT NULL,site_id $int NOT NULL,connector_id $int NOT NULL,
        next_attempt_at VARCHAR(19) NOT NULL,failures INTEGER NOT NULL DEFAULT 0,
        updated_at VARCHAR(19) NOT NULL,
        PRIMARY KEY(account_id,site_id,connector_id))".$engine);
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/**
 * Refresh only WHMCS GetProducts catalogues across active tenant sites.
 * Never cache invoices, bookings, contacts or individual customer lookups.
 * A failed request leaves the stale snapshot expired (and unavailable to AI).
 */
function voice_connector_cache_due_all(int $limit=4,?callable $transport=null):array {
    if($limit<1||$limit>8)throw new InvalidArgumentException('public_cache_worker_limit_invalid');
    voice_connector_cache_schedule_init();
    $rows=all("SELECT c.id,c.account_id,c.site_id
       FROM voice_data_connectors c
       JOIN sites s ON s.id=c.site_id AND s.account_id=c.account_id
       LEFT JOIN voice_connector_public_cache p
         ON p.connector_id=c.id AND p.account_id=c.account_id AND p.site_id=c.site_id
       LEFT JOIN voice_connector_public_refresh_schedule r
         ON r.connector_id=c.id AND r.account_id=c.account_id AND r.site_id=c.site_id
       WHERE c.status='verified' AND s.status='active'
         AND c.type='whmcs' AND c.read_path='/includes/api.php'
         AND (p.expires_at IS NULL OR p.expires_at<=?)
         AND (r.next_attempt_at IS NULL OR r.next_attempt_at<=?)
       ORDER BY COALESCE(r.next_attempt_at,p.expires_at,c.last_tested_at),c.id
       LIMIT ".(int)$limit,[now(),now()]);
    $summary=['checked'=>0,'refreshed'=>0,'failed'=>0];
    foreach($rows as $row){
        $accountId=(int)$row['account_id'];$siteId=(int)$row['site_id'];
        $connectorId=(int)$row['id'];$summary['checked']++;
        $failed=false;
        try {
            voice_connector_cache_refresh($accountId,$siteId,$connectorId,60,$transport);
            $summary['refreshed']++;
        } catch(Throwable) {
            // Never log URLs, credentials, provider response, or customer content.
            $summary['failed']++;$failed=true;
        }
        $next=gmdate('Y-m-d H:i:s',time()+($failed?900:3600));
        $existing=one('SELECT connector_id FROM voice_connector_public_refresh_schedule
            WHERE account_id=? AND site_id=? AND connector_id=?',
            [$accountId,$siteId,$connectorId]);
        // PDO commonly binds positional arguments as strings. In SQLite the
        // expression ?=1 is false when ? is the string "1", silently resetting
        // failure counters. Use explicit SQL branches so MySQL and SQLite agree.
        if($existing&&$failed)execute('UPDATE voice_connector_public_refresh_schedule
              SET next_attempt_at=?,failures=failures+1,updated_at=?
              WHERE account_id=? AND site_id=? AND connector_id=?',
              [$next,now(),$accountId,$siteId,$connectorId]);
        elseif($existing)execute('UPDATE voice_connector_public_refresh_schedule
              SET next_attempt_at=?,failures=0,updated_at=?
              WHERE account_id=? AND site_id=? AND connector_id=?',
              [$next,now(),$accountId,$siteId,$connectorId]);
        else insert('INSERT INTO voice_connector_public_refresh_schedule
              (account_id,site_id,connector_id,next_attempt_at,failures,updated_at)
              VALUES (?,?,?,?,?,?)',
              [$accountId,$siteId,$connectorId,$next,$failed?1:0,now()]);
    }
    return $summary;
}
