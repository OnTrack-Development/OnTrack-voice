<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/_core/FlowAiLive.php';
require_once __DIR__.'/../saas/console/_core/FlowAi.php';
function live_check(bool $condition,string $reason):void {
    if(!$condition)throw new RuntimeException('LIVE_THINKING_TEST_FAIL: '.$reason);
}
live_check(flow_ai_model()==='gemini-3.8-live-extended-thinking','editor uses 3.8 Live Extended Thinking with text turns');
putenv('ONTRACK_GEMINI_FLOW_MODEL=gemini-3.5-flash-lite');
live_check(flow_ai_model()==='gemini-3.8-live-extended-thinking','legacy setting never overrides 3.8 Live editor');
putenv('ONTRACK_GEMINI_FLOW_MODEL');
$schema=['type'=>'OBJECT','properties'=>[
    'nodes'=>['type'=>'ARRAY','items'=>['type'=>'OBJECT']],
    'edges'=>['type'=>'ARRAY','items'=>['type'=>'OBJECT']]
],'required'=>['nodes','edges']];
$binary=json_encode(['setupComplete'=>(object)[]],JSON_THROW_ON_ERROR);
$length=strlen($binary);
$socket=fopen('php://temp','w+b');
fwrite($socket,chr(0x82).chr($length).$binary);
rewind($socket);
[$opcode,$body,$final]=flow_ai_ws_read($socket);
fclose($socket);
live_check($opcode===2 && $final===true && json_decode($body,true)===['setupComplete'=>[]],
    'binary Live WebSocket JSON frame decoded');
$setup=flow_ai_live_setup('Only return proposal',$schema);
live_check($setup['setup']['model']==='models/gemini-3.8-live-extended-thinking','Google Live setup model');
live_check($setup['setup']['generationConfig']['thinkingConfig']['thinkingLevel']==='MEDIUM','extended thinking enabled');
live_check($setup['setup']['generationConfig']['responseModalities']===['AUDIO'],'official Live modality');
live_check(!isset($setup['setup']['generationConfig']['responseSchema']),'no unsupported response schema');
live_check(isset($setup['setup']['outputAudioTranscription']) &&
    !isset($setup['setup']['generationConfig']['outputAudioTranscription']),
    'transcription MUST be top-level BidiGenerateContentSetup, not generationConfig');
live_check($setup['setup']['tools'][0]['functionDeclarations'][0]['behavior']==='NON_BLOCKING','async-only tool');
$graph=['nodes'=>[
 ['id'=>'start','type'=>'start','text'=>'أهلاً','x'=>200,'y'=>80],
 ['id'=>'end','type'=>'end','text'=>'شكرًا','x'=>200,'y'=>300]
], 'edges'=>[['from'=>'start','to'=>'end','label'=>'التالي']]];
$transport=static function(array $configured,array $request)use($graph):array {
    live_check(($configured['setup']['model']??'')==='models/gemini-3.8-live-extended-thinking','runtime model');
    live_check(($request['clientContent']['turns'][0]['parts'][0]['text']??null)==='أهلا' && ($request['clientContent']['turnComplete']??false)===true,'text sent as explicit completed Live turn');
    return ['toolCall'=>['functionCalls'=>[['name'=>'submit_editor_proposal','args'=>$graph]]]];
};
$result=flow_ai_live_proposal('Build a flow','أهلا',$schema,$transport);
live_check(flow_ai_validate_proposal($result)===$graph,'tool proposal passes graph validator');
live_check(flow_ai_live_extract(['serverContent'=>['interactionStatus'=>'IN_PROGRESS']])===null,'filler never treated as result');
$failed=false;
try{flow_ai_live_proposal('test','test',$schema,static fn()=>['serverContent'=>['modelTurn'=>['parts'=>[['text'=>'not json']]]]]);}
catch(RuntimeException){$failed=true;}
live_check($failed,'no output without proper tool call');
$failed=false;
try{flow_ai_live_extract(['toolCall'=>['functionCalls'=>[['name'=>'submit_editor_proposal','args'=>['bad']]]]]);}
catch(RuntimeException){$failed=true;}
live_check($failed,'malformed tool args rejected');
for($attempt=1;$attempt<=2;$attempt++){
    $retry=flow_ai_live_retry_turn($attempt);
    live_check(($retry['clientContent']['turnComplete']??null)===true &&
        str_contains((string)($retry['clientContent']['turns'][0]['parts'][0]['text']??''),'submit_editor_proposal'),
        'a no-tool IDLE re-prompts the same Live TEXT session with bounded tool-only request');
}
$badRetry=false;
try{flow_ai_live_retry_turn(3);}catch(InvalidArgumentException){$badRetry=true;}
live_check($badRetry,'live tool retry is limited to two turns');
live_check(flow_ai_live_transcribed_chunk(['serverContent'=>['outputTranscription'=>['text'=>'رسالة من المساعد']]])==='رسالة من المساعد',
    'audio output transcript captured as assistant text');
live_check(flow_ai_live_transcribed_chunk(['serverContent'=>['inputTranscription'=>['text'=>'صوت المستخدم']]])==='',
    'do not mistake user transcript for assistant result');
live_check(flow_ai_live_parse_text_graph(json_encode($graph,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))===$graph,
    'strict structured JSON can be extracted from output transcription');
live_check(flow_ai_live_parse_text_graph('رد عام غير منظم')===null,'ordinary speech is not accepted as a graph');
live_check(($setup['setup']['generationConfig']['responseModalities']??[])===['AUDIO'],
    'do not request unsupported TEXT response modality');
echo "FLOW_CHAT_GEMINI_3_8_LIVE_THINKING_TOOL_PROTOCOL_PASS\n";
