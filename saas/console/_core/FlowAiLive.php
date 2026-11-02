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
        'tools'=>[['functionDeclarations'=>[[
            'name'=>'submit_editor_proposal',
            'description'=>'Return the complete proposed editor graph or settings as structured arguments. Do not execute any change; the user must review and apply the draft.',
            'behavior'=>'NON_BLOCKING',
            'parameters'=>$schema
        ]]]]
    ]];
}
function flow_ai_live_extract(array $message):?array {
    foreach((array)($message['toolCall']['functionCalls']??[]) as $call){
        if(!is_array($call)||($call['name']??null)!=='submit_editor_proposal')continue;
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
function flow_ai_ws_exact($socket,int $length):string {
    if($length<0||$length>150000)throw new RuntimeException('ai_live_frame_too_large');
    $data='';
    while(strlen($data)<$length){
        $part=fread($socket,$length-strlen($data));
        if(!is_string($part)||$part===''||feof($socket)){
            $meta=stream_get_meta_data($socket);
            throw new RuntimeException(!empty($meta['timed_out'])?'ai_live_read_timeout':'ai_live_connection_lost');
        }
        $data.=$part;
    }
    return $data;
}
function flow_ai_ws_read($socket):array {
    $head=flow_ai_ws_exact($socket,2);
    $first=ord($head[0]);$second=ord($head[1]);
    $opcode=$first&0x0f;$masked=($second&0x80)!==0;$length=$second&0x7f;
    if($length===126)$length=unpack('n',flow_ai_ws_exact($socket,2))[1];
    if($length===127){
        $parts=unpack('Nhigh/Nlow',flow_ai_ws_exact($socket,8));
        if($parts['high']!==0)throw new RuntimeException('ai_live_frame_too_large');
        $length=$parts['low'];
    }
    if($length>150000)throw new RuntimeException('ai_live_frame_too_large');
    $mask=$masked?flow_ai_ws_exact($socket,4):'';
    $body=flow_ai_ws_exact($socket,$length);
    if($masked)for($i=0;$i<$length;$i++)$body[$i]=$body[$i]^$mask[$i%4];
    return [$opcode,$body,($first&0x80)!==0];
}
function flow_ai_live_exchange(string $apiKey,array $setup,string $userText):array {
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
        stream_set_timeout($socket,24);
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
        $initialized=false;$started=false;$frames=0;$startedAt=microtime(true);
        $lastEvent='handshake';$toolResponse=null;
        while($frames++<120 && microtime(true)-$startedAt<27){
            try{[$opcode,$body,$last]=flow_ai_ws_read($socket);}
            catch(RuntimeException $error){
                throw new RuntimeException($error->getMessage().' [last='. $lastEvent.',frames='.$frames.']');
            }
            if($opcode===8)throw new RuntimeException('Google Live أغلق الجلسة قبل إنشاء المقترح.');
            if($opcode===9){ // ping -> masked pong
                $mask=random_bytes(4);$size=strlen($body);
                if($size>125)throw new RuntimeException('ai_live_ping_invalid');
                $masked='';for($i=0;$i<$size;$i++)$masked.=$body[$i]^$mask[$i%4];
                fwrite($socket,chr(0x8a).chr(0x80|$size).$mask.$masked);continue;
            }
            if($opcode!==1||!$last)continue;
            $event=json_decode($body,true,32);
            if(!is_array($event))continue;
            if(isset($event['setupComplete'])&&!$started){
                $initialized=true;$started=true;
                // The raw Live API's documented text-input message.
                flow_ai_ws_write($socket,['realtimeInput'=>['text'=>$userText]]);
                $lastEvent='text_sent';continue;
            }
            if(!$initialized)continue;
            $lastEvent=isset($event['serverContent'])?'server_content':
                (isset($event['toolCall'])?'tool_call':(isset($event['goAway'])?'go_away':'other'));
            $toolResponse=flow_ai_live_extract($event);
            if($toolResponse!==null)return $toolResponse;
            $state=(string)($event['interactionStatus']??$event['serverContent']['interactionStatus']??'');
            if($state==='IDLE')break;
        }
        throw new RuntimeException('مساعد Live لم يُرجع مقترحًا منظّمًا؛ لم يتم تعديل المسودة.');
    }finally{fclose($socket);}
}
function flow_ai_live_proposal(string $system,string $prompt,array $schema,?callable $transport=null):array {
    $setup=flow_ai_live_setup($system."\nOnly answer by calling submit_editor_proposal with every required field. Never produce spoken text or claim you executed changes.",$schema);
    if($transport!==null){
        $event=$transport($setup,['clientContent'=>['turns'=>[['role'=>'user','parts'=>[['text'=>$prompt]]]],'turnComplete'=>true]]);
        if(!is_array($event))throw new RuntimeException('ai_live_mock_invalid');
        $args=flow_ai_live_extract($event);
        if($args===null)throw new RuntimeException('مساعد Live لم يُنشئ المقترح المطلوب.');
        return $args;
    }
    require_once __DIR__.'/TenantVoiceSession.php';
    $provider=tenant_voice_provider();
    return flow_ai_live_exchange($provider['key'],$setup,$prompt);
}
