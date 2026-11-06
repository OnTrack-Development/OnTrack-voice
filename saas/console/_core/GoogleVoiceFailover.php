<?php
declare(strict_types=1);

/**
 * Tenant- and device-bound Live voice failover tickets.
 *
 * A Live WebSocket cannot move to another Google API key. The client must
 * build a NEW Google session with a newly minted ephemeral token and restore
 * the bounded caller/agent transcript explicitly; resumption handles are never
 * reused across keys.
 *
 * No Google master key or ephemeral token is stored in this table.
 */
function google_voice_failover_schema():void {
    $tail=driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS google_voice_live_calls (
        ticket VARCHAR(48) PRIMARY KEY,
        binding_id BIGINT NOT NULL, account_id BIGINT NOT NULL, pool_owner_id BIGINT NOT NULL,
        current_key_id BIGINT NOT NULL,
        agent_id BIGINT NOT NULL, site_id BIGINT NOT NULL, sim_slot INTEGER NOT NULL,
        revision INTEGER NOT NULL, flow_hash VARCHAR(64) NOT NULL,
        generation INTEGER NOT NULL DEFAULT 0, state VARCHAR(12) NOT NULL DEFAULT 'active',
        created_at VARCHAR(19) NOT NULL, expires_at VARCHAR(19) NOT NULL
    )".$tail);
}

/**
 * A ticket is not an authorization grant. Recheck the live SIM after token
 * minting as well as before switching, since an owner can revoke the device
 * or archive the original agent while the upstream request is in flight.
 * Agent reassignment of an otherwise active SIM is allowed mid-call; the
 * immutable original agent/revision remains pinned to the ticket.
 */
function google_voice_failover_assert_live_binding(array $binding,int $originalAgentId):void {
    $live=one("SELECT d.id FROM device_bindings d
        JOIN accounts a ON a.id=d.account_id AND a.status='active'
        JOIN sites s ON s.id=d.site_id AND s.account_id=d.account_id AND s.status='active'
        JOIN agents current_agent ON current_agent.id=d.agent_id
           AND current_agent.account_id=d.account_id AND current_agent.site_id=d.site_id
           AND current_agent.status!='archived'
        JOIN agents original_agent ON original_agent.id=?
           AND original_agent.account_id=d.account_id AND original_agent.site_id=d.site_id
           AND original_agent.status!='archived'
        WHERE d.id=? AND d.account_id=? AND d.site_id=? AND d.sim_slot=?
          AND d.status='active'",[
        $originalAgentId,(int)($binding['id']??0),(int)($binding['account_id']??0),
        (int)($binding['site_id']??0),(int)($binding['sim_slot']??-1)
    ]);
    if(!$live)throw new RuntimeException('voice_failover_binding_revoked');
}

