<?php
declare(strict_types=1);
/**
 * Server-authorized, per-SIM campaign lease queue. NOT a dialer.
 * A lease and a verified preflight do NOT initiate or authorize hidden calls
 * without an explicit owner campaign opt-in and a native device executor.
 */
function tcq_init():void{
    $mark=private_path().'/.schema.tenant-campaign-queue.v1';
    if(is_file($mark))return;
    $mysql=driver()==='mysql';$int=$mysql?'BIGINT':'INTEGER';
    $pk=$mysql?'BIGINT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    db()->exec("CREATE TABLE IF NOT EXISTS tenant_campaign_consents (
      id $pk,account_id $int NOT NULL,site_id $int NOT NULL,contact_id $int NOT NULL,
      phone_key VARCHAR(45) NOT NULL,method VARCHAR(24) NOT NULL,proof_hash CHAR(64) NOT NULL,
      created_at VARCHAR(19) NOT NULL,UNIQUE(account_id,site_id,contact_id)
    )".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS tenant_campaign_attempts (
      id CHAR(32) PRIMARY KEY,campaign_id CHAR(24) NOT NULL,
      account_id $int NOT NULL,site_id $int NOT NULL,device_binding_id $int NOT NULL,
      sim_slot INTEGER NOT NULL,contact_id $int NOT NULL,phone_key VARCHAR(45) NOT NULL,
      status VARCHAR(24) NOT NULL,leased_epoch $int NOT NULL,verified_epoch $int NULL,
      result VARCHAR(24) NULL,finished_at VARCHAR(19) NULL,UNIQUE(campaign_id,contact_id)
    )".$engine);
    file_put_contents($mark,now(),LOCK_EX);@chmod($mark,0600);
}
function tcq_enabled():bool{return getenv('ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED')==='1';}
function tcq_utc(string $local):string{
    if(!preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$/D',$local))
        throw new InvalidArgumentException('invalid_campaign_schedule');
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$local,new DateTimeZone('Africa/Cairo'));
    if(!$dt||$dt->format('Y-m-d\TH:i')!==$local)throw new InvalidArgumentException('invalid_campaign_schedule');
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
function tcq_window(array $campaign,int $epoch):bool{
    $from=(string)$campaign['starts_at'];$to=(string)$campaign['ends_at'];
    if(str_contains($from,'T'))$from=tcq_utc($from);
    if(str_contains($to,'T'))$to=tcq_utc($to);
    $current=gmdate('Y-m-d H:i:s',$epoch);
    return $current>=$from&&$current<$to;
}
function tcq_contact(array $binding,int $contactId):?array{
    return one('SELECT id,name,phone,dnc FROM contacts WHERE id=? AND account_id=? AND site_id=?',[
       $contactId,(int)$binding['account_id'],(int)$binding['site_id']]);
}
function tcq_consent(array $binding,string $opaque,string $method,string $evidence):array{
    tcq_init();$contactId=tenant_workspace_parse_id($opaque);
    $row=tcq_contact($binding,$contactId);
    if(!$row)throw new InvalidArgumentException('contact_unavailable');
    if(!in_array($method,['verbal','form','written','owner_test'],true)||
        mb_strlen(trim($evidence))<8||mb_strlen($evidence)>240)
        throw new InvalidArgumentException('consent_proof_required');
    $account=(int)$binding['account_id'];$site=(int)$binding['site_id'];
    if((int)$row['dnc']===1||dnc_is_suppressed($account,(string)$row['phone']))
        throw new InvalidArgumentException('dnc_cannot_be_overridden');
    $phoneKey=dnc_phone_key((string)$row['phone']);
    if(strlen($phoneKey)<8)throw new InvalidArgumentException('consent_phone_invalid');
    $hash=hash('sha256',$method.':'.trim($evidence));
    $existing=one('SELECT id FROM tenant_campaign_consents WHERE account_id=? AND site_id=? AND contact_id=?',
        [$account,$site,$contactId]);
    if($existing)execute('UPDATE tenant_campaign_consents SET phone_key=?,method=?,proof_hash=?,created_at=? WHERE id=?',
        [$phoneKey,$method,$hash,now(),$existing['id']]);
    else insert('INSERT INTO tenant_campaign_consents(account_id,site_id,contact_id,phone_key,method,proof_hash,created_at)
        VALUES(?,?,?,?,?,?,?)',[$account,$site,$contactId,$phoneKey,$method,$hash,now()]);
    return ['recorded'=>true,'consent_kind'=>'self_attested','contact_id'=>$opaque];
}
function tcq_allowed(array $b,int $contactId):bool{
    tcq_init();$r=tcq_contact($b,$contactId);
    if(!$r||(int)$r['dnc']===1)return false;
    $key=dnc_phone_key((string)$r['phone']);
    $consent=one('SELECT phone_key FROM tenant_campaign_consents WHERE account_id=? AND site_id=? AND contact_id=?',
        [(int)$b['account_id'],(int)$b['site_id'],$contactId]);
    return $consent!==null && hash_equals((string)$consent['phone_key'],$key) &&
        !dnc_is_suppressed((int)$b['account_id'],(string)$r['phone']);
}
/** Empty contact selection means every contact in this exact tenant/site,
 * never across another site's CRM. Bounded to 2k as current import contract.
 * Larger batches need streaming pagination before the commercial rollout. */
function tcq_targets(array $b,array $campaign):array {
    $list=json_decode((string)$campaign['contact_ids_json'],true);
    if(!is_array($list)||!array_is_list($list))throw new InvalidArgumentException('invalid_campaign_targets');
    if(count($list)>2000)throw new InvalidArgumentException('campaign_targets_limit');
    if($list!==[])return $list;
    $rows=all('SELECT id FROM contacts WHERE account_id=? AND site_id=? ORDER BY id ASC LIMIT 2000',
        [$b['account_id'],$b['site_id']]);
    return array_map(static fn($row)=>tenant_workspace_id((int)$row['id']),$rows);
}
function tcq_preview(array $b,array $campaign):array{
    tcq_init();$list=tcq_targets($b,$campaign);
    $eligible=[];$excluded=0;$seen=[];
    foreach($list as $opaque){
        try{$id=tenant_workspace_parse_id($opaque);}catch(InvalidArgumentException){$excluded++;continue;}
        if(isset($seen[$id])){$excluded++;continue;}
        $seen[$id]=true;
        if(tcq_allowed($b,$id))$eligible[]=['id'=>$opaque];else $excluded++;
    }
    return ['eligible'=>$eligible,'excluded'=>$excluded,'dialed'=>false,'dispatch_enabled'=>tcq_enabled()];
}
function tcq_arm(array $b,array $campaign):array{
    if(!in_array($campaign['status'],['draft','paused'],true))
        throw new InvalidArgumentException('campaign_transition_denied');
    $end=(string)$campaign['ends_at'];
    if(str_contains($end,'T'))$end=tcq_utc($end);
    if($end<=gmdate('Y-m-d H:i:s'))throw new InvalidArgumentException('campaign_expired');
    $preview=tcq_preview($b,$campaign);
    if(!$preview['eligible'])throw new InvalidArgumentException('no_consented_contacts');
    // Guard against a stale browser form resurrecting a paused/deleted campaign.
    if(execute("UPDATE tenant_campaigns SET status='ready'
      WHERE id=? AND account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=?
        AND status IN ('draft','paused')",
      [$campaign['id'],$b['account_id'],$b['site_id'],$b['id'],$b['sim_slot']])!==1)
        throw new RuntimeException('campaign_status_changed');
    return ['campaign'=>['id'=>$campaign['id'],'status'=>'ready'],
        'calls_started'=>0,'dispatch_enabled'=>tcq_enabled()];
}
function tcq_attempt(array $b,string $id):?array{
    if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('invalid_attempt_id');
    tcq_init();
    return one('SELECT * FROM tenant_campaign_attempts
      WHERE id=? AND account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=?',
      [$id,$b['account_id'],$b['site_id'],$b['id'],$b['sim_slot']]);
}
function tcq_claim(array $b):array{
    tcq_init();
    if(!tcq_enabled())throw new RuntimeException('campaign_device_e2e_gate_disabled');
    $db=db();$db->beginTransaction();
    try{
        // InnoDB must serialize claims for this physical SIM before selecting
        // eligible campaigns; a unique contact constraint alone is not enough.
        if(driver()==='mysql'){
            $owner=one("SELECT id FROM device_bindings
                WHERE id=? AND account_id=? AND site_id=? AND sim_slot=?
                  AND status='active' FOR UPDATE",
                [$b['id'],$b['account_id'],$b['site_id'],$b['sim_slot']]);
            if(!$owner)throw new RuntimeException('campaign_device_revoked');
        }
        $active=all("SELECT id,leased_epoch FROM tenant_campaign_attempts WHERE
          account_id=? AND device_binding_id=? AND sim_slot=? AND status IN ('leased','verified')",
          [$b['account_id'],$b['id'],$b['sim_slot']]);
        foreach($active as $row){
            if(time()-(int)$row['leased_epoch']<=60){
                $db->commit();return ['claim'=>null,'reason'=>'active_lease','dial_requested'=>false];
            }
            // Do not proceed to ANY number until the physical SIM state and
            // uncertain attempt have been reconciled by an authorized actor.
            execute("UPDATE tenant_campaign_attempts SET status='unknown',result='expired',
                finished_at=? WHERE id=? AND status IN ('leased','verified')",[now(),$row['id']]);
        }
        $uncertain=one("SELECT id FROM tenant_campaign_attempts WHERE
            account_id=? AND device_binding_id=? AND sim_slot=? AND status='unknown' LIMIT 1",
            [$b['account_id'],$b['id'],$b['sim_slot']]);
        if($uncertain){
            $db->commit();return ['claim'=>null,'reason'=>'uncertain_call_requires_review',
                'dial_requested'=>false];
        }
        $campaigns=all("SELECT * FROM tenant_campaigns WHERE account_id=? AND
            site_id=? AND device_binding_id=? AND sim_slot=? AND status='ready'
            ORDER BY created_at,id LIMIT 20",
            [$b['account_id'],$b['site_id'],$b['id'],$b['sim_slot']]);
        foreach($campaigns as $campaign){
            if(!tcq_window($campaign,time()))continue;
            foreach(tcq_targets($b,$campaign) as $opaque){
                try{$contactId=tenant_workspace_parse_id($opaque);}catch(InvalidArgumentException){continue;}
                if(one('SELECT id FROM tenant_campaign_attempts WHERE campaign_id=? AND contact_id=?',
                    [$campaign['id'],$contactId]))continue;
                if(!tcq_allowed($b,$contactId))continue;
                $contact=tcq_contact($b,$contactId);
                if(!$contact)continue;
                $id=bin2hex(random_bytes(16));
                insert('INSERT INTO tenant_campaign_attempts
                    (id,campaign_id,account_id,site_id,device_binding_id,sim_slot,contact_id,
                     phone_key,status,leased_epoch) VALUES (?,?,?,?,?,?,?,?,?,?)',[
                    $id,$campaign['id'],$b['account_id'],$b['site_id'],$b['id'],$b['sim_slot'],
                    $contactId,dnc_phone_key((string)$contact['phone']),'leased',time()
                ]);
                $db->commit();
                return ['claim'=>['id'=>$id,'campaign_id'=>$campaign['id'],
                    'contact_id'=>$opaque,'name'=>$contact['name'],'phone'=>$contact['phone'],
                    'scenario'=>$campaign['scenario'],'expires_in_seconds'=>30],
                    'dial_requested'=>false];
            }
        }
        $db->commit();return ['claim'=>null,'dial_requested'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function tcq_verify(array $b,string $attemptId):array{
    if(!tcq_enabled())return ['allowed'=>false,'reason'=>'device_e2e_gate_disabled'];
    $a=tcq_attempt($b,$attemptId);
    if(!$a||$a['status']!=='leased')return ['allowed'=>false,'reason'=>'lease_not_active'];
    // A device may be revoked after HTTP bearer authentication but before
    // the final pre-dial confirmation. Stale requests must fail closed.
    $physical=one("SELECT id FROM device_bindings WHERE id=? AND account_id=?
        AND site_id=? AND sim_slot=? AND status='active'",[
        $b['id'],$b['account_id'],$b['site_id'],$b['sim_slot']]);
    if(!$physical)return ['allowed'=>false,'reason'=>'device_revoked_or_unverified'];
    if(time()-(int)$a['leased_epoch']>30)return ['allowed'=>false,'reason'=>'lease_expired'];
    $campaign=one('SELECT * FROM tenant_campaigns WHERE id=? AND account_id=? AND site_id=?
      AND device_binding_id=? AND sim_slot=?',
      [$a['campaign_id'],$b['account_id'],$b['site_id'],$b['id'],$b['sim_slot']]);
    if(!$campaign||$campaign['status']!=='ready'||!tcq_window($campaign,time()))
        return ['allowed'=>false,'reason'=>'campaign_paused_or_outside_hours'];
    if(!tcq_allowed($b,(int)$a['contact_id']))
        return ['allowed'=>false,'reason'=>'consent_or_dnc_changed'];
    $contact=tcq_contact($b,(int)$a['contact_id']);
    if(!$contact||!hash_equals((string)$a['phone_key'],dnc_phone_key((string)$contact['phone'])))
        return ['allowed'=>false,'reason'=>'number_changed'];
    if(execute("UPDATE tenant_campaign_attempts SET status='verified',verified_epoch=?
         WHERE id=? AND status='leased'",[time(),$attemptId])!==1)
        return ['allowed'=>false,'reason'=>'already_verified'];
    return ['allowed'=>true,'reason'=>'ok','must_dial_within_seconds'=>10,
            'sim_slot'=>(int)$b['sim_slot']];
}
function tcq_finish(array $b,string $attemptId,string $result):array{
    if(!in_array($result,['completed','failed','busy','no_answer','unknown'],true))
        throw new InvalidArgumentException('invalid_attempt_result');
    $a=tcq_attempt($b,$attemptId);
    if(!$a)throw new InvalidArgumentException('attempt_unavailable');
    if(in_array($a['status'],['finished','unknown'],true)){
        if($a['result']===$result)return ['saved'=>true,'duplicate'=>true,'result'=>$result];
        throw new InvalidArgumentException('attempt_already_finished');
    }
    if(!in_array($a['status'],['leased','verified'],true))
        throw new InvalidArgumentException('attempt_not_active');
    // A lease can fail/expire before Android verified eligibility. Never
    // accept a fabricated "completed" or "no_answer" for a bare lease.
    if($a['status']!=='verified' && in_array($result,['completed','busy','no_answer'],true))
        throw new InvalidArgumentException('campaign_result_requires_verified_dial');
    $stored=$result==='unknown'?'unknown':'finished';
    $updated=execute("UPDATE tenant_campaign_attempts SET status=?,result=?,finished_at=?
      WHERE id=? AND account_id=? AND device_binding_id=? AND status IN ('leased','verified')",
      [$stored,$result,now(),$attemptId,$b['account_id'],$b['id']]);
    if($updated!==1){
        // Another worker may have finalized this attempt after our scoped read.
        $current=tcq_attempt($b,$attemptId);
        if($current && in_array($current['status'],['finished','unknown'],true)
           && $current['result']===$result)
            return ['saved'=>true,'duplicate'=>true,'result'=>$result];
        throw new RuntimeException('campaign_attempt_state_changed');
    }
    return ['saved'=>true,'duplicate'=>false,'result'=>$result];
}
