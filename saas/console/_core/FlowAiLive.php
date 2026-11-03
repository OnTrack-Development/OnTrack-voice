<?php
declare(strict_types=1);

/**
 * Server-side Gemini 3.8 Live Extended Thinking adapter for a TEXT editor.
 * Live 3.8 does not support responseMimeType/responseSchema. Use an explicitly
 * NON_BLOCKING tool call as a structured PROPOSAL (never run external actions).
 * The model and API credential never reach the browser.
 */
function flow_ai_live_model():string { return 'gemini-3.8-live-extended-thinking'; }

function flow_ai_live_setup(string $instruction,array $schema):array {
    return ['setup'=>[
        'model'=>'models/'.flow_ai_live_model(),
        'systemInstruction'=>['parts'=>[['text'=>$instruction]]],
        'generationConfig'=>[
            'responseModalities'=>['AUDIO'],
            'thinkingConfig'=>['thinkingLevel'=>'MEDIUM']
        ],
        // BidiGenerateContentSetup.outputAudioTranscription is a TOP-LEVEL
        // setup field, NOT generationConfig.outputAudioTranscription.
        // Sending it in generationConfig causes an invalid Live setup and
        // the peer closes the session without a structured proposal.
        'outputAudioTranscription'=>new stdClass(),
        'tools'=>[['functionDeclarations'=>[[
            'name'=>'submit_editor_proposal',
            'description'=>'Return the complete proposed editor graph or settings as structured arguments. Do not execute any change; the user must review and apply the draft.',
            'behavior'=>'NON_BLOCKING',
            'parameters'=>$schema
        ]]]]
    ]];
}
/** Proven text-turn input for Gemini Live. Realtime input is for a stream of
 * media chunks and may never signal a completed text turn to the model.
 */
function flow_ai_live_text_turn(string $text):array {
    return ['clientContent'=>['turns'=>[['role'=>'user','parts'=>[['text'=>$text]]]],
        'turnComplete'=>true]];
}
/** Transcription is output of AUDIO response; audio payload is discarded.
 * Never interpret the user's inputTranscription as a model reply.
 */
function flow_ai_live_transcribed_chunk(array $event):string {
    $value=$event['serverContent']['outputTranscription']['text']??'';
    return is_string($value)?$value:'';
}
/** A speaking model may return JSON as text/transcript rather than calling the
 * proposal function. Only a complete structurally valid object is accepted.
 */
