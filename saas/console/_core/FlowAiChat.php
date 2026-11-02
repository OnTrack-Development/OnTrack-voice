<?php
declare(strict_types=1);
require_once __DIR__.'/FlowAi.php';

/**
 * Durable conversation belonging to ONE authenticated account/user/agent.
 * Never query by agent alone; this is editable business context, not public data.
 */
function flow_ai_chat_read(int $accountId,int $userId,int $agentId):array {
    $row=one('SELECT messages_json,pending_graph_json FROM flow_ai_chats WHERE account_id=? AND user_id=? AND agent_id=?',
        [$accountId,$userId,$agentId]);
    $list=$row?json_decode((string)$row['messages_json'],true):[];
    if(!is_array($list))$list=[];
    $messages=[];
    foreach(array_slice($list,-18) as $entry){
        if(!is_array($entry)||!in_array($entry['role']??null,['user','assistant'],true)
            ||!is_string($entry['text']??null))continue;
        $messages[]=['role'=>$entry['role'],'text'=>mb_substr($entry['text'],0,2800)];
    }
    $pending=null;
    if($row && is_string($row['pending_graph_json']??null)){
        $graph=json_decode($row['pending_graph_json'],true,32);
        if(is_array($graph)){
            try{$pending=flow_ai_validate_proposal($graph);}
            catch(Throwable){$pending=null;}
        }
    }
    return ['messages'=>$messages,'pending_graph'=>$pending];
}
function flow_ai_chat_write(int $accountId,int $userId,int $agentId,array $messages,?array $pending):void {
    $clean=[];
    foreach(array_slice($messages,-18) as $entry){
        if(!in_array($entry['role']??null,['user','assistant'],true))continue;
        $clean[]=['role'=>$entry['role'],'text'=>mb_substr(trim((string)($entry['text']??'')),0,2800)];
    }
    $encoded=json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $graph=$pending===null?null:json_encode(flow_ai_validate_proposal($pending),
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if(driver()==='mysql'){
        execute('INSERT INTO flow_ai_chats(account_id,user_id,agent_id,messages_json,pending_graph_json,updated_at)
            VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE
            messages_json=VALUES(messages_json),pending_graph_json=VALUES(pending_graph_json),updated_at=VALUES(updated_at)',
            [$accountId,$userId,$agentId,$encoded,$graph,now()]);
    }else{
        execute('INSERT INTO flow_ai_chats(account_id,user_id,agent_id,messages_json,pending_graph_json,updated_at)
            VALUES(?,?,?,?,?,?) ON CONFLICT(account_id,user_id,agent_id)
            DO UPDATE SET messages_json=excluded.messages_json,
                pending_graph_json=excluded.pending_graph_json,updated_at=excluded.updated_at',
            [$accountId,$userId,$agentId,$encoded,$graph,now()]);
    }
}
/** Explanation is computed from the validated graph, not invented by an LLM. */
function flow_ai_explain_graph(array $graph):string {
    $graph=flow_ai_validate_proposal($graph);
    $edges=[];$nodes=[];
    foreach($graph['nodes'] as $n)$nodes[(string)$n['id']]=$n;
    foreach($graph['edges'] as $e)$edges[(string)$e['from']][]=$e;
    $start=null;
    foreach($graph['nodes'] as $n)if($n['type']==='start'){$start=$n;break;}
    $parts=['ده شرح المخطط المقترح:'];
    if($start!==null)$parts[]='• البداية: '.mb_substr(trim((string)$start['text']),0,140);
    $visited=[];$id=$start['id']??null;$steps=0;
    $types=['start'=>'بداية','say'=>'رد أو معلومة','listen'=>'استماع للعميل',
        'ask'=>'سؤال للعميل','instruction'=>'تعليمات داخلية للإيجنت',
        'condition'=>'تفرّع حسب الإجابة','end'=>'إنهاء المحادثة'];
    while(is_string($id) && isset($nodes[$id]) && !isset($visited[$id]) && $steps<14){
        $visited[$id]=true;$steps++;
        $node=$nodes[$id];$type=(string)$node['type'];
        if($type!=='start'){
            $line='• '.($types[$type]??'خطوة').': '.mb_substr(trim((string)$node['text']),0,140);
            $parts[]=$line;
        }
        $options=$edges[$id]??[];
        if(count($options)>1){
            foreach(array_slice($options,0,5) as $edge){
                $label=trim((string)$edge['label']);
                $destination=$nodes[$edge['to']]??null;
                $parts[]='  ↳ '.($label!==''?'لو الرد «'.mb_substr($label,0,55).'»':'المسار الافتراضي').
                    ' ← '.($destination?($types[$destination['type']]??'خطوة').' — '.mb_substr((string)$destination['text'],0,90):'خطوة تالية');
            }
        }
        if(!$options)break;
        $next=null;
        foreach($options as $e)if(trim((string)$e['label'])===''){$next=$e['to'];break;}
        $id=$next??$options[0]['to'];
    }
    if(count($visited)<count($nodes))$parts[]='• توجد مسارات فرعية أخرى في المخطط، تقدر تفتح العقدة لمراجعتها.';
    $parts[]='المخطط مسودة محادثة فقط؛ أي حجز أو تغيير على نظام خارجي يحتاج تكاملًا وتنفيذًا مؤكدًا.';
    return mb_substr(implode("\n",$parts),0,2700);
}
