<?php
declare(strict_types=1);
/**
 * Durable, account-scoped, LOCAL event consumer for the legacy outbox.
 * This is not an external WHMCS callback. Never mislabel a local receipt as
 * third-party delivery, and never send an unapproved outbound request.
 */
function operational_outbox_init():void{
    $engine=driver()==='mysql'
        ?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS operational_event_receipts(
        outbox_id BIGINT PRIMARY KEY,
        account_id BIGINT NOT NULL,
        integration_id BIGINT NULL,
        kind VARCHAR(80) NOT NULL,
        payload_hash CHAR(64) NOT NULL,
        accepted_at VARCHAR(19) NOT NULL
    )".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS operational_outbox_leases(
        outbox_id BIGINT PRIMARY KEY,
        claimed_at VARCHAR(19) NOT NULL
    )".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS operational_worker_heartbeat(
        worker VARCHAR(70) PRIMARY KEY,
        last_run_at VARCHAR(19) NOT NULL,
        last_success_at VARCHAR(19) NULL,
        scanned BIGINT NOT NULL DEFAULT 0,
        delivered BIGINT NOT NULL DEFAULT 0,
        failed BIGINT NOT NULL DEFAULT 0
    )".$engine);
}
function operational_worker_beat(string $worker,int $seen,int $done,int $failed):void{
    operational_outbox_init();
    if(!preg_match('/^[a-z0-9_-]{2,70}$/D',$worker))
        throw new InvalidArgumentException('worker_name_invalid');
    $v=one('SELECT worker FROM operational_worker_heartbeat WHERE worker=?',[$worker]);
    if($v){
        execute('UPDATE operational_worker_heartbeat
            SET last_run_at=?,last_success_at=?,scanned=?,delivered=?,failed=? WHERE worker=?',
            [now(),$failed===0?now():null,$seen,$done,$failed,$worker]);
    }else{
        insert('INSERT INTO operational_worker_heartbeat
            (worker,last_run_at,last_success_at,scanned,delivered,failed)
            VALUES (?,?,?,?,?,?)',[
            $worker,now(),$failed===0?now():null,$seen,$done,$failed
        ]);
    }
}
function operational_outbox_recover(int $timeout=180):int{
    operational_outbox_init();
    $before=gmdate('Y-m-d H:i:s',time()-max(120,min(86400,$timeout)));
    // No irreversible external I/O is performed in this consumer, so a stale
    // processing state is safe to retry. Never retry more than five times.
    // MySQL prohibits updating a table selected from itself in a nested
    // subquery. Fetch only bounded, expired lease IDs and CAS-update rows.
    $stale=all("SELECT o.id,o.attempts FROM outbox o
        LEFT JOIN operational_outbox_leases l ON l.outbox_id=o.id
        WHERE o.status='processing' AND COALESCE(l.claimed_at,o.created_at)<?
        ORDER BY o.id LIMIT 150",[$before]);
    $recovered=0;
    foreach($stale as $job){
        $next=(int)$job['attempts']>=5?'failed':'pending';
        $recovered+=execute("UPDATE outbox SET status=?
            WHERE id=? AND status='processing' AND attempts=?",[
            $next,(int)$job['id'],(int)$job['attempts']]);
    }
    return $recovered;
}
function operational_outbox_process(int $id):array{
    operational_outbox_init();
    $row=one("SELECT * FROM outbox WHERE id=? AND status='pending'",[$id]);
    if(!$row)return ['status'=>'not_pending'];
    if(execute("UPDATE outbox SET status='processing',attempts=attempts+1
        WHERE id=? AND status='pending' AND attempts<5",[$id])!==1)
        return ['status'=>'already_claimed_or_limit'];
    try{
        // A fresh claim timestamp prevents old pending jobs from being
        // incorrectly recovered during an active, newly claimed attempt.
        $lease=one('SELECT outbox_id FROM operational_outbox_leases WHERE outbox_id=?',[$id]);
        if($lease)execute('UPDATE operational_outbox_leases SET claimed_at=? WHERE outbox_id=?',
            [now(),$id]);
        else insert('INSERT INTO operational_outbox_leases(outbox_id,claimed_at) VALUES (?,?)',
            [$id,now()]);
        db()->beginTransaction();
        // SELECT row lock is necessary to protect concurrent concurrent
        // consumers and ensure the status transition isn't interrupted.
        $sql='SELECT * FROM outbox WHERE id=?';
        if(driver()==='mysql')$sql.=' FOR UPDATE';
        $current=one($sql,[$id]);
        if(!$current||$current['status']!=='processing')
            throw new RuntimeException('outbox_claim_lost');
        $kind=(string)$current['kind'];
        $accountId=(int)$current['account_id'];
        if(!preg_match('/^[a-z][a-z0-9_.:-]{2,79}$/D',$kind)||$accountId<1)
            throw new InvalidArgumentException('outbox_record_invalid');
        if(!account($accountId))throw new InvalidArgumentException('outbox_account_missing');
        $json=(string)$current['payload'];
        if(strlen($json)>65000 || !is_array(json_decode($json,true)))
            throw new InvalidArgumentException('outbox_json_invalid');
        $sha=hash('sha256',$json);
        $prior=one('SELECT account_id,kind,payload_hash FROM operational_event_receipts
            WHERE outbox_id=?',[$id]);
        if($prior && ((int)$prior['account_id']!==$accountId||
            $prior['kind']!==$kind||!hash_equals($prior['payload_hash'],$sha)))
            throw new RuntimeException('outbox_receipt_integrity_conflict');
        if(!$prior)insert('INSERT INTO operational_event_receipts
            (outbox_id,account_id,integration_id,kind,payload_hash,accepted_at)
            VALUES (?,?,?,?,?,?)',[
            $id,$accountId,$current['integration_id'],$kind,$sha,now()]);
        if(execute("UPDATE outbox SET status='delivered_local'
            WHERE id=? AND status='processing'",[$id])!==1)
            throw new RuntimeException('outbox_state_race');
        db()->commit();
        return ['status'=>'delivered_local','outbox_id'=>$id];
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        execute("UPDATE outbox SET status=? WHERE id=? AND status='processing'",[
            (int)$row['attempts']>=4?'failed':'pending',$id]);
        return ['status'=>'failed','code'=>get_class($e)];
    }
}
function operational_outbox_batch(int $limit=60):array{
    operational_outbox_init();
    $recovered=operational_outbox_recover();
    $limit=max(1,min(200,$limit));
    $jobs=all("SELECT id FROM outbox WHERE status='pending' AND attempts<5
        ORDER BY id ASC LIMIT $limit");
    $counts=['scanned'=>count($jobs),'delivered'=>0,'failed'=>0,'recovered'=>$recovered];
    foreach($jobs as $job){
        $out=operational_outbox_process((int)$job['id']);
        if($out['status']==='delivered_local')$counts['delivered']++;
        else $counts['failed']++;
    }
    operational_worker_beat('operational_outbox',$counts['scanned'],$counts['delivered'],$counts['failed']);
    return $counts;
}
/** Pure worker freshness check, safe for fixtures and production read-only UI.
 * A heartbeat means the process ran, not that Meta/SMTP delivery succeeded.
 * Timestamps are server UTC. Missing and stale workers must never look green.
 */
function operational_workers_assess(array $rows,?int $epoch=null):array{
    $epoch=$epoch??time();
    $expected=[
        'operational_outbox'=>300,
        'operational_email'=>300,
        'flow_ai'=>300,
        'whatsapp_ai'=>300,
        'knowledge_sync'=>900
    ];
    $indexed=[];
    foreach($rows as $row){
        $name=(string)($row['worker']??'');
        if(array_key_exists($name,$expected))$indexed[$name]=$row;
    }
    $health=[];$counts=['healthy'=>0,'stale'=>0,'failed'=>0,'missing'=>0,'invalid'=>0];
    foreach($expected as $name=>$maxAge){
        $row=$indexed[$name]??null;
        $run=$row?strtotime((string)($row['last_run_at']??'').' UTC'):false;
        $success=$row&&!empty($row['last_success_at'])
            ?strtotime((string)$row['last_success_at'].' UTC'):false;
        $age=$run===false?null:max(0,$epoch-$run);
        $successAge=$success===false?null:max(0,$epoch-$success);
        if($row===null)$status='missing';
        elseif($run===false||$run>$epoch+60||($success!==false&&$success>$epoch+60))
            $status='invalid';
        elseif($age>$maxAge)$status='stale';
        elseif($success===false||$successAge>max(900,3*$maxAge))
            $status='failed';
        else $status='healthy';
        $counts[$status]++;
        $health[]=[
            'worker'=>$name,'status'=>$status,'age_seconds'=>$age,
            'success_age_seconds'=>$successAge,'last_run_at'=>$row['last_run_at']??null,
            'last_success_at'=>$row['last_success_at']??null,
            'scanned'=>(int)($row['scanned']??0),
            'delivered'=>(int)($row['delivered']??0),
            'failed'=>(int)($row['failed']??0)
        ];
    }
    return ['workers'=>$health,'counts'=>$counts,
        'all_healthy'=>$counts['healthy']===count($expected)];
}
/** Dashboard is scoped by account; no sensitive raw event payload displayed. */
function operational_outbox_status(int $accountId,bool $admin=false):array{
    operational_outbox_init();
    $where=$admin?'':' WHERE account_id=?';
    $args=$admin?[]:[$accountId];
    $pending=all("SELECT status,COUNT(*) amount FROM outbox".$where."
        GROUP BY status", $args);
    $states=[];foreach($pending as $v)$states[(string)$v['status']]=(int)$v['amount'];
    // Only platform admin may see global operational worker health.
    $jobs=$admin?all('SELECT worker,last_run_at,last_success_at,scanned,delivered,failed
        FROM operational_worker_heartbeat ORDER BY worker LIMIT 20'):[];
    return ['states'=>$states,'workers'=>$jobs,
        'health'=>$admin?operational_workers_assess($jobs):null];
}
