<?php
declare(strict_types=1);

/**
 * Multi-tenant Android SIM enrollment. This MUST NOT reuse the old pilot bearer.
 * New six-digit pairing codes expire in ten minutes and are one-time, SHA256-only.
 * SIM is enforced server-side; legacy 24-hex codes remain valid until expiry.
 * Each installation/physical SIM slot can have at most one active binding.
 */
function device_pairing_create(int $accountId,int $siteId,int $agentId,int $userId,int $simSlot):string {
    if(!in_array($simSlot,[0,1],true))throw new InvalidArgumentException('device_pairing_invalid_slot');
    $account=account($accountId);
    $site=one("SELECT id FROM sites WHERE id=? AND account_id=? AND status='active'",[$siteId,$accountId]);
    $agent=one("SELECT id FROM agents WHERE id=? AND account_id=? AND site_id=? AND status!='archived'",[$agentId,$accountId,$siteId]);
    if(!$account||$account['status']!=='active'||!$site||!$agent)
        throw new InvalidArgumentException('device_invalid_account_site_agent');
    $pending=(int)one("SELECT COUNT(*) n FROM device_pairings WHERE account_id=? AND status IN ('pending','pending_sim0','pending_sim1') AND expires_at>?",[$accountId,now()])['n'];
    if($pending>=5)throw new InvalidArgumentException('device_pending_limit');
    // Unique six digits, including leading zeroes. Plaintext never stored.
    $raw='';
    for($attempt=0;$attempt<64;$attempt++){
        $candidate=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        if(!one('SELECT id FROM device_pairings WHERE code_hash=?',[hash('sha256',$candidate)])){
            $raw=$candidate;break;
        }
    }
    if($raw==='')throw new RuntimeException('device_pairing_code_space_exhausted');
    insert("INSERT INTO device_pairings(account_id,site_id,agent_id,code_hash,status,expires_at,created_by,created_at)
       VALUES (?,?,?,?,?,?,?,?)",
       [$accountId,$siteId,$agentId,hash('sha256',$raw),'pending_sim'.$simSlot,gmdate('Y-m-d H:i:s',time()+600),$userId,now()]);
    return $raw;
}
/** Persisted throttle for low-entropy six-digit codes; IP is REMOTE_ADDR, not user headers. */
function device_pairing_throttle(string $installationId):void {
    if(PHP_SAPI==='cli' && empty($_SERVER['REMOTE_ADDR']))return;
    db()->exec('CREATE TABLE IF NOT EXISTS device_pair_attempts (
        fingerprint CHAR(64) PRIMARY KEY, window_epoch BIGINT NOT NULL, attempts INT NOT NULL)');
    $now=time();
    foreach([['install:'.$installationId,5],
             ['ip:'.(string)($_SERVER['REMOTE_ADDR']??'unknown'),12],
             ['global',300]] as [$identity,$limit]){
        $fingerprint=hash('sha256','device-pair-v1|'.$identity);
        try{
            execute('INSERT INTO device_pair_attempts(fingerprint,window_epoch,attempts) VALUES (?,?,0)',
                [$fingerprint,$now]);
        }catch(PDOException $e){
            if(!in_array((string)$e->getCode(),['23000','19'],true))throw $e;
        }
        execute('UPDATE device_pair_attempts SET
            attempts=CASE WHEN window_epoch<? THEN 1 ELSE attempts+1 END,
            window_epoch=CASE WHEN window_epoch<? THEN ? ELSE window_epoch END
            WHERE fingerprint=?',[$now-600,$now-600,$now,$fingerprint]);
        $entry=one('SELECT attempts FROM device_pair_attempts WHERE fingerprint=?',[$fingerprint]);
        if((int)$entry['attempts']>$limit)
            throw new InvalidArgumentException('device_pairing_rate_limited');
    }
}
function device_pairing_redeem(string $code,string $installationId,int $simSlot,string $label):array {
    $clean=strtolower(str_replace('-','',trim($code)));
    $short=preg_match('/^[0-9]{6}$/D',$clean)===1;
    $legacy=preg_match('/^[a-f0-9]{24}$/D',$clean)===1;
    if((!$short&&!$legacy)
        || !preg_match('/^[a-zA-Z0-9_.:-]{12,128}$/D',$installationId)
        || !in_array($simSlot,[0,1],true)
        || mb_strlen($label)>100 || $label==='')
        throw new InvalidArgumentException('device_pairing_invalid');
    // Old 24-character codes have an encoded SIM marker; new numeric codes do not.
    if($legacy && $clean[0] !== (string)$simSlot)
        throw new InvalidArgumentException('device_pairing_slot_mismatch');
    if($short)device_pairing_throttle($installationId);
    $token='otdev_'.bin2hex(random_bytes(32));
    $claim=hash('sha256',$installationId.'|'.$simSlot);
    db()->beginTransaction();
    try{
        $pair=one("SELECT * FROM device_pairings WHERE code_hash=? AND expires_at>?",
            [hash('sha256',$clean),now()]);
        if(!$pair || !in_array((string)$pair['status'],['pending_sim0','pending_sim1'],true))
            throw new InvalidArgumentException('device_pairing_expired_or_used');
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
    // MySQL reports *changed* rows (not matched rows): a second heartbeat
    // within the same second can update an identical timestamp and return 0.
    // Authorization is determined by the scoped active-row SELECT below,
    // never by whether the last_seen_at text changed.
    execute('UPDATE device_bindings SET last_seen_at=? WHERE id=? AND account_id=? AND sim_slot=? AND status=?',
        [now(),$binding['id'],$binding['account_id'],$binding['sim_slot'],'active']);
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
