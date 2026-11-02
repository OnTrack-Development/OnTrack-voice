<?php
declare(strict_types=1);
require_once __DIR__.'/FlowAi.php';

/**
 * Durable conversation belonging to ONE authenticated account/user/agent.
 * Never query by agent alone; this is editable business context, not public data.
 */
function flow_ai_chat_read(int $accountId,int $userId,int $agentId):array {
    $row=one('SELECT messages_json,pending_graph_json,pending_profile_json FROM flow_ai_chats WHERE account_id=? AND user_id=? AND agent_id=?',
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
    $profile=null;
    if($row && is_string($row['pending_profile_json']??null)){
        $candidate=json_decode($row['pending_profile_json'],true,32);
        if(is_array($candidate)){
            try{require_once __DIR__.'/FlowAiProfile.php';$profile=flow_ai_profile_validate($candidate);}
            catch(Throwable){$profile=null;}
        }
    }
    return ['messages'=>$messages,'pending_graph'=>$pending,'pending_profile'=>$profile];
}
function flow_ai_chat_write(int $accountId,int $userId,int $agentId,array $messages,?array $pending,?array $pendingProfile=null):void {
    $clean=[];
    foreach(array_slice($messages,-18) as $entry){
        if(!in_array($entry['role']??null,['user','assistant'],true))continue;
        $clean[]=['role'=>$entry['role'],'text'=>mb_substr(trim((string)($entry['text']??'')),0,2800)];
    }
    $encoded=json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $graph=$pending===null?null:json_encode(flow_ai_validate_proposal($pending),
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $profile=null;
    if($pendingProfile!==null){
        require_once __DIR__.'/FlowAiProfile.php';
        $profile=json_encode(flow_ai_profile_validate($pendingProfile),
            JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
    if(driver()==='mysql'){
        execute('INSERT INTO flow_ai_chats(account_id,user_id,agent_id,messages_json,pending_graph_json,pending_profile_json,updated_at)
            VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE
            messages_json=VALUES(messages_json),pending_graph_json=VALUES(pending_graph_json),
            pending_profile_json=VALUES(pending_profile_json),updated_at=VALUES(updated_at)',
            [$accountId,$userId,$agentId,$encoded,$graph,$profile,now()]);
    }else{
        execute('INSERT INTO flow_ai_chats(account_id,user_id,agent_id,messages_json,pending_graph_json,pending_profile_json,updated_at)
            VALUES(?,?,?,?,?,?,?) ON CONFLICT(account_id,user_id,agent_id)
            DO UPDATE SET messages_json=excluded.messages_json,
                pending_graph_json=excluded.pending_graph_json,
                pending_profile_json=excluded.pending_profile_json,updated_at=excluded.updated_at',
            [$accountId,$userId,$agentId,$encoded,$graph,$profile,now()]);
    }
}
/** Explain the actual editor graph, including EVERY reachable branch, without AI guesses. */
function flow_ai_explain_graph(array $graph):string {
    if(!isset($graph['nodes'],$graph['edges'])||!is_array($graph['nodes'])||
        !is_array($graph['edges'])||count($graph['nodes'])>100||count($graph['edges'])>250)
        throw new InvalidArgumentException('مخطط غير صالح للشرح');
    flow_preview_compile($graph);
    $nodes=[];$outgoing=[];
    foreach($graph['nodes'] as $node)$nodes[(string)$node['id']]=$node;
    foreach($graph['edges'] as $edge)$outgoing[(string)$edge['from']][]=$edge;
    $start=null;
    foreach($graph['nodes'] as $n)if(($n['type']??'')==='start'){$start=$n;break;}
    $types=['start'=>'بداية','say'=>'رد أو معلومة','listen'=>'استماع للعميل',
        'ask'=>'سؤال للعميل','instruction'=>'تعليمات داخلية للإيجنت',
        'condition'=>'تفرّع حسب الإجابة','end'=>'إنهاء المحادثة',
        'knowledge_lookup'=>'بحث في المعرفة','collect_field'=>'جمع بيانات',
        'crm_lookup'=>'قراءة معلومات من CRM','crm_write'=>'طلب تحديث CRM',
        'schedule'=>'طلب حجز موعد','handoff'=>'تحويل لموظف'];
    require_once __DIR__.'/FlowAiQuality.php';
    $metrics=flow_ai_graph_quality($graph);
    $parts=['قرأت المخطط الحالي: '.count($nodes).' خطوة و'.count($graph['edges']).
        ' رابط؛ '.$metrics['branch_points'].' تشعبات و'.$metrics['reconvergence_points'].' نقاط التقاء.'];
    $queue=$start!==null?[(string)$start['id']]:[];
    $seen=[];$steps=0;
    while($queue&&$steps<100){
        $id=array_shift($queue);
        if(isset($seen[$id])||!isset($nodes[$id]))continue;
        $seen[$id]=true;$steps++;
        $node=$nodes[$id];
        $type=(string)$node['type'];
        $parts[]='• '.($types[$type]??'خطوة').': '.mb_substr(trim((string)$node['text']),0,115);
        foreach($outgoing[$id]??[] as $edge){
            $label=trim((string)($edge['label']??''));
            $target=$nodes[$edge['to']]??null;
            if($target!==null){
                $parts[]='  ↳ '.($label!==''?'لو «'.mb_substr($label,0,45).'»':'المسار الافتراضي').
                    ' → '.($types[$target['type']]??'خطوة').': '.mb_substr(trim((string)$target['text']),0,70);
                if(!isset($seen[$edge['to']]))$queue[]=(string)$edge['to'];
            }
        }
    }
    if(count($seen)<count($nodes))$parts[]='• فيه عقد مش متصلة بالمسار الأساسي؛ محتاجة مراجعة قبل النشر.';
    $parts[]='أي حجز أو تعديل على نظام خارجي لا يتم لمجرد وجود عقدة؛ لازم تكامل فعلي وصلاحيات وتنفيذ مؤكد.';
    return mb_substr(implode("\n",$parts),0,7000);
}
