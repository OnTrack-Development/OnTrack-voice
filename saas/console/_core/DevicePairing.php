<?php
declare(strict_types=1);

/**
 * Multi-tenant Android SIM enrollment. This MUST NOT reuse the old pilot bearer.
 * Pairing code: 96 bits, expires in 10 minutes, one-time, stored as SHA-256.
 * Each installation/physical SIM slot can have at most one active binding.
 */
function device_pairing_create(int $accountId,int $siteId,int $agentId,int $userId):string {
    $account=account($accountId);
    $site=one("SELECT id FROM sites WHERE id=? AND account_id=? AND status='active'",[$siteId,$accountId]);
    $agent=one("SELECT id FROM agents WHERE id=? AND account_id=? AND site_id=? AND status!='archived'",[$agentId,$accountId,$siteId]);
    if(!$account||$account['status']!=='active'||!$site||!$agent)
        throw new InvalidArgumentException('device_invalid_account_site_agent');
    $pending=(int)one("SELECT COUNT(*) n FROM device_pairings WHERE account_id=? AND status='pending' AND expires_at>?",[$accountId,now()])['n'];
    if($pending>=5)throw new InvalidArgumentException('device_pending_limit');
    $raw=bin2hex(random_bytes(12));
    insert("INSERT INTO device_pairings(account_id,site_id,agent_id,code_hash,status,expires_at,created_by,created_at)
       VALUES (?,?,?,?,'pending',?,?,?)",
       [$accountId,$siteId,$agentId,hash('sha256',$raw),gmdate('Y-m-d H:i:s',time()+600),$userId,now()]);
    return strtoupper(implode('-',str_split($raw,4)));
}
function device_pairing_redeem(string $code,string $installationId,int $simSlot,string $label):array {
    $clean=strtolower(str_replace('-','',trim($code)));
    if(!preg_match('/^[a-f0-9]{24}$/D',$clean)
        || !preg_match('/^[a-zA-Z0-9_.:-]{12,128}$/D',$installationId)
        || !in_array($simSlot,[0,1],true)
        || mb_strlen($label)>100 || $label==='')
        throw new InvalidArgumentException('device_pairing_invalid');
    $token='otdev_'.bin2hex(random_bytes(32));
    $claim=hash('sha256',$installationId.'|'.$simSlot);
    db()->beginTransaction();
    try{
        $pair=one("SELECT * FROM device_pairings WHERE code_hash=? AND status='pending' AND expires_at>?",[hash('sha256',$clean),now()]);
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
        $changed=execute("UPDATE device_pairings SET status='redeemed',device_id=?,redeemed_at=? WHERE id=? AND status='pending' AND expires_at>?",
            [$deviceId,now(),$pair['id'],now()]);
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
    if(!$agent||!$site)throw new RuntimeException('device_profile_unavailable');
    return ['device_id'=>(int)$binding['id'],'account_id'=>(int)$binding['account_id'],
        'site_id'=>(int)$site['id'],'site_name'=>$site['name'],
        'agent_id'=>(int)$agent['id'],'agent_name'=>$agent['name'],
        'voice'=>$agent['voice'],'instruction'=>$agent['instructions'],
        'sim_slot'=>(int)$binding['sim_slot'],'heartbeat_seconds'=>10,
        'transport'=>'tenant_pairing_preview_no_sim_bridge'];
}
function device_binding_heartbeat(array $binding):array {
    execute('UPDATE device_bindings SET last_seen_at=? WHERE id=? AND status=?',[now(),$binding['id'],'active']);
    return device_binding_profile($binding);
}
function device_binding_revoke(int $accountId,int $deviceId):bool {
    $changed=execute("UPDATE device_bindings SET status='revoked',slot_claim=NULL,revoked_at=? WHERE id=? AND account_id=? AND status='active'",
        [now(),$deviceId,$accountId]);
    return $changed===1;
}
function device_pairing_cancel(int $accountId,int $pairingId):bool {
    return execute("UPDATE device_pairings SET status='cancelled' WHERE id=? AND account_id=? AND status='pending'",
        [$pairingId,$accountId])===1;
}

/** Device-owner initiated disconnect. Revokes only the authenticated SIM binding. */
function device_binding_disconnect(array $binding):bool {
    return execute("UPDATE device_bindings SET status='revoked',slot_claim=NULL,revoked_at=? WHERE id=? AND account_id=? AND sim_slot=? AND status='active'",
        [now(),$binding['id'],$binding['account_id'],$binding['sim_slot']])===1;
}
