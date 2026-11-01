<?php
declare(strict_types=1);

/**
 * Multi-tenant Android SIM enrollment. This MUST NOT reuse the old pilot bearer.
 * Pairing code: 92 random bits plus a non-secret SIM-slot nibble (0/1),
 * expires in 10 minutes, one-time, stored as SHA-256. Slot is server-locked.
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
    // 92 unpredictable bits; the first nibble is the approved physical slot.
    // Encoding the slot in the hashed one-time code avoids a schema migration
    // and makes a SIM1 code impossible to redeem for SIM2 (or vice versa).
    $raw=(string)$simSlot.substr(bin2hex(random_bytes(12)),1);
    insert("INSERT INTO device_pairings(account_id,site_id,agent_id,code_hash,status,expires_at,created_by,created_at)
       VALUES (?,?,?,?,?,?,?,?)",
       [$accountId,$siteId,$agentId,hash('sha256',$raw),'pending_sim'.$simSlot,gmdate('Y-m-d H:i:s',time()+600),$userId,now()]);
    return strtoupper(implode('-',str_split($raw,4)));
}
function device_pairing_redeem(string $code,string $installationId,int $simSlot,string $label):array {
    $clean=strtolower(str_replace('-','',trim($code)));
    if(!preg_match('/^[a-f0-9]{24}$/D',$clean)
        || !preg_match('/^[a-zA-Z0-9_.:-]{12,128}$/D',$installationId)
        || !in_array($simSlot,[0,1],true)
        || mb_strlen($label)>100 || $label==='')
        throw new InvalidArgumentException('device_pairing_invalid');
    // Reject a wrong-slot request before consuming the single-use ticket.
    // Legacy 'pending' tickets are intentionally not redeemable after this
    // change. New slot-bound tickets have an explicit status in the same DB
    // column; no schema migration or guessing a legacy ticket's intent.
    if($clean[0] !== (string)$simSlot)
        throw new InvalidArgumentException('device_pairing_slot_mismatch');
    $token='otdev_'.bin2hex(random_bytes(32));
    $claim=hash('sha256',$installationId.'|'.$simSlot);
    db()->beginTransaction();
    try{
        $pair=one("SELECT * FROM device_pairings WHERE code_hash=? AND status=? AND expires_at>?",[hash('sha256',$clean),'pending_sim'.$simSlot,now()]);
        if(!$pair)throw new InvalidArgumentException('device_pairing_expired_or_used');
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
function device_binding_profile(array $binding):array {
    $agent=one('SELECT id,name,voice,instructions FROM agents WHERE id=? AND account_id=? AND site_id=?',
        [$binding['agent_id'],$binding['account_id'],$binding['site_id']]);
    $site=one('SELECT id,name FROM sites WHERE id=? AND account_id=?',[$binding['site_id'],$binding['account_id']]);
    $tenant=account((int)$binding['account_id']);
    if(!$agent||!$site||!$tenant||$tenant['status']!=='active')throw new RuntimeException('device_profile_unavailable');
    return ['device_id'=>(int)$binding['id'],'account_id'=>(int)$binding['account_id'],
        'account_name'=>(string)$tenant['name'],
        'site_id'=>(int)$site['id'],'site_name'=>$site['name'],
        'agent_id'=>(int)$agent['id'],'agent_name'=>$agent['name'],
        'voice'=>$agent['voice'],'instruction'=>$agent['instructions'],
        'sim_slot'=>(int)$binding['sim_slot'],'heartbeat_seconds'=>10,
        'transport'=>'tenant_pairing_preview_no_sim_bridge'];
}
function device_binding_heartbeat(array $binding):array {
    // A binding can be revoked between bearer authorization and heartbeat.
    // Never return its agent policy after revocation, even during that race.
    // MySQL reports 0 affected rows when two heartbeats share the same
    // second. Do not confuse an unchanged timestamp with a revoked binding.
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
function device_binding_revoke(int $accountId,int $deviceId):bool {
    $changed=execute("UPDATE device_bindings SET status='revoked',slot_claim=NULL,revoked_at=? WHERE id=? AND account_id=? AND status='active'",
        [now(),$deviceId,$accountId]);
    return $changed===1;
}
function device_pairing_cancel(int $accountId,int $pairingId):bool {
    return execute("UPDATE device_pairings SET status='cancelled' WHERE id=? AND account_id=? AND status IN ('pending','pending_sim0','pending_sim1')",
        [$pairingId,$accountId])===1;
}

/** Device-owner initiated disconnect. Revokes only the authenticated SIM binding. */
function device_binding_disconnect(array $binding):bool {
    return execute("UPDATE device_bindings SET status='revoked',slot_claim=NULL,revoked_at=? WHERE id=? AND account_id=? AND sim_slot=? AND status='active'",
        [now(),$binding['id'],$binding['account_id'],$binding['sim_slot']])===1;
}