function flow_ai_live_parse_text_graph(string $text):?array {
    $text=trim($text);
    if($text===''||strlen($text)>38000)return null;
    $text=preg_replace('/\\A```(?:json)?\\s*|\\s*```\\z/i','',$text);
    try{$graph=json_decode($text,true,40,JSON_THROW_ON_ERROR);}
    catch(JsonException){return null;}
    return is_array($graph)&&!array_is_list($graph)?$graph:null;
}
function flow_ai_live_extract(array $message,string $toolName='submit_editor_proposal'):?array {
    foreach((array)($message['toolCall']['functionCalls']??[]) as $call){
        if(!is_array($call)||($call['name']??null)!==$toolName)continue;
        $args=$call['args']??null;
        if(!is_array($args)||array_is_list($args))
            throw new RuntimeException('مساعد AI رجّع مقترحًا غير صالح.');
        return $args;
    }
    return null;
}
function flow_ai_ws_write($socket,array $message):void {
    $payload=json_encode($message,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $length=strlen($payload);
    if($length>100000)throw new RuntimeException('ai_live_request_too_large');
    $mask=random_bytes(4);
    $head=chr(0x81).($length<126?chr(0x80|$length):($length<=65535?
        chr(0x80|126).pack('n',$length):chr(0x80|127).pack('NN',0,$length)));
    $masked='';
    for($i=0;$i<$length;$i++)$masked.=$payload[$i]^$mask[$i%4];
    $frame=$head.$mask.$masked;
    $sent=0;$total=strlen($frame);
    while($sent<$total){
        $n=fwrite($socket,substr($frame,$sent));
        if($n===false||$n===0)throw new RuntimeException('ai_live_write_failed');
        $sent+=$n;
    }
}
function flow_ai_ws_exact($socket,int $length,?float $deadline=null):string {
    if($length<0||$length>150000)throw new RuntimeException('ai_live_frame_too_large');
    $data='';$deadline??=microtime(true)+140;
    while(strlen($data)<$length){
        if(microtime(true)>=$deadline)throw new RuntimeException('ai_live_result_timeout');
        $part=fread($socket,$length-strlen($data));
        if(!is_string($part)||$part===''||feof($socket)){
            if(feof($socket))throw new RuntimeException('ai_live_connection_lost');
            $meta=stream_get_meta_data($socket);
            if(!empty($meta['timed_out']) && microtime(true)<$deadline)continue;
            throw new RuntimeException('ai_live_read_failed');
        }
        $data.=$part;
    }
    return $data;
}
function flow_ai_ws_read($socket,?float $deadline=null):array {
    $head=flow_ai_ws_exact($socket,2,$deadline);
    $first=ord($head[0]);$second=ord($head[1]);
    $opcode=$first&0x0f;$masked=($second&0x80)!==0;$length=$second&0x7f;
    if($length===126)$length=unpack('n',flow_ai_ws_exact($socket,2,$deadline))[1];
    if($length===127){
        $parts=unpack('Nhigh/Nlow',flow_ai_ws_exact($socket,8,$deadline));
        if($parts['high']!==0)throw new RuntimeException('ai_live_frame_too_large');
        $length=$parts['low'];
    }
    if($length>150000)throw new RuntimeException('ai_live_frame_too_large');
    $mask=$masked?flow_ai_ws_exact($socket,4,$deadline):'';
    $body=flow_ai_ws_exact($socket,$length,$deadline);
    if($masked)for($i=0;$i<$length;$i++)$body[$i]=$body[$i]^$mask[$i%4];
    return [$opcode,$body,($first&0x80)!==0];
}
function flow_ai_live_exchange(string $apiKey,array $setup,string $userText,string $toolName='submit_editor_proposal'):array {
    if(!preg_match('/\A[A-Za-z0-9._~-]{4,256}\z/D',$apiKey))
        throw new RuntimeException('مفتاح منصة AI غير مهيأ.');
    $host='generativelanguage.googleapis.com';
    $ctx=stream_context_create(['ssl'=>[
        'verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$host,
        'SNI_enabled'=>true,'SNI_server_name'=>$host
    ]]);
    $socket=@stream_socket_client('tls://'.$host.':443',$errno,$errstr,6,STREAM_CLIENT_CONNECT,$ctx);
    if(!is_resource($socket))throw new RuntimeException('تعذر فتح اتصال Live آمن مع Google.');
    try{
        stream_set_timeout($socket,18);
        $key=base64_encode(random_bytes(16));
        $path='/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key='.rawurlencode($apiKey);
        $request="GET ".$path." HTTP/1.1\r\nHost: ".$host."\r\n".
            "Upgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: ".$key."\r\n".
            "Sec-WebSocket-Version: 13\r\n\r\n";
        if(fwrite($socket,$request)!==strlen($request))
            throw new RuntimeException('ai_live_handshake_failed');
        $status=fgets($socket,512);
        if(!is_string($status)||!preg_match('~^HTTP/1\.[01] 101 ~',$status))
            throw new RuntimeException('Google Live رفض الاتصال؛ تحقق من صلاحيات مفتاح API والموديل.');
        $headers=[];$headerLen=0;
        while(($line=fgets($socket,1024))!==false && trim($line)!==''){
            $headerLen+=strlen($line);
            if($headerLen>8192)throw new RuntimeException('ai_live_headers_too_large');
            [$name,$value]=array_pad(explode(':',$line,2),2,'');
            $headers[strtolower(trim($name))]=trim($value);
        }
        $accept=base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11',true));
        if(!hash_equals($accept,(string)($headers['sec-websocket-accept']??'')))
            throw new RuntimeException('Google Live WebSocket handshake غير صالح.');
        flow_ai_ws_write($socket,$setup);
        $initialized=false;$started=false;$frames=0;$deadline=microtime(true)+140;
        stream_set_timeout($socket,6);
        $lastEvent='handshake';$transcript='';$modelText='';
        while($frames++<900 && microtime(true)<$deadline){
            try{[$opcode,$body,$last]=flow_ai_ws_read($socket,$deadline);}
            catch(RuntimeException $error){
                throw new RuntimeException($error->getMessage().' [last='. $lastEvent.',frames='.$frames.']');
            }
            if($opcode===8){
                // A close frame contains a two-byte RFC6455 code followed by
                // optional UTF-8 reason. Never expose the full provider text:
                // it may echo request data. The code and stage are sufficient.
                $closeCode=strlen($body)>=2?unpack('n',substr($body,0,2))[1]:1005;
                $step=$initialized?'بعد إرسال طلب المقترح':'أثناء إعداد جلسة Live';
                throw new RuntimeException(
                    'Google Live أغلق الاتصال '.$step.' (WebSocket '.$closeCode.
                    '؛ last='.$lastEvent.'). لم يتم تعديل المخطط أو الخصائص.'
                );
            }
            if($opcode===9){ // ping -> masked pong
                $mask=random_bytes(4);$size=strlen($body);
                if($size>125)throw new RuntimeException('ai_live_ping_invalid');
                $masked='';for($i=0;$i<$size;$i++)$masked.=$body[$i]^$mask[$i%4];
                fwrite($socket,chr(0x8a).chr(0x80|$size).$mask.$masked);continue;
            }
            // Live servers may deliver JSON in either text or binary WebSocket frames.
            if(!in_array($opcode,[1,2],true)||!$last)continue;
            $event=json_decode($body,true,32);
            if(!is_array($event))continue;
            if(isset($event['setupComplete'])&&!$started){
                $initialized=true;$started=true;
                // Discrete text turn with explicit completion; do not stream
                // realtimeInput (the earlier editor adapter stalled here).
                flow_ai_ws_write($socket,flow_ai_live_text_turn($userText));
                $lastEvent='text_sent';continue;
            }
            if(!$initialized)continue;
            $lastEvent=isset($event['serverContent'])?'server_content':
                (isset($event['toolCall'])?'tool_call':(isset($event['goAway'])?'go_away':'other'));
            $toolResponse=flow_ai_live_extract($event,$toolName);
            if($toolResponse!==null)return $toolResponse;
            $transcriptChunk=flow_ai_live_transcribed_chunk($event);
            if($transcriptChunk!=='' && strlen($transcript)<38000)
                $transcript.=substr($transcriptChunk,0,38000-strlen($transcript));
            // Rare text parts also arrive as modelTurn parts with no audio.
            foreach((array)($event['serverContent']['modelTurn']['parts']??[]) as $part){
                if(!is_array($part)||!is_string($part['text']??null))continue;
                $modelText.=substr((string)$part['text'],0,max(0,38000-strlen($modelText)));
                $candidate=flow_ai_live_parse_text_graph($modelText);
                if($candidate!==null)return $candidate;
            }
            if(!empty($event['serverContent']['turnComplete'])){
                $candidate=flow_ai_live_parse_text_graph($modelText!==''?$modelText:$transcript);
                if($candidate!==null)return $candidate;
            }
            $state=(string)($event['interactionStatus']??$event['serverContent']['interactionStatus']??'');
            if($state==='IDLE')break;
        }
        $candidate=flow_ai_live_parse_text_graph($transcript);
        if($candidate!==null)return $candidate;
        throw new RuntimeException('انتهت مهلة معالجة Gemini Live دون اقتراح منظّم؛ لم يتم تعديل المسودة.');
    }finally{fclose($socket);}
}
function flow_ai_live_proposal(string $system,string $prompt,array $schema,?callable $transport=null):array {
    $setup=flow_ai_live_setup($system."\nOnly answer by calling submit_editor_proposal with every required field. Never produce spoken text or claim you executed changes.",$schema);
    if($transport!==null){
        $event=$transport($setup,flow_ai_live_text_turn($prompt));
        if(!is_array($event))throw new RuntimeException('ai_live_mock_invalid');
        $args=flow_ai_live_extract($event);
        if($args===null)throw new RuntimeException('مساعد Live لم يُنشئ المقترح المطلوب.');
        return $args;
    }
    require_once __DIR__.'/TenantVoiceSession.php';
    $provider=tenant_voice_provider();
    return flow_ai_live_exchange($provider['key'],$setup,$prompt);
}
