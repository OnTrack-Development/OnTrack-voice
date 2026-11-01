<?php
declare(strict_types=1);
require __DIR__.'/_core/Bootstrap.php';
require __DIR__.'/_core/FlowPreview.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'");
function flow_preview_answer(int $status,array $payload):never {
    http_response_code($status);
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')
    flow_preview_answer(405,['ok'=>false,'error'=>'method_not_allowed']);
if(PHP_SAPI!=='cli' && (empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'))
    flow_preview_answer(426,['ok'=>false,'error'=>'https_required']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>18000)
    flow_preview_answer(413,['ok'=>false,'error'=>'preview_too_large']);
startsession();
try{
    init_schema();
    $user=me();$account=mine();
    if(!$user||!$account||!allowed('agents.write',$account))
        flow_preview_answer(403,['ok'=>false,'error'=>'not_authorized']);
    if(!hash_equals(csrf(),(string)($_POST['csrf']??'')))
        flow_preview_answer(419,['ok'=>false,'error'=>'csrf_expired']);
    $accountId=(int)$account['account_id'];
    $agentId=filter_var($_POST['agent_id']??null,FILTER_VALIDATE_INT);
    if($agentId===false||$agentId===null||$agentId<1
        ||!flow_preview_agent_for_account($accountId,(int)$agentId))
        flow_preview_answer(404,['ok'=>false,'error'=>'agent_not_found']);
    $json=$_POST['flow_json']??null;
    if(!is_string($json)||strlen($json)>16000)
        flow_preview_answer(422,['ok'=>false,'error'=>'flow_invalid_graph']);
    $graph=json_decode($json,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($graph))throw new InvalidArgumentException('flow_invalid_graph');
    $flow=flow_preview_compile($graph);
    $hash=hash('sha256',$json);
    $operation=(string)($_POST['operation']??'');
    $now=time();
    $sessions=$_SESSION['flow_previews']??[];
    if(!is_array($sessions))$sessions=[];
    foreach($sessions as $key=>$item){
        if(!is_array($item)||($item['created_at']??0)<$now-600)unset($sessions[$key]);
    }
    if($operation==='start'){
        // Each browser session gets at most four bounded previews.
        if(count($sessions)>=4)$sessions=array_slice($sessions,-3,null,true);
        $previewId=bin2hex(random_bytes(16));
        $result=flow_preview_step($flow,flow_preview_initial($flow));
        $sessions[$previewId]=['account_id'=>$accountId,'agent_id'=>$agentId,
            'graph_hash'=>$hash,'created_at'=>$now,'state'=>$result['state']];
    }elseif($operation==='reply'){
        $previewId=(string)($_POST['preview_id']??'');
        if(!preg_match('/^[a-f0-9]{32}$/D',$previewId))
            flow_preview_answer(409,['ok'=>false,'error'=>'preview_expired']);
        $session=$sessions[$previewId]??null;
        if(!is_array($session)||($session['account_id']??null)!==$accountId
            ||($session['agent_id']??null)!==$agentId
            ||!hash_equals((string)($session['graph_hash']??''),$hash))
            flow_preview_answer(409,['ok'=>false,'error'=>'preview_expired_or_changed']);
        $reply=$_POST['reply']??null;
        if(!is_string($reply))throw new InvalidArgumentException('flow_answer_required');
        $result=flow_preview_step($flow,$session['state'],$reply);
        $sessions[$previewId]['state']=$result['state'];
    }else{
        flow_preview_answer(422,['ok'=>false,'error'=>'invalid_operation']);
    }
    $_SESSION['flow_previews']=$sessions;
    flow_preview_answer(200,['ok'=>true,'preview_id'=>$previewId,
        'events'=>$result['events'],'waiting'=>$result['waiting'],'done'=>$result['done'],
        'mode'=>'text_preview_no_ai_no_external_actions']);
}catch(JsonException|InvalidArgumentException $e){
    flow_preview_answer(422,['ok'=>false,'error'=>'invalid_flow_or_reply']);
}catch(Throwable $e){
    flow_preview_answer(503,['ok'=>false,'error'=>'preview_unavailable']);
}