/**
 * A physical SIM call-state observation, not a dial authorization or proof of AI audio.
 * Account/site/agent/device are derived exclusively from the authenticated binding.
 * Device timestamps are bounded but not cryptographically attested.
 */
function device_call_event(array $binding,array $payload):array {
    $slot=$payload['sim_slot']??null;
    $deviceId=$payload['device_id']??null;
    $callId=$payload['call_id']??null;
    $phase=$payload['phase']??null;
    $duration=$payload['duration_seconds']??null;
    $occurred=$payload['occurred_at']??null;
    if(!is_int($slot)||$slot!==(int)$binding['sim_slot'] ||
       !is_int($deviceId)||$deviceId!==(int)$binding['id'] ||
       !is_string($callId)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$callId) ||
       !is_string($phase)||!in_array($phase,['ringing','active','ended'],true) ||
       !is_int($duration)||$duration<0||$duration>86400 ||
       ($phase!=='ended' && $duration!==0) ||
       !is_string($occurred)||!preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z$/D',$occurred))
        throw new InvalidArgumentException('device_call_event_invalid');
    $when=strtotime($occurred);
    if($when===false||gmdate('Y-m-d\\TH:i:s\\Z',$when)!==$occurred||
       $when<time()-604800||$when>time()+300)
        throw new InvalidArgumentException('device_call_event_timestamp_invalid');
    // Re-check revocation and account/agent status immediately before any write.
    $current=one("SELECT d.* FROM device_bindings d
        JOIN accounts a ON a.id=d.account_id AND a.status='active'
        JOIN sites s ON s.id=d.site_id AND s.account_id=d.account_id AND s.status='active'
        JOIN agents ag ON ag.id=d.agent_id AND ag.site_id=d.site_id AND ag.account_id=d.account_id AND ag.status!='archived'
        WHERE d.id=? AND d.account_id=? AND d.sim_slot=? AND d.status='active'",
        [$binding['id'],$binding['account_id'],$slot]);
    if(!$current)throw new RuntimeException('device_binding_revoked');
    $args=[(int)$current['id'],$callId,$phase];
    $existing=one('SELECT id FROM tenant_call_events WHERE device_id=? AND call_id=? AND phase=?',$args);
    if($existing)return ['event_id'=>(int)$existing['id'],'duplicate'=>true];
    if($phase==='ended'&&!one("SELECT id FROM tenant_call_events WHERE device_id=? AND call_id=? AND phase IN ('ringing','active') LIMIT 1",
        [(int)$current['id'],$callId]))
        throw new InvalidArgumentException('device_call_event_without_start');
    try {
        $eventId=insert("INSERT INTO tenant_call_events
            (account_id,site_id,agent_id,device_id,sim_slot,call_id,phase,duration_seconds,observed_at)
            VALUES (?,?,?,?,?,?,?,?,?)",[
            $current['account_id'],$current['site_id'],$current['agent_id'],$current['id'],
            $slot,$callId,$phase,$duration,str_replace(['T','Z'],[' ',''],$occurred)
        ]);
    }catch(PDOException $e){
        $duplicate=one('SELECT id FROM tenant_call_events WHERE device_id=? AND call_id=? AND phase=?',$args);
        if(!$duplicate)throw $e;
        return ['event_id'=>(int)$duplicate['id'],'duplicate'=>true];
    }
    return ['event_id'=>$eventId,'duplicate'=>false];
}

/** Read-only last 20 physical call observations for this exact device/SIM binding. */
function device_call_history(array $binding):array {
    // A stale session cannot read a history after device revocation.
    device_binding_heartbeat($binding);
    $rows=all("SELECT call_id,MAX(observed_at) observed_at,
        MAX(duration_seconds) duration_seconds,
        MAX(CASE WHEN phase='ended' THEN 1 ELSE 0 END) ended,
        MAX(CASE WHEN phase='active' THEN 1 ELSE 0 END) active
        FROM tenant_call_events
        WHERE account_id=? AND device_id=? AND sim_slot=?
        GROUP BY call_id ORDER BY MAX(id) DESC LIMIT 20",
        [$binding['account_id'],$binding['id'],$binding['sim_slot']]);
    return array_map(static fn(array $row):array=>[
        'call_id'=>(string)$row['call_id'],
        'observed_at'=>(string)$row['observed_at'],
        'duration_seconds'=>(int)$row['duration_seconds'],
        'state'=>(int)$row['ended']===1
            ?((int)$row['active']===1?'ended':'unanswered')
            :((int)$row['active']===1?'active':'ringing')
    ],$rows);
}