/** An opaque single-call ticket pins the tenant, key pool, SIM and published agent revision. */
function google_voice_failover_register(array $binding,array $definition,array $issued):array {
    $keyId=(int)($issued['entry_id']??0);
    $ownerId=(int)($issued['pool_owner_id']??0);
    if($keyId<1||$ownerId<1)return []; // Legacy platform env key is not rotatable.
    if((int)($binding['account_id']??0)!==(int)$definition['account_id'] ||
       (int)($binding['site_id']??0)!==(int)$definition['site_id'] ||
       (int)($binding['agent_id']??0)!==(int)$definition['agent_id'])
        throw new RuntimeException('voice_failover_binding_mismatch');
    google_voice_failover_assert_live_binding($binding,(int)$definition['agent_id']);
    $key=ai_relay_one($keyId,$ownerId);
    if(!$key||$key['scope']!=='voice'||$key['driver']!=='google_live')
        throw new RuntimeException('voice_failover_pool_mismatch');
    google_voice_failover_schema();
    $ticket=bin2hex(random_bytes(24));
    $minutes=max(1,min(30,(int)($definition['policy']['max_call_minutes']??10)));
    insert('INSERT INTO google_voice_live_calls
        (ticket,binding_id,account_id,pool_owner_id,current_key_id,agent_id,site_id,sim_slot,
         revision,flow_hash,generation,state,created_at,expires_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,0,\'active\',?,?)',[
        $ticket,(int)$binding['id'],(int)$binding['account_id'],$ownerId,$keyId,
        (int)$definition['agent_id'],(int)$definition['site_id'],(int)$binding['sim_slot'],
        (int)$definition['revision'],(string)$definition['flow_hash'],now(),
        gmdate('Y-m-d H:i:s',time()+($minutes+2)*60)
    ]);
    return ['failover_ticket'=>$ticket,'failover_generation'=>0];
}

/**
 * Rotate only for recoverable provider errors. State is claimed atomically
 * BEFORE the external token request, so duplicated WebSocket error callbacks
 * cannot cool multiple keys or open concurrent sessions.
 *
 * $issuer is an internal, injectable PHP test seam; device requests cannot
 * control it. It receives only the account ID.
 */
function google_voice_failover_rotate(array $binding,string $ticket,int $generation,string $code,?callable $issuer=null):array {
    if(!preg_match('/^[a-f0-9]{48}$/D',$ticket) ||
       $generation<0||$generation>=3||
       !in_array($code,['429','503','timeout'],true))
        throw new InvalidArgumentException('voice_failover_invalid_request');
    google_voice_failover_schema();
    if(!tenant_voice_entitled($binding))throw new RuntimeException('voice_subscription_inactive');
    db()->beginTransaction();
    try {
        $row=one('SELECT * FROM google_voice_live_calls WHERE ticket=?',[$ticket]);
        if(!$row ||
           (int)$row['binding_id']!==(int)$binding['id'] ||
           (int)$row['account_id']!==(int)$binding['account_id'] ||
           (int)$row['site_id']!==(int)$binding['site_id'] ||
           (int)$row['sim_slot']!==(int)$binding['sim_slot'] ||
           $row['state']!=='active' || $row['expires_at']<=now() ||
           (int)$row['generation']!==$generation)
            throw new RuntimeException('voice_failover_ticket_invalid_or_stale');
        // A reseller may switch this SIM to another agent mid-call. Keep the
        // ORIGINAL agent/revision and do not re-read a new published prompt.
        $owner=(int)$row['pool_owner_id'];
        require_once __DIR__.'/GoogleTenantKeys.php';
        if(google_voice_pool_owner((int)$binding['account_id'])!==$owner)
            throw new RuntimeException('voice_failover_pool_changed');
        google_voice_failover_assert_live_binding($binding,(int)$row['agent_id']);
        $keyId=(int)$row['current_key_id'];
        $key=ai_relay_one($keyId,$owner);
        if(!$key || $key['scope']!=='voice' || $key['driver']!=='google_live')
            throw new RuntimeException('voice_failover_key_not_owned');
        $changed=execute("UPDATE google_voice_live_calls SET state='switching'
            WHERE ticket=? AND generation=? AND state='active'",[$ticket,$generation]);
        if($changed!==1)throw new RuntimeException('voice_failover_concurrent_request');
        ai_relay_mark($keyId,$code); // 429/503/timeout => 900-second cooldown.
        db()->commit();
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        throw $e;
    }
    try{
        $mint=$issuer??static fn(int $accountId):array=>ai_relay_voice_session(10,$accountId);
        $next=$mint((int)$row['account_id']);
        $newKey=(int)($next['entry_id']??0);
        $newOwner=(int)($next['pool_owner_id']??0);
        if($newKey<1||$newKey===$keyId||$newOwner!==$owner ||
           !preg_match('~^auth_tokens/[A-Za-z0-9_-]{12,512}$~D',(string)($next['token']??'')) ||
           !is_string($next['model']??null))
            throw new RuntimeException('voice_failover_next_key_invalid');
        // The issuer may have returned a key deleted, disabled or cooled by a
        // concurrent admin action. Only an enabled, healthy credential from
        // the original pool may take over the call.
        $entry=ai_relay_one($newKey,$owner);
        if(!$entry || (int)$entry['enabled']!==1 ||
           $entry['scope']!=='voice' || $entry['driver']!=='google_live' ||
           (!empty($entry['cooldown_until']) && $entry['cooldown_until']>now()) ||
           (string)$entry['model']!==$next['model'] ||
           google_voice_pool_owner((int)$row['account_id'])!==$owner)
            throw new RuntimeException('voice_failover_next_key_unavailable');
        google_voice_failover_assert_live_binding($binding,(int)$row['agent_id']);
        $changed=execute("UPDATE google_voice_live_calls
            SET current_key_id=?,generation=?,state='active'
            WHERE ticket=? AND generation=? AND state='switching'",
            [$newKey,$generation+1,$ticket,$generation]);
        if($changed!==1)throw new RuntimeException('voice_failover_concurrent_request');
        return ['token'=>$next['token'],'model'=>$next['model'],
            'failover_ticket'=>$ticket,'failover_generation'=>$generation+1,
            'agent_id'=>(int)$row['agent_id'],'site_id'=>(int)$row['site_id'],
            'sim_slot'=>(int)$row['sim_slot'],'revision'=>(int)$row['revision'],
            'hash'=>(string)$row['flow_hash']];
    }catch(Throwable $e){
        // Never restore the old failed key or auto-retry a partially consumed
        // ticket. A new physical call must request an independent session.
        execute("UPDATE google_voice_live_calls SET state='exhausted'
            WHERE ticket=? AND state='switching'",[$ticket]);
        throw new RuntimeException('voice_failover_no_healthy_keys',0,$e);
    }
}
