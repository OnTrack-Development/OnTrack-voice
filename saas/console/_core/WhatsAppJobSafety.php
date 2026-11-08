<?php
declare(strict_types=1);
require_once __DIR__.'/WhatsAppSupport.php';

/**
 * Two-stage durable WhatsApp dispatch: reserve on the DB, COMMIT, then call Meta.
 * A crash after external dispatch is UNKNOWN and must never silently retry.
 * 'processing' is safe to retry only because the network call can begin solely
 * after the durable transition to 'sending'.
 */
function wa_recover_stale_jobs(int $seconds=180,int $limit=100):array {
    $seconds=max(120,min(86400,$seconds));
    $limit=max(1,min(200,$limit));
    $before=gmdate('Y-m-d H:i:s',time()-$seconds);
    $rows=all("SELECT id,account_id,conversation_id,status,attempts
        FROM wa_ai_jobs WHERE status IN ('processing','sending') AND updated_at<?
        ORDER BY updated_at,id LIMIT $limit",[$before]);
    $stats=['retried'=>0,'review_required'=>0,'failed'=>0];
    foreach($rows as $row){
        $state=(string)$row['status'];
        if($state==='sending'){$next='needs_review';$label='review_required';}
        elseif((int)$row['attempts']<2){$next='pending';$label='retried';}
        else{$next='failed';$label='failed';}
        $affected=execute("UPDATE wa_ai_jobs SET status=?,updated_at=?
            WHERE id=? AND status=? AND updated_at<?",[$next,now(),$row['id'],$state,$before]);
        if($affected===1){
            $stats[$label]++;
            wa_audit((int)$row['account_id'],(int)$row['conversation_id'],
                'whatsapp.job.recovered',$state.'_to_'.$next);
        }
    }
    return $stats;
}

/** Reserve an outbound send while holding only DB locks, never HTTP inside them. */
function wa_job_reserve_send(array $job,string $text,bool $native):array {
    if(trim($text)===''||mb_strlen($text)>4096)
        throw new InvalidArgumentException('whatsapp_empty_reviewed_reply');
    if(db()->inTransaction())throw new RuntimeException('wa_job_nested_transaction');
    db()->beginTransaction();
    try{
        $sql='SELECT * FROM wa_conversations WHERE id=? AND account_id=?';
        if(driver()==='mysql')$sql.=' FOR UPDATE';
        $conv=one($sql,[(int)$job['conversation_id'],(int)$job['account_id']]);
        $conn=wa_connection((int)$job['connection_id'],(int)$job['account_id']);
        $enabled=$native?((int)($conn['ai_enabled']??0)===1)
            :(!empty($conn['whmcs_bridge_verified_at']));
        if(!$conv||!$enabled||$conn['state']!=='verified'||
           (int)$conv['generation']!==(int)$job['generation']||
           !wa_ai_allowed($conv)||empty($conv['last_customer_at'])||
           strtotime((string)$conv['last_customer_at'])<time()-86400){
            execute("UPDATE wa_ai_jobs SET status='skipped',updated_at=? WHERE id=? AND status='processing'",
                [now(),(int)$job['id']]);
            db()->commit();
            return ['send'=>false,'status'=>'stale_or_human'];
        }
        // Recheck that the sender can actually dispatch. An existing-webhook
        // channel without a verified outbound adapter must not be marked active.
        if(($conn['onboarding_method']??'')==='existing_webhook' &&
           !wa_existing_outbound_ready($conn)){
            execute("UPDATE wa_ai_jobs SET status='failed',updated_at=? WHERE id=? AND status='processing'",
                [now(),(int)$job['id']]);
            db()->commit();
            return ['send'=>false,'status'=>'outbound_adapter_unavailable'];
        }
        if(execute("UPDATE wa_ai_jobs SET status='sending',updated_at=? WHERE id=?
                AND account_id=? AND status='processing'",[
                now(),(int)$job['id'],(int)$job['account_id']])!==1)
            throw new RuntimeException('whatsapp_send_claim_conflict');
        db()->commit();
        return ['send'=>true,'connection'=>$conn,'conversation'=>$conv];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
/**
 * External provider send is ONCE ONLY. A timeout may mean the provider sent;
 * do not automatically retry a 'sending' or 'needs_review' job.
 */
function wa_job_send_once(array $job,string $body,?callable $transport=null,array $buttons=[],
    bool $native=true):array {
    $reservation=wa_job_reserve_send($job,$body,$native);
    if(!$reservation['send'])return ['status'=>$reservation['status']];
    $connection=$reservation['connection'];
    $conversation=$reservation['conversation'];
    try{
        // HTTP is outside DB transaction. Never hold a MySQL row lock for
        // a 12-second Meta request or an external relay timeout.
        $sent=wa_meta_send($connection,(string)$conversation['contact'],$body,$transport,$buttons);
    }catch(Throwable $error){
        execute("UPDATE wa_ai_jobs SET status='needs_review',updated_at=?
            WHERE id=? AND status='sending'",[now(),(int)$job['id']]);
        wa_audit((int)$job['account_id'],(int)$job['conversation_id'],
            'whatsapp.send.uncertain','provider_or_network_failed_after_reservation');
        return ['status'=>'needs_review'];
    }
    try{
        db()->beginTransaction();
        $mid=insert("INSERT INTO wa_messages(account_id,conversation_id,connection_id,
            provider_message_id,role,body,delivery_status,created_at)
            VALUES (?,?,?,?,'ai',?,'accepted',?)",[
            (int)$job['account_id'],(int)$job['conversation_id'],(int)$job['connection_id'],
            $sent['provider_message_id'],$body,now()]);
        execute("UPDATE wa_conversations SET last_message_at=? WHERE id=? AND account_id=?",
            [now(),(int)$job['conversation_id'],(int)$job['account_id']]);
        if(execute("UPDATE wa_ai_jobs SET status='sent',updated_at=?
            WHERE id=? AND account_id=? AND status='sending'",[
                now(),(int)$job['id'],(int)$job['account_id']])!==1)
            throw new RuntimeException('whatsapp_send_state_changed');
        wa_audit((int)$job['account_id'],(int)$job['conversation_id'],
            $native?'whatsapp.native.sent':'whatsapp.ai.sent',
            'reviewed; provider accepted not delivered');
        db()->commit();
        return ['status'=>'accepted_not_delivered','message_id'=>$mid];
    }catch(Throwable $error){
        if(db()->inTransaction())db()->rollBack();
        execute("UPDATE wa_ai_jobs SET status='needs_review',updated_at=?
            WHERE id=? AND status='sending'",[now(),(int)$job['id']]);
        wa_audit((int)$job['account_id'],(int)$job['conversation_id'],
            'whatsapp.send.persistence_uncertain','provider_accepted_but_local_commit_failed');
        return ['status'=>'needs_review'];
    }
}
