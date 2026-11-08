<?php
declare(strict_types=1);

/**
 * Six-digit one-time codes: no QR or user-visible secret keys.
 * Each code is valid for five minutes and locked to one SIM slot/tenant.
 * Database stores a keyed hash using a private server-only secret, never digits.
 * Redeeming remains atomic and legacy 24-character tickets cannot be used.
 */
function device_pairing_secret():string {
    static $secret=null;
    if($secret!==null)return $secret;
    $path=private_path().'/sim_pairing_hmac_v2.key';
    if(!is_file($path)){
        $handle=@fopen($path,'x');
        if($handle!==false){
            try{
                @chmod($path,0600);
                $generated=random_bytes(32);
                if(fwrite($handle,$generated)!==32)throw new RuntimeException('device_pair_secret_write_failed');
                fflush($handle);
            }finally{fclose($handle);}
        }
    }
    $secret=file_get_contents($path);
    if(!is_string($secret)||strlen($secret)!==32)
        throw new RuntimeException('device_pair_secret_unavailable');
    return $secret;
}
function device_pairing_hash(string $code,int $bucket):string {
    return hash_hmac('sha256','sim6:'.$bucket.':'.$code,device_pairing_secret());
}
function device_pairing_create(int $accountId,int $siteId,int $agentId,int $userId,int $simSlot):string {
    if(!in_array($simSlot,[0,1],true))throw new InvalidArgumentException('device_pairing_invalid_slot');
    $account=account($accountId);
    $site=one("SELECT id FROM sites WHERE id=? AND account_id=? AND status='active'",[$siteId,$accountId]);
    $agent=one("SELECT id FROM agents WHERE id=? AND account_id=? AND site_id=? AND status!='archived'",[$agentId,$accountId,$siteId]);
    if(!$account||$account['status']!=='active'||!$site||!$agent)
        throw new InvalidArgumentException('device_invalid_account_site_agent');
    $bucket=intdiv(time(),300);
    for($attempt=0;$attempt<40;$attempt++){
        $digits=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        $hash=device_pairing_hash($digits,$bucket);
        $older=device_pairing_hash($digits,$bucket-1);
        // Collision guard across all tenants and both SIM slots.
        if(one("SELECT id FROM device_pairings WHERE code_hash IN (?,?)
            AND status IN ('pending_sim0','pending_sim1') AND expires_at>?",
            [$hash,$older,now()]))continue;
        db()->beginTransaction();
        try{
            // A replacement click invalidates the earlier 5-minute code for
            // the SAME account/SIM only. This avoids misleading duplicate codes
            // or hitting the pending-code cap after a couple of retries.
            execute("UPDATE device_pairings SET status='cancelled'
                WHERE account_id=? AND status=? AND expires_at>?",
                [$accountId,'pending_sim'.$simSlot,now()]);
            $pending=(int)(one("SELECT COUNT(*) n FROM device_pairings WHERE
                account_id=? AND status IN ('pending','pending_sim0','pending_sim1')
                AND expires_at>?",[$accountId,now()])['n']??0);
            if($pending>=5)throw new InvalidArgumentException('device_pending_limit');
            insert("INSERT INTO device_pairings(account_id,site_id,agent_id,code_hash,status,expires_at,created_by,created_at)
                VALUES (?,?,?,?,?,?,?,?)",[
                $accountId,$siteId,$agentId,$hash,'pending_sim'.$simSlot,
                gmdate('Y-m-d H:i:s',time()+300),$userId,now()
            ]);
            db()->commit();
            return $digits;
        }catch(PDOException $collision){
            if(db()->inTransaction())db()->rollBack();
            if(!in_array((string)$collision->getCode(),['23000','19'],true))throw $collision;
        }catch(Throwable $failure){
            if(db()->inTransaction())db()->rollBack();
            throw $failure;
        }
    }
    throw new RuntimeException('device_pairing_try_again');
}
/** Global, IP and installation rate limits to protect the small 6-digit space.
 * The caller provides the server-observed network address, never an XFF header.
 * A fresh installation_id cannot bypass the IP or global cap.
 */
function device_pairing_rate_guard(string $peer,string $installationId):void {
    if(!filter_var($peer,FILTER_VALIDATE_IP))
        throw new InvalidArgumentException('device_pairing_network_invalid');
    if(!preg_match('/^[a-zA-Z0-9_.:-]{12,128}$/D',$installationId))
        throw new InvalidArgumentException('device_pairing_invalid');
    if(driver()==='mysql'){
        db()->exec("CREATE TABLE IF NOT EXISTS device_pair_attempts (
          bucket_hash CHAR(64) NOT NULL PRIMARY KEY,
          window_epoch BIGINT NOT NULL, attempts INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }else{
        db()->exec("CREATE TABLE IF NOT EXISTS device_pair_attempts (
          bucket_hash TEXT PRIMARY KEY, window_epoch INTEGER NOT NULL, attempts INTEGER NOT NULL)");
    }
    $window=intdiv(time(),300);
    foreach([
        ['ip:'.$peer,20],
        ['install:'.$installationId,12],
        ['global',300]
    ] as [$subject,$max]){
        $key=hash_hmac('sha256','sim-rate:'.$window.':'.$subject,device_pairing_secret());
        if(driver()==='mysql'){
            execute("INSERT INTO device_pair_attempts(bucket_hash,window_epoch,attempts) VALUES (?,?,1)
              ON DUPLICATE KEY UPDATE attempts=attempts+1",[$key,$window]);
        }else{
            execute("INSERT INTO device_pair_attempts(bucket_hash,window_epoch,attempts) VALUES (?,?,1)
              ON CONFLICT(bucket_hash) DO UPDATE SET attempts=attempts+1",[$key,$window]);
        }
        $count=(int)(one("SELECT attempts FROM device_pair_attempts WHERE bucket_hash=?",[$key])['attempts']??999);
        if($count>$max)throw new InvalidArgumentException('device_pairing_rate_limited');
    }
    // Expired rate buckets hold no active pairing credentials; keep table small.
    if(random_int(0,31)===0)
        execute('DELETE FROM device_pair_attempts WHERE window_epoch<?',[$window-3]);
}
function device_pairing_redeem(string $code,string $installationId,int $simSlot,string $label):array {
    $clean=trim($code);
    if(!preg_match('/^[0-9]{6}$/D',$clean)
        || !preg_match('/^[a-zA-Z0-9_.:-]{12,128}$/D',$installationId)
        || !in_array($simSlot,[0,1],true)
        || mb_strlen($label)>100 || $label==='')
        throw new InvalidArgumentException('device_pairing_invalid');
    $bucket=intdiv(time(),300);
    $hashes=[device_pairing_hash($clean,$bucket),device_pairing_hash($clean,$bucket-1)];
    $token='otdev_'.bin2hex(random_bytes(32));
    $claim=hash('sha256',$installationId.'|'.$simSlot);
    db()->beginTransaction();
    try{
        $pair=one("SELECT * FROM device_pairings WHERE code_hash IN (?,?)
              AND status IN ('pending_sim0','pending_sim1') AND expires_at>?",
              [$hashes[0],$hashes[1],now()]);
        if(!$pair)throw new InvalidArgumentException('device_pairing_expired_or_used');
        if($pair['status']!=='pending_sim'.$simSlot)
            throw new InvalidArgumentException('device_pairing_slot_mismatch');
        $a=account((int)$pair['account_id']);
        $site=one("SELECT id FROM sites WHERE id=? AND account_id=? AND status='active'",[$pair['site_id'],$pair['account_id']]);
        $agent=one("SELECT id FROM agents WHERE id=? AND account_id=? AND site_id=? AND status!='archived'",
            [$pair['agent_id'],$pair['account_id'],$pair['site_id']]);
        if(!$a||$a['status']!=='active'||!$site||!$agent)
            throw new InvalidArgumentException('device_pairing_target_unavailable');
        // Reserve slot atomically using UNIQUE nullable slot_claim across tenants.
        $deviceId=insert("INSERT INTO device_bindings(account_id,site_id,agent_id,installation_id,sim_slot,slot_claim,device_name,token_hash,status,created_at)
            VALUES (?,?,?,?,?,?,?,?,'active',?)",[
                $pair['account_id'],$pair['site_id'],$pair['agent_id'],$installationId,$simSlot,
                $claim,$label,hash('sha256',$token),now()
            ]);
        // Existing bindings are backfilled by migration; newly enrolled SIMs
        // also need the immutable initial assignment before later hot swaps.
        insert('INSERT INTO device_agent_revisions
            (device_id,account_id,site_id,agent_id,agent_revision,created_at)
            VALUES (?,?,?,?,?,?)',[
            $deviceId,(int)$pair['account_id'],(int)$pair['site_id'],
            (int)$pair['agent_id'],1,now()]);
        $changed=execute("UPDATE device_pairings SET status='redeemed',device_id=?,redeemed_at=? WHERE id=? AND status=? AND expires_at>?",
            [$deviceId,now(),$pair['id'],'pending_sim'.$simSlot,now()]);
        if($changed!==1)throw new RuntimeException('device_pairing_race');
        db()->commit();
        return ['device_id'=>$deviceId,'device_token'=>$token,'sim_slot'=>$simSlot,
            'site_id'=>(int)$pair['site_id'],'agent_id'=>(int)$pair['agent_id']];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
/** Active token must be tied to a valid, current account, site and agent. */
function device_binding_authorize(string $token):?array {
    if(!preg_match('/^otdev_[a-f0-9]{64}$/D',$token))return null;
    return one("SELECT d.* FROM device_bindings d
        JOIN accounts a ON a.id=d.account_id AND a.status='active'
        JOIN sites s ON s.id=d.site_id AND s.account_id=d.account_id AND s.status='active'
        JOIN agents ag ON ag.id=d.agent_id AND ag.site_id=d.site_id AND ag.account_id=d.account_id AND ag.status!='archived'
        WHERE d.token_hash=? AND d.status='active'",[hash('sha256',$token)]);
}
require_once __DIR__.'/PublishedFlow.php';
function device_binding_profile(array $binding):array {
    $agent=one('SELECT id,name,voice,instructions FROM agents WHERE id=? AND account_id=? AND site_id=?',
        [$binding['agent_id'],$binding['account_id'],$binding['site_id']]);
    $site=one('SELECT id,name FROM sites WHERE id=? AND account_id=?',[$binding['site_id'],$binding['account_id']]);
    $tenant=account((int)$binding['account_id']);
    if(!$agent||!$site||!$tenant||$tenant['status']!=='active')throw new RuntimeException('device_profile_unavailable');
    $published=published_agent_flow((int)$binding['account_id'],(int)$binding['site_id'],(int)$binding['agent_id']);
    return ['flow_revision'=>$published?(int)$published['revision']:null,
        'flow_hash'=>$published?(string)$published['flow_hash']:null,
        'flow_ready'=>$published!==null,
        'device_id'=>(int)$binding['id'],'account_id'=>(int)$binding['account_id'],
        'account_name'=>(string)$tenant['name'],
        'site_id'=>(int)$site['id'],'site_name'=>$site['name'],
        'agent_id'=>(int)$agent['id'],'agent_name'=>$agent['name'],
        'agent_revision'=>(int)($binding['agent_revision']??1),
        // Draft agent changes must never leak through a bound SIM heartbeat.
        // Only a specifically published immutable revision can supply instructions.
        'voice'=>$published?(string)$published['voice']:null,
        'instruction'=>$published?(string)$published['instructions']:null,
        'answer_policy'=>$published?answer_policy_normalize((array)(json_decode((string)($published['answer_policy_json']??''),true)??[])):null,
        'sim_slot'=>(int)$binding['sim_slot'],'heartbeat_seconds'=>10,
        'transport'=>'tenant_pairing_preview_no_sim_bridge'];
}
function device_binding_heartbeat(array $binding):array {
    // A binding can be revoked between bearer authorization and heartbeat.
    // Never return its agent policy after revocation, even during that race.
    $updated=execute('UPDATE device_bindings SET last_seen_at=? WHERE id=? AND account_id=? AND sim_slot=? AND status=?',
        [now(),$binding['id'],$binding['account_id'],$binding['sim_slot'],'active']);
    if($updated!==1)throw new RuntimeException('device_binding_revoked');
    $current=one("SELECT d.* FROM device_bindings d
        JOIN accounts a ON a.id=d.account_id AND a.status='active'
        JOIN sites s ON s.id=d.site_id AND s.account_id=d.account_id AND s.status='active'
        JOIN agents ag ON ag.id=d.agent_id AND ag.site_id=d.site_id AND ag.account_id=d.account_id AND ag.status!='archived'
        WHERE d.id=? AND d.account_id=? AND d.sim_slot=? AND d.status='active'",
        [$binding['id'],$binding['account_id'],$binding['sim_slot']]);
    if(!$current)throw new RuntimeException('device_binding_revoked');
    return device_binding_profile($current);
}
/**
 * Switch the agent running on an already-paired physical SIM slot.
 * The binding, slot claim and bearer stay exactly the same; only the owner-
 * scoped, same-site agent changes. A current call keeps its issued session
 * snapshot; the next heartbeat / new voice session receives the new agent.
 */
function device_binding_change_agent(int $accountId,int $deviceId,int $newAgentId):array {
    if($accountId<1||$deviceId<1||$newAgentId<1)
        throw new InvalidArgumentException('device_agent_invalid_target');
    $db=db();
    $db->beginTransaction();
    try {
        $binding=one('SELECT * FROM device_bindings WHERE id=? AND account_id=? AND status=?',
            [$deviceId,$accountId,'active']);
        if(!$binding)throw new InvalidArgumentException('الشريحة غير مربوطة بحسابك أو تم فصلها');
        $agent=one("SELECT id,name FROM agents
            WHERE id=? AND account_id=? AND site_id=? AND status!='archived'",
            [$newAgentId,$accountId,(int)$binding['site_id']]);
        if(!$agent)throw new InvalidArgumentException('اختار إيجنت من نفس الشركة والموقع');
        $published=published_agent_flow($accountId,(int)$binding['site_id'],$newAgentId);
        if(!$published)throw new InvalidArgumentException('لازم تنشر إعدادات الإيجنت الجديد قبل ربطه بالشريحة');
        $previous=(int)$binding['agent_id'];
        $revision=(int)($binding['agent_revision']??1);
        if($previous!==$newAgentId){
            $changed=execute('UPDATE device_bindings SET agent_id=?,agent_revision=agent_revision+1
                WHERE id=? AND account_id=? AND site_id=? AND sim_slot=?
                  AND status=? AND agent_id=? AND agent_revision=?',[
                    $newAgentId,$deviceId,$accountId,(int)$binding['site_id'],
                    (int)$binding['sim_slot'],'active',$previous,$revision]);
            if($changed!==1)throw new RuntimeException('اتغير ربط الشريحة بالتزامن؛ حدّث الصفحة وأعد المحاولة');
            insert('INSERT INTO device_agent_revisions
                (device_id,account_id,site_id,agent_id,agent_revision,created_at)
                VALUES (?,?,?,?,?,?)',[
                $deviceId,$accountId,(int)$binding['site_id'],$newAgentId,$revision+1,now()]);
            ++$revision;
        }
        $db->commit();
        return ['device_id'=>$deviceId,'site_id'=>(int)$binding['site_id'],
            'sim_slot'=>(int)$binding['sim_slot'],'previous_agent_id'=>$previous,
            'agent_id'=>$newAgentId,'agent_name'=>(string)$agent['name'],
            'agent_revision'=>$revision,
            'changed'=>$previous!==$newAgentId];
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}
/** Revoke all pending automated operations on this exact device/SIM.
 * Never silently reassign an outstanding campaign to a replacement phone. */
function device_binding_halt_campaigns(int $accountId,int $deviceId):void {
    require_once __DIR__.'/TenantCampaignQueue.php';
    tcq_init();
    execute("UPDATE tenant_campaigns SET status='paused'
      WHERE account_id=? AND device_binding_id=? AND status='ready'",[$accountId,$deviceId]);
    execute("UPDATE tenant_campaign_attempts SET status='unknown',
        result='device_revoked',finished_at=?
        WHERE account_id=? AND device_binding_id=? AND status IN ('leased','verified')",
        [now(),$accountId,$deviceId]);
}
function device_binding_revoke(int $accountId,int $deviceId):bool {
    $changed=execute("UPDATE device_bindings SET status='revoked',slot_claim=NULL,revoked_at=? WHERE id=? AND account_id=? AND status='active'",
        [now(),$deviceId,$accountId]);
    if($changed===1)device_binding_halt_campaigns($accountId,$deviceId);
    return $changed===1;
}
function device_pairing_cancel(int $accountId,int $pairingId):bool {
    return execute("UPDATE device_pairings SET status='cancelled' WHERE id=? AND account_id=? AND status IN ('pending','pending_sim0','pending_sim1')",
        [$pairingId,$accountId])===1;
}

/** Device-owner initiated disconnect. Revokes only the authenticated SIM binding. */
function device_binding_disconnect(array $binding):bool {
    $changed=execute("UPDATE device_bindings SET status='revoked',slot_claim=NULL,revoked_at=? WHERE id=? AND account_id=? AND sim_slot=? AND status='active'",
        [now(),$binding['id'],$binding['account_id'],$binding['sim_slot']]);
    if($changed===1)device_binding_halt_campaigns((int)$binding['account_id'],(int)$binding['id']);
    return $changed===1;
}
