<?php
declare(strict_types=1);
/**
 * SOG read-only readiness audit for auto-answer and saving. Host SSH only.
 * No session access, no account mutation, no phone numbers or bearer secrets.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
try{
    require_once '/home/appontrack/public_html/_core/Bootstrap.php';
    require_once '/home/appontrack/public_html/_core/TenantVoiceSession.php';
    require_once '/home/appontrack/public_html/_core/AgentAnswerPolicy.php';
    $matching=all("SELECT id,kind,status,parent_id FROM accounts
        WHERE LOWER(TRIM(name))='sog' OR LOWER(TRIM(slug))='sog'
           OR LOWER(TRIM(COALESCE(brand_name,'')))='sog'
        ORDER BY id LIMIT 4");
    if(count($matching)!==1){
        $candidates=[];
        foreach($matching as $candidate){
            $candidateId=(int)$candidate['id'];
            $candidates[]=[
                'account_id'=>$candidateId,
                'kind'=>(string)$candidate['kind'],
                'status'=>(string)$candidate['status'],
                'parent_id'=>(int)($candidate['parent_id']??0),
                'agent_count'=>(int)(one("SELECT COUNT(*) n FROM agents
                    WHERE account_id=? AND status!='archived'",[$candidateId])['n']??0),
                'published_count'=>(int)(one("SELECT COUNT(DISTINCT agent_id) n FROM agent_flow_versions
                    WHERE account_id=?",[$candidateId])['n']??0),
                'paired_active_count'=>(int)(one("SELECT COUNT(*) n FROM device_bindings
                    WHERE account_id=? AND status='active'",[$candidateId])['n']??0)
            ];
        }
        echo json_encode(['probe'=>'v76_sog_read_only',
            'account_resolution'=>count($matching)===0?'not_found':'ambiguous',
            'matched_accounts'=>count($matching),
            'candidate_service_scopes'=>$candidates,
            'no_customer_data_modified'=>true],
            JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }
    $acct=$matching[0];
    $id=(int)$acct['id'];
    $parent=(int)($acct['parent_id']??0);
    $children=all("SELECT id FROM accounts WHERE parent_id=? AND status='active' LIMIT 50",[$id]);
    $agents=all("SELECT a.id,a.site_id,a.voice,a.status FROM agents a
        JOIN sites s ON s.id=a.site_id AND s.account_id=a.account_id
        WHERE a.account_id=? AND a.status!='archived' AND s.status='active'
        ORDER BY a.id LIMIT 15",[$id]);
    $out=[];
    foreach($agents as $agent){
        $agentId=(int)$agent['id'];
        $siteId=(int)$agent['site_id'];
        $draft=answer_policy_for_agent($id,$agentId);
        $pub=published_agent_flow($id,$siteId,$agentId);
        $actual=$pub?json_decode((string)$pub['answer_policy_json'],true):null;
        $devices=all("SELECT sim_slot,last_seen_at,status FROM device_bindings
            WHERE account_id=? AND site_id=? AND agent_id=? AND status='active'
            ORDER BY id DESC LIMIT 5",[$id,$siteId,$agentId]);
        $lastSeen=null;
        foreach($devices as $dev){
            $timestamp=strtotime((string)($dev['last_seen_at']??''));
            if($timestamp!==false)$lastSeen=$lastSeen===null?time()-$timestamp:
                min($lastSeen,max(0,time()-$timestamp));
        }
        $out[]=['agent_id'=>$agentId,'agent_state'=>(string)$agent['status'],
            'voice_io_premium'=>$agent['voice']==='io.premium',
            'draft_auto_answer'=>$draft['auto_answer'],
            'published_revision'=>$pub?(int)$pub['revision']:null,
            'published_auto_answer'=>$actual['auto_answer']??null,
            'published_answer_delay'=>$actual['answer_delay_seconds']??null,
            'paired_sim_count'=>count($devices),
            'paired_sim_heartbeat_age_seconds'=>$lastSeen];
    }
    $subscription=one("SELECT id,starts_at,ends_at FROM subscriptions
        WHERE account_id=? AND status='active' ORDER BY ends_at DESC LIMIT 1",[$id]);
    $outcome=['probe'=>'v76_sog_read_only','account_resolution'=>'unique',
        'account_id'=>$id,'account_kind'=>$acct['kind'],
        'account_status'=>$acct['status'],
        'reseller_parent_id'=>$parent?:null,
        'account_ancestor_service_active'=>account_service_active(account($id)),
        'voice_entitlement_active'=>tenant_voice_entitled(['account_id'=>$id]),
        'active_subscription_row'=>$subscription!==null,
        'active_child_accounts'=>count($children),
        'agents'=>$out,
        'no_customer_data_modified'=>true,
        'no_calls_placed'=>true,'no_tokens_exposed'=>true];
    echo json_encode($outcome,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $ex){
    fwrite(STDERR,'V76_SOG_READ_ONLY_PROBE_ERROR '.get_class($ex)."\n");
    exit(1);
}
