<?php
declare(strict_types=1);
require_once __DIR__.'/VoiceAccess.php';

/**
 * Post-call human escalation. The contact list is the only authority for phone
 * numbers; a caller or LLM cannot select a destination or trigger a GSM call.
 *
 * This module SCHEDULES a notification. Only a paired phone can claim its own
 * due job and report the real dialing outcome. No server-side "sent" fiction.
 */
function he_init():void {
    $marker=private_path().'/.schema.human-escalation.v1';
    if(is_file($marker))return;
    $mysql=driver()==='mysql';
    $int=$mysql?'BIGINT':'INTEGER';
    $pk=$mysql?'BIGINT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $names=$mysql?array_column(all('SHOW COLUMNS FROM calls'),'Field'):
        array_column(all('PRAGMA table_info(calls)'),'name');
    if(!in_array('ended_at',$names,true))db()->exec('ALTER TABLE calls ADD COLUMN ended_at VARCHAR(19) NULL');
    if(!in_array('agent_id',$names,true))db()->exec('ALTER TABLE calls ADD COLUMN agent_id BIGINT NULL');
    db()->exec("CREATE TABLE IF NOT EXISTS human_escalation_contacts(
        id $pk,account_id $int NOT NULL,name VARCHAR(140) NOT NULL,
        phone_e164 VARCHAR(20) NOT NULL,recipient_role VARCHAR(20) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at VARCHAR(19) NOT NULL,updated_at VARCHAR(19) NOT NULL,
        UNIQUE(account_id,phone_e164))".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS human_escalation_routes(
        agent_id $int PRIMARY KEY,account_id $int NOT NULL,site_id $int NOT NULL,
        contact_id $int NULL,enabled INTEGER NOT NULL DEFAULT 0,
        updated_at VARCHAR(19) NOT NULL)".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS human_escalation_jobs(
        id $pk,account_id $int NOT NULL,site_id $int NOT NULL,
        device_binding_id $int NOT NULL,sim_slot INTEGER NOT NULL,
        call_id VARCHAR(64) NOT NULL,contact_id $int NOT NULL,
        due_epoch $int NOT NULL,status VARCHAR(24) NOT NULL DEFAULT 'pending',
        leased_epoch $int NULL,attempt_count INTEGER NOT NULL DEFAULT 0,
        dial_at VARCHAR(19) NULL,finished_at VARCHAR(19) NULL,
        result VARCHAR(40) NULL,created_at VARCHAR(19) NOT NULL,
        UNIQUE(device_binding_id,call_id))".$engine);
    if($mysql){
        // Site/tenant scope indexes exist only after all tables are migrated.
        // The unique call+SIM pair provides replay protection.
    }else{
        db()->exec('CREATE INDEX IF NOT EXISTS he_job_ready ON human_escalation_jobs(device_binding_id,status,due_epoch)');
        db()->exec('CREATE INDEX IF NOT EXISTS he_contact_owner ON human_escalation_contacts(account_id,status)');
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}
function he_contact_add(int $accountId,string $name,string $number,string $role):int {
    he_init();
    $name=trim($name);
    if($accountId<1||mb_strlen($name)<2||mb_strlen($name)>140||
       !in_array($role,['manager','employee'],true))
        throw new InvalidArgumentException('بيانات مدير أو موظف غير صالحة');
    $phone=voice_phone_normalize($number);
    $existing=one('SELECT id FROM human_escalation_contacts WHERE account_id=? AND phone_e164=?',
        [$accountId,$phone]);
    if($existing){
        execute("UPDATE human_escalation_contacts SET name=?,recipient_role=?,status='active',updated_at=?
            WHERE id=? AND account_id=?",[$name,$role,now(),$existing['id'],$accountId]);
        return (int)$existing['id'];
    }
    return insert('INSERT INTO human_escalation_contacts
        (account_id,name,phone_e164,recipient_role,status,created_at,updated_at)
        VALUES (?,?,?,? ,?, ?,?)',[$accountId,$name,$phone,$role,'active',now(),now()]);
}
function he_contact_disable(int $accountId,int $contactId):void {
    he_init();
    if(execute("UPDATE human_escalation_contacts SET status='disabled',updated_at=?
        WHERE id=? AND account_id=? AND status='active'",[now(),$contactId,$accountId])!==1)
        throw new InvalidArgumentException('رقم الموظف غير متاح');
    execute('UPDATE human_escalation_routes SET enabled=0,updated_at=?
        WHERE account_id=? AND contact_id=?',[now(),$accountId,$contactId]);
    execute("UPDATE human_escalation_jobs SET status='cancelled',result='recipient_disabled',finished_at=?
        WHERE account_id=? AND contact_id=? AND status='pending'",[now(),$accountId,$contactId]);
}
function he_route_configure(int $accountId,int $agentId,int $contactId,bool $enabled):void {
    he_init();
    $agent=one('SELECT id,site_id FROM agents WHERE id=? AND account_id=? AND status!=?',
        [$agentId,$accountId,'archived']);
    if(!$agent)throw new InvalidArgumentException('اختر مساعدًا تابعًا لشركتك');
    if($enabled){
        $contact=one("SELECT id FROM human_escalation_contacts
            WHERE id=? AND account_id=? AND status='active'",[$contactId,$accountId]);
        if(!$contact)throw new InvalidArgumentException('اختر مديرًا أو موظفًا نشطًا');
    }else{$contactId=$contactId>0?$contactId:0;}
    $previous=one('SELECT agent_id FROM human_escalation_routes WHERE account_id=? AND agent_id=?',
        [$accountId,$agentId]);
    if($previous)
        execute('UPDATE human_escalation_routes SET site_id=?,contact_id=?,enabled=?,updated_at=?
            WHERE account_id=? AND agent_id=?',
            [$agent['site_id'],$contactId?:null,(int)$enabled,now(),$accountId,$agentId]);
    else
        insert('INSERT INTO human_escalation_routes
            (agent_id,account_id,site_id,contact_id,enabled,updated_at) VALUES (?,?,?,?,?,?)',
            [$agentId,$accountId,$agent['site_id'],$contactId?:null,(int)$enabled,now()]);
    // Revoking/changing an agent route must immediately stop any not-yet-claimed
    // callback for that agent; a previously saved queue row is not authorization.
    execute("UPDATE human_escalation_jobs SET status='cancelled',
        result='route_changed',finished_at=?
        WHERE account_id=? AND status='pending'
          AND EXISTS (SELECT 1 FROM calls c WHERE c.account_id=?
            AND c.site_id=human_escalation_jobs.site_id
            AND c.device_binding_id=human_escalation_jobs.device_binding_id
            AND c.external_call_id=human_escalation_jobs.call_id
            AND c.agent_id=?)
          AND (?=0 OR contact_id<>?)",
        [now(),$accountId,$accountId,$agentId,(int)$enabled,$contactId]);
}
/** Select a destination from the agent bound WHEN the call started; no
 * speculative reassignment if the SIM's agent was changed in the meantime.
 */
function he_schedule(array $b,string $callId):array {
    he_init();
    $account=(int)$b['account_id'];$site=(int)$b['site_id'];
    $device=(int)$b['id'];$slot=(int)$b['sim_slot'];
    $row=one("SELECT c.ended_at,c.status,c.agent_id,r.handoff_status,r.handoff_reason,
            r.source,ar.contact_id,ar.enabled,co.status recipient_status
        FROM calls c
        JOIN tenant_call_reports r ON r.account_id=c.account_id
          AND r.site_id=c.site_id AND r.device_binding_id=c.device_binding_id
          AND r.call_id=c.external_call_id
        LEFT JOIN human_escalation_routes ar ON ar.agent_id=c.agent_id
          AND ar.account_id=c.account_id AND ar.site_id=c.site_id
        LEFT JOIN human_escalation_contacts co ON co.id=ar.contact_id
          AND co.account_id=c.account_id
        WHERE c.account_id=? AND c.site_id=? AND c.device_binding_id=?
          AND c.sim_slot=? AND c.external_call_id=?",
        [$account,$site,$device,$slot,$callId]);
    if(!$row)return ['queued'=>false,'reason'=>'no_eligible_report'];
    if($row['status']!=='ended'||!$row['ended_at'])
        return ['queued'=>false,'reason'=>'call_still_open'];
    if($row['handoff_status']!=='requested')
        return ['queued'=>false,'reason'=>'no_human_request'];
    if($row['source']==='device_handoff')
        return ['queued'=>false,'reason'=>'handset_takeover_not_customer_request'];
    if((int)($row['enabled']??0)!==1||!$row['contact_id']||$row['recipient_status']!=='active')
        return ['queued'=>false,'reason'=>'staff_routing_not_configured'];
    $existing=one('SELECT id,status FROM human_escalation_jobs WHERE device_binding_id=? AND call_id=?',
        [$device,$callId]);
    if($existing)return ['queued'=>true,'duplicate'=>true,'status'=>$existing['status']];
    $ended=strtotime((string)$row['ended_at'].' UTC');
    $due=max(time(),($ended?:time())+10);
    try{
        $id=insert("INSERT INTO human_escalation_jobs
            (account_id,site_id,device_binding_id,sim_slot,call_id,contact_id,
             due_epoch,status,created_at) VALUES (?,?,?,?,?,?,?,'pending',?)",
            [$account,$site,$device,$slot,$callId,(int)$row['contact_id'],$due,now()]);
    }catch(PDOException $error){
        if(!one('SELECT id FROM human_escalation_jobs WHERE device_binding_id=? AND call_id=?',
            [$device,$callId]))throw $error;
        return ['queued'=>true,'duplicate'=>true];
    }
    return ['queued'=>true,'duplicate'=>false,'id'=>$id,'ready_after_seconds'=>max(0,$due-time())];
}
function he_briefing(array $b,string $callId):?array {
    return one("SELECT c.phone,c.name,c.status,r.summary,r.customer_notes,
        r.handoff_reason,r.outcome
        FROM calls c JOIN tenant_call_reports r ON r.account_id=c.account_id
         AND r.site_id=c.site_id AND r.device_binding_id=c.device_binding_id
         AND r.call_id=c.external_call_id
        WHERE c.account_id=? AND c.site_id=? AND c.device_binding_id=?
         AND c.sim_slot=? AND c.external_call_id=?",
        [$b['account_id'],$b['site_id'],$b['id'],$b['sim_slot'],$callId]);
}
/** Poll only while the exact paired SIM is physically idle. A lease is a
 * ONE-SHOT command: if confirmation is uncertain it must never auto-redial.
 */
function he_claim(array $binding):array {
    he_init();
    $b=$binding;$account=(int)$b['account_id'];$device=(int)$b['id'];
    // A summary can arrive after the call's terminal event. Recover the queue
    // idempotently on subsequent authenticated idle polling.
    $eligible=all("SELECT c.external_call_id FROM calls c
        JOIN tenant_call_reports r ON r.call_id=c.external_call_id
         AND r.account_id=c.account_id AND r.site_id=c.site_id
         AND r.device_binding_id=c.device_binding_id
        WHERE c.account_id=? AND c.site_id=? AND c.device_binding_id=?
        AND c.sim_slot=? AND c.status='ended' AND r.handoff_status='requested'
        AND r.source!='device_handoff'
        ORDER BY c.id DESC LIMIT 20",
        [$account,$b['site_id'],$device,$b['sim_slot']]);
    foreach($eligible as $rec)he_schedule($b,(string)$rec['external_call_id']);
    $prev=one("SELECT id,status,leased_epoch FROM human_escalation_jobs
        WHERE account_id=? AND device_binding_id=? AND sim_slot=?
          AND status='claimed' ORDER BY id DESC LIMIT 1",
        [$account,$device,$b['sim_slot']]);
    if($prev){
        if(time()-(int)$prev['leased_epoch']>90)
            execute("UPDATE human_escalation_jobs SET status='unknown',result='claim_expired',
                finished_at=? WHERE id=? AND status='claimed'",[now(),$prev['id']]);
        else return ['job'=>null,'reason'=>'active_claim'];
    }
    $job=one("SELECT j.*,co.name recipient_name,co.phone_e164
        FROM human_escalation_jobs j
        JOIN human_escalation_contacts co ON co.id=j.contact_id
         AND co.account_id=j.account_id AND co.status='active'
        JOIN device_bindings d ON d.id=j.device_binding_id
         AND d.account_id=j.account_id AND d.sim_slot=j.sim_slot AND d.status='active'
        JOIN calls c ON c.account_id=j.account_id AND c.site_id=j.site_id
         AND c.device_binding_id=j.device_binding_id AND c.sim_slot=j.sim_slot
         AND c.external_call_id=j.call_id AND c.status='ended'
        JOIN human_escalation_routes ar ON ar.account_id=j.account_id
         AND ar.site_id=j.site_id AND ar.agent_id=c.agent_id
         AND ar.contact_id=j.contact_id AND ar.enabled=1
        JOIN tenant_call_reports r ON r.account_id=j.account_id AND r.site_id=j.site_id
         AND r.device_binding_id=j.device_binding_id AND r.call_id=j.call_id
         AND r.handoff_status='requested'
        WHERE j.account_id=? AND j.site_id=? AND j.device_binding_id=?
          AND j.sim_slot=? AND j.status='pending' AND j.due_epoch<=?
        ORDER BY j.id ASC LIMIT 1",
        [$account,$b['site_id'],$device,$b['sim_slot'],time()]);
    if(!$job)return ['job'=>null,'reason'=>'no_due_notification'];
    if(execute("UPDATE human_escalation_jobs SET status='claimed',leased_epoch=?,attempt_count=attempt_count+1
        WHERE id=? AND account_id=? AND device_binding_id=? AND status='pending'",
        [time(),$job['id'],$account,$device])!==1)
        return ['job'=>null,'reason'=>'claim_race'];
    $report=he_briefing($b,(string)$job['call_id']);
    $reason=trim((string)($report['handoff_reason']??''));
    $summary=trim((string)($report['summary']??''));
    // Never invent a call summary. Lack of an AI report is explicit.
    if($summary==='')$summary='لا يوجد ملخص صوتي موثّق حتى الآن؛ راجع سجل المكالمة.';
    return ['job'=>[
        'id'=>(int)$job['id'],'call_id'=>(string)$job['call_id'],
        'name'=>(string)$job['recipient_name'],'phone'=>(string)$job['phone_e164'],
        'customer_name'=>(string)($report['name']?:'عميل غير معروف'),
        'customer_phone'=>(string)($report['phone']?:'رقم غير متاح'),
        'reason'=>mb_substr($reason,0,900),
        'summary'=>mb_substr($summary,0,1200),
        'sim_slot'=>(int)$b['sim_slot'],
        'expires_in_seconds'=>30]];
}
function he_ack(array $b,int $jobId,string $stage):array {
    he_init();
    if(!in_array($stage,['dial_started','failed','call_ended_unverified'],true))
        throw new InvalidArgumentException('invalid_manager_notification_stage');
    $job=one("SELECT status FROM human_escalation_jobs WHERE id=? AND account_id=?
        AND site_id=? AND device_binding_id=? AND sim_slot=?",
        [$jobId,$b['account_id'],$b['site_id'],$b['id'],$b['sim_slot']]);
    if(!$job)throw new InvalidArgumentException('manager_notification_not_found');
    $state=$stage==='dial_started'?'dial_started':($stage==='failed'?'failed':'call_ended_unverified');
    if($job['status']===$state)return ['saved'=>true,'duplicate'=>true,'status'=>$state];
    if($job['status']!=='claimed' &&
       !(in_array($stage,['call_ended_unverified','failed'],true)&&$job['status']==='dial_started'))
        throw new InvalidArgumentException('manager_notification_not_claimed');
    if($stage==='call_ended_unverified'&&$job['status']!=='dial_started')
        throw new InvalidArgumentException('cannot_finish_undialed_manager_call');
    execute("UPDATE human_escalation_jobs
        SET status=?,result=?,dial_at=CASE WHEN ?='dial_started' THEN ? ELSE dial_at END,
            finished_at=CASE WHEN ?='dial_started' THEN NULL ELSE ? END
        WHERE id=? AND account_id=? AND device_binding_id=? AND status=?",
        [$state,$stage,$state,now(),$state,now(),$jobId,$b['account_id'],$b['id'],$job['status']]);
    return ['saved'=>true,'duplicate'=>false,'status'=>$state];
}
