<?php
declare(strict_types=1);

/**
 * Deterministic, side-effect-free text preview for an agent's DRAFT Flow graph.
 * This is NOT an AI voice runtime, CRM writer, booking engine, or SIM bridge.
 * All state is scoped by the authenticated endpoint to one agent and graph hash.
 */
function flow_preview_compile(array $graph):array {
    $nodes=$graph['nodes']??null;
    $edges=$graph['edges']??null;
    if(!is_array($nodes)||!array_is_list($nodes)||!is_array($edges)||!array_is_list($edges)
        ||count($nodes)<1||count($nodes)>100||count($edges)>250)
        throw new InvalidArgumentException('flow_invalid_graph');
    $types=['start','say','listen','ask','condition','instruction','knowledge_lookup',
        'collect_field','crm_lookup','crm_write','schedule','handoff','end',
        'intent','reasoning','validation','approval','fallback','tool'];
    $index=[];$starts=[];
    foreach($nodes as $node){
        if(!is_array($node)||!is_string($node['id']??null)
            ||!preg_match('/^[A-Za-z0-9_-]{1,60}$/D',$node['id'])
            ||isset($index[$node['id']])||!in_array($node['type']??null,$types,true)
            ||!is_string($node['text']??'')||mb_strlen((string)($node['text']??''))>900)
            throw new InvalidArgumentException('flow_invalid_node');
        $index[$node['id']]=['id'=>$node['id'],'type'=>$node['type'],'text'=>(string)($node['text']??'')];
        if($node['type']==='start')$starts[]=$node['id'];
    }
    if(count($starts)!==1)throw new InvalidArgumentException('flow_one_start_required');
    $out=[];
    foreach($edges as $edge){
        if(!is_array($edge)||!is_string($edge['from']??null)||!is_string($edge['to']??null)
            ||!isset($index[$edge['from']],$index[$edge['to']])
            ||!is_string($edge['label']??'')||mb_strlen((string)($edge['label']??''))>70)
            throw new InvalidArgumentException('flow_invalid_edge');
        $from=$edge['from'];$label=trim((string)($edge['label']??''));
        $normalized=mb_strtolower($label,'UTF-8');
        foreach($out[$from]??[] as $prior){
            if($prior['normalized']===$normalized)throw new InvalidArgumentException('flow_duplicate_branch');
        }
        $out[$from][]=['to'=>$edge['to'],'label'=>$label,'normalized'=>$normalized];
    }
    return ['nodes'=>$index,'edges'=>$out,'start'=>$starts[0]];
}
function flow_preview_edge(array $flow,string $nodeId,?string $answer):?string {
    $edges=$flow['edges'][$nodeId]??[];
    if(!$edges)return null;
    if($answer!==null){
        $needle=mb_strtolower(trim($answer),'UTF-8');
        foreach($edges as $edge)if($edge['normalized']!==''&&$edge['normalized']!=='*'
            &&$edge['normalized']===$needle)return $edge['to'];
    }
    foreach($edges as $edge)if($edge['normalized']==='')return $edge['to'];
    foreach($edges as $edge)if($edge['normalized']==='*')return $edge['to'];
    // One unlabeled route is safe; a labelled route must not be guessed.
    return null;
}
function flow_preview_initial(array $flow):array {
    return ['cursor'=>$flow['start'],'awaiting'=>null,'last_answer'=>null,
        'fields'=>[],'turns'=>0,'steps'=>0,'done'=>false];
}
/**
 * Start with answer=null; later supply a single user's reply.
 * Only explicit branch labels match (case-insensitive); empty/* is fallback.
 * Unsupported external nodes fail closed with a visible "not executed" notice.
 */
