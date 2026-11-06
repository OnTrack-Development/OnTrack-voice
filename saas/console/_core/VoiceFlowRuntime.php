<?php
declare(strict_types=1);
require_once __DIR__.'/PublishedFlow.php';
require_once __DIR__.'/VoiceKnowledgeWeb.php';
require_once __DIR__.'/VoiceToolGateway.php';
/** Stateful, reproducible server Flow execution. Never execute external writes from an LLM suggestion. */
function voice_flow_runtime_init():void {
    $mark=private_path().'/.schema.voice-flow-runtime.v1';
    if(is_file($mark))return;
    $mysql=driver()==='mysql';
    $pk=$mysql?'BIGINT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $int=$mysql?'BIGINT':'INTEGER';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $large=$mysql?'MEDIUMTEXT':'TEXT';
    db()->exec("CREATE TABLE IF NOT EXISTS voice_flow_sessions (
       id $pk,account_id $int NOT NULL,site_id $int NOT NULL,agent_id $int NOT NULL,
       actor_id $int NOT NULL DEFAULT 0,device_binding_id $int NOT NULL DEFAULT 0,
       source VARCHAR(20) NOT NULL,call_id VARCHAR(64) NOT NULL,
       published_revision INTEGER NOT NULL,flow_hash VARCHAR(64) NOT NULL,
       state_json $large NOT NULL,step_version INTEGER NOT NULL DEFAULT 1,
       status VARCHAR(20) NOT NULL DEFAULT 'active',
       created_at VARCHAR(19) NOT NULL,updated_at VARCHAR(19) NOT NULL,
       UNIQUE(account_id,site_id,agent_id,source,call_id))".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS voice_flow_traces (
       id $pk,account_id $int NOT NULL,site_id $int NOT NULL,
       session_id $int NOT NULL,agent_id $int NOT NULL,
       node_id VARCHAR(64) NOT NULL,event_type VARCHAR(32) NOT NULL,
       event_detail VARCHAR(500) NOT NULL,created_at VARCHAR(19) NOT NULL)".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS voice_flow_node_tools (
       account_id $int NOT NULL,site_id $int NOT NULL,agent_id $int NOT NULL,
       node_id VARCHAR(64) NOT NULL,policy_id $int NOT NULL,updated_at VARCHAR(19) NOT NULL,
       PRIMARY KEY(account_id,site_id,agent_id,node_id))".$engine);
    file_put_contents($mark,now(),LOCK_EX);@chmod($mark,0600);
}
function voice_flow_tool_bind(int $accountId,int $siteId,int $agentId,string $nodeId,int $policyId):void {
    voice_flow_runtime_init();
    if(!preg_match('/^[A-Za-z0-9_-]{1,60}$/D',$nodeId))
        throw new InvalidArgumentException('flow_tool_node_invalid');
    $flow=published_agent_flow($accountId,$siteId,$agentId);
    if(!$flow)throw new InvalidArgumentException('flow_tool_agent_not_published');
    $graph=flow_preview_compile(json_decode((string)$flow['flow_json'],true,32,JSON_THROW_ON_ERROR));
    $node=$graph['nodes'][$nodeId]??null;
    if(!$node||!in_array($node['type'],['crm_lookup','crm_write','schedule','tool'],true))
        throw new InvalidArgumentException('flow_tool_node_type_denied');
    $policy=voice_tool_policy($accountId,$siteId,$policyId);
    if(in_array($node['type'],['crm_write','schedule'],true)&&
       !voice_tool_is_write((string)$policy['operation']))
        throw new InvalidArgumentException('flow_write_policy_required');
    if($node['type']==='crm_lookup'&&voice_tool_is_write((string)$policy['operation']))
        throw new InvalidArgumentException('flow_read_policy_required');
    $existing=one('SELECT node_id FROM voice_flow_node_tools WHERE account_id=? AND site_id=? AND agent_id=? AND node_id=?',
        [$accountId,$siteId,$agentId,$nodeId]);
    if($existing)execute("UPDATE voice_flow_node_tools SET policy_id=?,updated_at=?
        WHERE account_id=? AND site_id=? AND agent_id=? AND node_id=?",
        [$policyId,now(),$accountId,$siteId,$agentId,$nodeId]);
    else insert("INSERT INTO voice_flow_node_tools
        (account_id,site_id,agent_id,node_id,policy_id,updated_at)
        VALUES (?,?,?,?,?,?)",[$accountId,$siteId,$agentId,$nodeId,$policyId,now()]);
}
function voice_flow_trace(array $session,string $node,string $event,string $text):void {
    insert("INSERT INTO voice_flow_traces
       (account_id,site_id,session_id,agent_id,node_id,event_type,event_detail,created_at)
       VALUES (?,?,?,?,?,?,?,?)",[
       (int)$session['account_id'],(int)$session['site_id'],(int)$session['id'],
       (int)$session['agent_id'],mb_substr($node,0,64),$event,mb_substr($text,0,500),now()]);
}
function voice_flow_start(int $accountId,int $siteId,int $agentId,string $callId,
   string $source='web_preview',int $actorId=0,int $deviceBindingId=0):array {
    voice_flow_runtime_init();
    if(!in_array($source,['web_preview','device'],true)||
       !preg_match('/^[A-Za-z0-9_-]{8,64}$/D',$callId))
        throw new InvalidArgumentException('flow_source_or_call_invalid');
    if($source==='web_preview'){
        if($actorId<1)throw new InvalidArgumentException('flow_actor_required');
        voice_tool_actor($accountId,$actorId);
    }else{
        if($deviceBindingId<1)throw new InvalidArgumentException('flow_device_required');
        // A positive ID is not proof of a paired SIM. Validate its current
        // tenant/site/agent binding before opening a device-backed workflow.
        if(!one("SELECT d.id FROM device_bindings d
            JOIN accounts a ON a.id=d.account_id AND a.status='active'
            WHERE d.id=? AND d.account_id=? AND d.site_id=? AND d.agent_id=?
              AND d.status='active'",
            [$deviceBindingId,$accountId,$siteId,$agentId]))
            throw new InvalidArgumentException('flow_device_binding_invalid');
    }
    $agent=one("SELECT a.id FROM agents a
        JOIN sites s ON s.id=a.site_id AND s.account_id=a.account_id
        WHERE a.id=? AND a.account_id=? AND a.site_id=? AND a.status!='archived'
          AND s.status='active'",[$agentId,$accountId,$siteId]);
    $published=published_agent_flow($accountId,$siteId,$agentId);
    if(!$agent||!$published)throw new InvalidArgumentException('flow_unpublished_agent');
    $graph=flow_preview_compile(json_decode((string)$published['flow_json'],true,32,JSON_THROW_ON_ERROR));
    $existing=one('SELECT * FROM voice_flow_sessions WHERE account_id=? AND site_id=?
        AND agent_id=? AND source=? AND call_id=?',
        [$accountId,$siteId,$agentId,$source,$callId]);
    if($existing){
        if((int)$existing['actor_id']!==$actorId||
            (int)$existing['device_binding_id']!==$deviceBindingId)
            throw new RuntimeException('flow_session_owner_mismatch');
        return ['session_id'=>(int)$existing['id'],'status'=>$existing['status'],
            'reused'=>true,'revision'=>(int)$existing['published_revision']];
    }
    $state=['cursor'=>$graph['start'],'awaiting'=>null,'fields'=>[],'last_answer'=>null,
        'pending_tool'=>null,'turns'=>0,'steps'=>0,'done'=>false,'blocked'=>false];
    $id=insert("INSERT INTO voice_flow_sessions
        (account_id,site_id,agent_id,actor_id,device_binding_id,source,call_id,
         published_revision,flow_hash,state_json,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
        [$accountId,$siteId,$agentId,$actorId,$deviceBindingId,$source,$callId,
        (int)$published['revision'],(string)$published['flow_hash'],
        json_encode($state,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),now(),now()]);
    $session=['id'=>$id,'account_id'=>$accountId,'site_id'=>$siteId,'agent_id'=>$agentId];
    voice_flow_trace($session,$graph['start'],'started','published_revision_'.(int)$published['revision']);
    return ['session_id'=>$id,'status'=>'active','reused'=>false,'revision'=>(int)$published['revision']];
}
function voice_flow_session(int $accountId,int $sessionId,int $actorId=0,int $deviceBindingId=0):array {
    voice_flow_runtime_init();
    $s=one('SELECT * FROM voice_flow_sessions WHERE id=? AND account_id=?',[$sessionId,$accountId]);
    if(!$s||($s['source']==='web_preview'&&(int)$s['actor_id']!==$actorId)||
       ($s['source']==='device'&&(int)$s['device_binding_id']!==$deviceBindingId))
        throw new InvalidArgumentException('flow_session_access_denied');
    return $s;
}
/** Archive/suspension must revoke a previously opened workflow before the next step. */
function voice_flow_require_active_context(array $session):void {
    if(!one("SELECT ag.id FROM agents ag
        JOIN sites s ON s.id=ag.site_id AND s.account_id=ag.account_id
        JOIN accounts a ON a.id=ag.account_id
        WHERE ag.id=? AND ag.account_id=? AND ag.site_id=?
          AND ag.status!='archived' AND s.status='active' AND a.status='active'",
        [(int)$session['agent_id'],(int)$session['account_id'],(int)$session['site_id']]))
        throw new InvalidArgumentException('flow_context_inactive');
    // The current call retains its immutable agent snapshot after reassignment,
    // but revoking a physical SIM must terminate permission to continue.
    if(($session['source']??'')==='device' &&
       !one("SELECT id FROM device_bindings
          WHERE id=? AND account_id=? AND site_id=? AND status='active'",
          [(int)$session['device_binding_id'],(int)$session['account_id'],
           (int)$session['site_id']]))
        throw new InvalidArgumentException('flow_device_binding_inactive');
}
function voice_flow_saved_graph(array $session):array {
    $snapshot=one('SELECT flow_json,revision,flow_hash FROM agent_flow_versions
       WHERE account_id=? AND site_id=? AND agent_id=? AND revision=?',
       [(int)$session['account_id'],(int)$session['site_id'],(int)$session['agent_id'],
       (int)$session['published_revision']]);
    if(!$snapshot||!hash_equals((string)$session['flow_hash'],(string)$snapshot['flow_hash']))
        throw new RuntimeException('flow_published_snapshot_missing');
    return flow_preview_compile(json_decode((string)$snapshot['flow_json'],true,32,JSON_THROW_ON_ERROR));
}
function voice_flow_process(int $accountId,int $sessionId,int $actorId=0,?string $reply=null,
    int $deviceBindingId=0):array {
    $session=voice_flow_session($accountId,$sessionId,$actorId,$deviceBindingId);
    voice_flow_require_active_context($session);
    if(in_array($session['status'],['completed','blocked'],true))return ['status'=>$session['status'],'events'=>[],'done'=>true];
    $graph=voice_flow_saved_graph($session);
    $state=json_decode((string)$session['state_json'],true,32,JSON_THROW_ON_ERROR);
    if(!is_array($state))throw new RuntimeException('flow_state_invalid');
    $events=[];$pending=$state['pending_tool']??null;
    if($pending!==null){
        // Reconcile sessions created before the unbound-tool fix; never allow a policy_id=0
        // placeholder to wait indefinitely or be released by an unrelated tool request.
        if(is_array($pending)&&(!empty($pending['blocked'])||(int)($pending['policy_id']??0)<1)){
            $state['pending_tool']=null;
            $state['blocked']=true;$state['done']=true;
            $updated=execute("UPDATE voice_flow_sessions SET state_json=?,status='blocked',
                step_version=step_version+1,updated_at=?
                WHERE id=? AND account_id=? AND step_version=?",[
                json_encode($state,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                now(),$sessionId,$accountId,(int)$session['step_version']]);
            if($updated!==1)throw new RuntimeException('flow_session_write_conflict');
            voice_flow_trace($session,(string)($pending['node_id']??''),'tool_unbound','legacy session blocked');
            return ['status'=>'blocked','events'=>[[
                'kind'=>'notice','text'=>'الأداة غير مربوطة بسياسة معتمدة؛ تم إيقاف هذا المسار بأمان دون تنفيذ أي عملية.'
            ]],'state'=>$state,'done'=>true];
        }
        $events[]=['kind'=>'notice','text'=>'خطوة خارجية معلقة بموافقة صريحة؛ لا يُفترض تنفيذها.'];
        return ['status'=>'awaiting_tool','events'=>$events,'state'=>$state,'done'=>false];
    }
    if($state['awaiting']!==null){
        if($reply===null||!is_string($reply)||mb_strlen($reply)>500)
            throw new InvalidArgumentException('flow_reply_required');
        $answer=trim($reply);
        if($answer==='')throw new InvalidArgumentException('flow_empty_reply');
        $next=flow_preview_edge($graph,(string)$state['awaiting'],$answer);
        if($next===null){
            // Side question: do not discard the waiting node, collected fields or call context.
            $events[]=['kind'=>'side_question','text'=>'السؤال الجانبي اتسجل؛ العقدة الحالية لم تتغير.'];
            voice_flow_trace($session,(string)$state['awaiting'],'side_question','non-matching answer');
        }else{
            $old=(string)$state['awaiting'];
            if(($graph['nodes'][$old]['type']??'')==='collect_field')$state['fields'][$old]=$answer;
            $state['last_answer']=$answer;$state['cursor']=$next;$state['awaiting']=null;$state['turns']++;
            voice_flow_trace($session,$old,'answered','transition_'.$next);
        }
    }elseif($reply!==null)throw new InvalidArgumentException('flow_not_waiting');
    if($state['awaiting']===null){
        for($hop=0;$hop<40;$hop++){
            if(++$state['steps']>200||$state['turns']>60)
                throw new RuntimeException('flow_runtime_bounded_limit');
            $node=$graph['nodes'][$state['cursor']??'']??null;
            if(!$node)throw new RuntimeException('flow_missing_cursor');
            $nodeId=(string)$node['id'];$type=(string)$node['type'];$message=trim((string)$node['text']);
            voice_flow_trace($session,$nodeId,'visited',$type);
            if($type==='end'){
                if($message!=='')$events[]=['kind'=>'agent','text'=>$message];
                $state['done']=true;break;
            }
            if(in_array($type,['ask','listen','collect_field','approval'],true)){
                if($message!=='')$events[]=['kind'=>'agent','text'=>$message];
                $state['awaiting']=$nodeId;break;
            }
            if($type==='knowledge_lookup'){
                // Use the caller's most recent answer to retrieve relevant
                // approved facts, not the same blind first-page excerpt.
                $facts=voice_kb_agent_context((int)$session['account_id'],
                    (int)$session['site_id'],(int)$session['agent_id'],1200,
                    (string)($state['last_answer']??''));
                $events[]=['kind'=>'knowledge','text'=>$facts!==''?$facts:
                    'لا توجد معرفة عامة معتمدة مناسبة؛ لا تخترع معلومة.'];
                $next=flow_preview_edge($graph,$nodeId,null);
            }elseif(in_array($type,['crm_lookup','crm_write','schedule','tool'],true)){
                $binding=one("SELECT policy_id FROM voice_flow_node_tools
                    WHERE account_id=? AND site_id=? AND agent_id=? AND node_id=?",[
                    $session['account_id'],$session['site_id'],$session['agent_id'],$nodeId]);
                if(!$binding){
                    $events[]=['kind'=>'notice','text'=>'هذه العقدة ليست مرتبطة بأداة معتمدة؛ أُوقف التنفيذ بأمان ولم تنفذ أي عملية.'];
                    $state['blocked']=true;$state['done']=true;
                    voice_flow_trace($session,$nodeId,'tool_unbound','no approved policy binding');
                    break;
                }
                $state['pending_tool']=['node_id'=>$nodeId,'policy_id'=>(int)$binding['policy_id']];
                $events[]=['kind'=>'tool_requires_approval','node_id'=>$nodeId,
                    'policy_id'=>(int)$binding['policy_id'],
                    'text'=>'الأداة تنتظر موافقة صريحة وتنفيذًا موثقًا؛ لا نتيجة مختلقة.'];
                break;
            }elseif($type==='condition'||$type==='intent'||$type==='validation'){
                $next=flow_preview_edge($graph,$nodeId,$state['last_answer']??null);
                if($next===null){
                    $state['awaiting']=$nodeId;
                    $events[]=['kind'=>'notice','text'=>'لا يوجد مسار مطابق؛ مطلوب توضيح قبل الاستكمال.'];
                    break;
                }
            }else{
                if($message!==''&&!in_array($type,['instruction','reasoning'],true))
                    $events[]=['kind'=>'agent','text'=>$message];
                $next=flow_preview_edge($graph,$nodeId,null);
            }
            if($next===null){$state['done']=true;break;}
            $state['cursor']=$next;
        }
    }
    $state['done']=(bool)$state['done'];
    $status=!empty($state['blocked'])?'blocked':
        ($state['done']?'completed':($state['pending_tool']!==null?'awaiting_tool':'active'));
    $updated=execute("UPDATE voice_flow_sessions SET state_json=?,status=?,step_version=step_version+1,updated_at=?
       WHERE id=? AND account_id=? AND step_version=?",[
       json_encode($state,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$status,now(),
       $sessionId,$accountId,(int)$session['step_version']]);
    if($updated!==1)throw new RuntimeException('flow_session_write_conflict');
    return ['status'=>$status,'events'=>$events,'state'=>$state,'done'=>$state['done']];
}
/** Only server-confirmed, matching tool requests can release a paused node. */
function voice_flow_complete_tool(int $accountId,int $sessionId,int $actorId,int $requestId):array {
    $session=voice_flow_session($accountId,$sessionId,$actorId);
    voice_flow_require_active_context($session);
    $state=json_decode((string)$session['state_json'],true,32,JSON_THROW_ON_ERROR);
    $pending=$state['pending_tool']??null;
    if(!is_array($pending))throw new InvalidArgumentException('flow_no_pending_tool');
    if(!empty($pending['blocked'])||(int)($pending['policy_id']??0)<1)
        throw new InvalidArgumentException('flow_tool_node_unbound');
    $tool=one("SELECT status,operation,reference_id,call_id,policy_id
       FROM voice_tool_requests WHERE id=? AND account_id=? AND site_id=? AND actor_id=?",
       [$requestId,$accountId,(int)$session['site_id'],$actorId]);
    if(!$tool||(int)$tool['policy_id']!==(int)$pending['policy_id']||
       $tool['call_id']!==$session['call_id'])
        throw new InvalidArgumentException('flow_tool_request_not_bound');
    if(!in_array($tool['status'],['succeeded','failed','uncertain'],true))
        throw new RuntimeException('flow_tool_waiting_on_provider');
    $graph=voice_flow_saved_graph($session);
    $node=(string)$pending['node_id'];
    if($tool['status']==='succeeded'){
        $next=flow_preview_edge($graph,$node,'success')??flow_preview_edge($graph,$node,null);
        $event=['kind'=>'tool_result','status'=>'succeeded','reference_id'=>$tool['reference_id']];
    }else{
        $next=flow_preview_edge($graph,$node,'failed');
        $event=['kind'=>'tool_result','status'=>$tool['status'],
            'text'=>'العملية لم يتم تأكيد نجاحها؛ ممنوع اعتبارها مكتملة.'];
    }
    $state['pending_tool']=null;
    if($next!==null){$state['cursor']=$next;$state['awaiting']=null;}
    else{$state['done']=true;$state['awaiting']=null;}
    $newStatus=($state['done']??false)?'blocked':'active';
    $updated=execute("UPDATE voice_flow_sessions SET state_json=?,status=?,
       step_version=step_version+1,updated_at=? WHERE id=? AND account_id=? AND step_version=?",[
       json_encode($state,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$newStatus,now(),
       $sessionId,$accountId,(int)$session['step_version']]);
    if($updated!==1)throw new RuntimeException('flow_session_write_conflict');
    voice_flow_trace($session,$node,'tool_'.$tool['status'],
        $tool['status']==='succeeded'?'verified_ref_'.(string)($tool['reference_id']??''):'unconfirmed');
    return ['status'=>$newStatus,'events'=>[$event],'state'=>$state,'done'=>(bool)($state['done']??false)];
}
function voice_flow_session_traces(int $accountId,int $sessionId,int $actorId):array {
    voice_flow_session($accountId,$sessionId,$actorId);
    return all('SELECT node_id,event_type,event_detail,created_at FROM voice_flow_traces
      WHERE session_id=? AND account_id=? ORDER BY id ASC LIMIT 200',[$sessionId,$accountId]);
}