function flow_preview_step(array $flow,array $state,?string $answer=null):array {
    $events=[];
    if(($state['done']??false)===true)
        return ['state'=>$state,'events'=>[],'waiting'=>false,'done'=>true];
    if(($state['steps']??0)>200||($state['turns']??0)>30)
        throw new InvalidArgumentException('flow_preview_limit');
    $awaiting=$state['awaiting']??null;
    if($awaiting!==null){
        if(!is_string($answer)||mb_strlen($answer)>500)
            throw new InvalidArgumentException('flow_answer_required');
        $answer=trim($answer);
        if($answer==='') {
            $events[]=['kind'=>'notice','text'=>'اكتب ردًا قبل المتابعة.'];
            return ['state'=>$state,'events'=>$events,'waiting'=>true,'done'=>false];
        }
        $next=flow_preview_edge($flow,$awaiting,$answer);
        $state['turns']++;
        if($next===null){
            $events[]=['kind'=>'notice','text'=>'الرد لا يطابق أي مسار محدد. جرّب إحدى تسميات الروابط.'];
            return ['state'=>$state,'events'=>$events,'waiting'=>true,'done'=>false];
        }
        $type=$flow['nodes'][$awaiting]['type']??'';
        if($type==='collect_field')$state['fields'][$awaiting]=$answer;
        $state['last_answer']=$answer;
        $state['cursor']=$next;
        $state['awaiting']=null;
    }elseif($answer!==null){
        throw new InvalidArgumentException('flow_not_waiting');
    }
    for($hop=0;$hop<40;$hop++){
        if(++$state['steps']>200)throw new InvalidArgumentException('flow_preview_limit');
        $node=$flow['nodes'][$state['cursor']??'']??null;
        if(!$node)throw new InvalidArgumentException('flow_unknown_cursor');
        $type=$node['type'];$id=$node['id'];$message=trim($node['text']);
        if(in_array($type,['knowledge_lookup','crm_lookup','crm_write','schedule','handoff','tool'],true)){
            $events[]=['kind'=>'notice','text'=>'هذه الخطوة (' . $type . ') تتطلب تكاملًا فعليًا؛ لم تُنفّذ أي عملية خارجية.'];
            $state['done']=true;break;
        }
        if($type==='end'){
            if($message!=='')$events[]=['kind'=>'agent','text'=>$message];
            $state['done']=true;break;
        }
        if(in_array($type,['ask','listen','collect_field','approval'],true)){
            if($message!=='')$events[]=['kind'=>'agent','text'=>$message];
            $state['awaiting']=$id;break;
        }
        if(in_array($type,['condition','intent','validation'],true)){
            $next=flow_preview_edge($flow,$id,$state['last_answer']??null);
            if($next===null){
                $events[]=['kind'=>'notice','text'=>'لا يوجد مسار شرط مطابق؛ المحاكاة توقفت دون تخمين.'];
                $state['done']=true;break;
            }
        }else{
            // Internal guidance/knowledge policy is never spoken as a chat message.
            if($message!==''&&!in_array($type,['instruction','reasoning'],true))$events[]=['kind'=>'agent','text'=>$message];
            $next=flow_preview_edge($flow,$id,null);
            if($next===null){
                $events[]=['kind'=>'notice','text'=>'لا توجد خطوة تالية؛ انتهت المحاكاة.'];
                $state['done']=true;break;
            }
        }
        $state['cursor']=$next;
    }
    if(!$state['done']&&$state['awaiting']===null){
        $events[]=['kind'=>'notice','text'=>'تم إيقاف حلقة طويلة بأمان؛ راجع روابط Flow.'];
        $state['done']=true;
    }
    return ['state'=>$state,'events'=>$events,'waiting'=>$state['awaiting']!==null,'done'=>$state['done']];
}
/** Scope every preview to an active account, site and non-archived agent. */
function flow_preview_agent_for_account(int $accountId,int $agentId):?array {
    return one("SELECT ag.id,ag.account_id,ag.site_id,ag.status
        FROM agents ag JOIN sites s ON s.id=ag.site_id AND s.account_id=ag.account_id
        JOIN accounts a ON a.id=ag.account_id
        WHERE ag.id=? AND ag.account_id=? AND ag.status!='archived'
          AND s.status='active' AND a.status='active'",[$agentId,$accountId]);
}
