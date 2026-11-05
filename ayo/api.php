<?php
declare(strict_types=1);
/* AYO Voice Lab: isolated PHP HTTP streaming demo. No credentials are committed. */
require_once __DIR__.'/voice-expression.php';
require_once __DIR__.'/secure-vault.php';
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
ini_set('session.cookie_path', '/ayo');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
session_start();
if (!isset($_SESSION['ayo_csrf'])) $_SESSION['ayo_csrf'] = bin2hex(random_bytes(20));
function fail(string $message, int $code=400): never { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error'=>$message], JSON_UNESCAPED_UNICODE); exit; }
function json_out(array $data): never { header('Content-Type: application/json; charset=utf-8'); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function body(): array { $raw=file_get_contents('php://input'); if (strlen((string)$raw)>24000) fail('الطلب أكبر من الحد'); $data=json_decode((string)$raw,true); return is_array($data)?$data:[]; }
function emit(array $event): void { echo json_encode($event,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES)."\n"; if (ob_get_level()>0) @ob_flush(); flush(); }
function char_len(string $s): int { if(function_exists('mb_strlen')) return mb_strlen($s,'UTF-8'); return preg_match_all('/./us',$s) ?: strlen($s); }
function char_sub(string $s,int $length): string { if(function_exists('mb_substr')) return mb_substr($s,0,$length,'UTF-8'); preg_match_all('/./us',$s,$m); return implode('',array_slice($m[0]??[],0,$length)); }
function envkey(string $key): string { $v=getenv($key); return $v===false?'':trim($v); }
function cfg(): array {
 $saved=$_SESSION['ayo_config'] ?? [];
 $privateKeys=ayo_saved_keys();
 return array_merge(['voiceId'=>'silver-quail-7878__ayo','ttsModel'=>'inworld-tts-2','ttsDelivery'=>'BALANCED','ttsLocale'=>'ar-EG','emotionMode'=>'expressive','llmProvider'=>'apinex','llmModel'=>'free/gpt-6-luna'], $saved, [
  'inworldKey'=>ayo_is_owner() ? ($privateKeys['inworld'] ?? ($_SESSION['ayo_keys']['inworld'] ?? envkey('INWORLD_API_KEY'))) : '',
  'apinexKey'=>ayo_is_owner() ? ($privateKeys['apinex'] ?? ($_SESSION['ayo_keys']['apinex'] ?? envkey('APINEX_API_KEY'))) : '',
  'omniraKey'=>ayo_is_owner() ? ($privateKeys['omnira'] ?? ($_SESSION['ayo_keys']['omnira'] ?? envkey('OMNIRA_API_KEY'))) : ''
 ]);
}
function public_cfg(array $c): array {return ['voiceId'=>$c['voiceId'],'ttsModel'=>$c['ttsModel'],'ttsDelivery'=>$c['ttsDelivery'],'ttsLocale'=>$c['ttsLocale'],'emotionMode'=>$c['emotionMode'],'llmProvider'=>$c['llmProvider'],'llmModel'=>$c['llmModel'],'hasInworldKey'=>($c['inworldKey']!==''),'hasActiveLlmKey'=>($c[$c['llmProvider']==='apinex'?'apinexKey':'omniraKey']!==''),'hasOmniraKey'=>($c['omniraKey']!==''),'hasApinexKey'=>($c['apinexKey']!==''),'defaultSttMode'=>'browser','ttsPrice'=>$c['ttsModel']==='inworld-tts-2'?25:15,'maxSeconds'=>120]; }
function prompt(): string {
 $k=json_decode((string)file_get_contents(__DIR__.'/ontrack.json'),true) ?: [];
 $plans=[]; foreach($k['plans']??[] as $p){$plans[]=$p['name'].': $'.$p['monthly_usd'].' شهريًا، $'.$p['yearly_usd'].' سنويًا؛ '.$p['details'];}
 return 'أنت آيو AYO، مساعدة المبيعات الصوتية التجريبية لشركة أون تراك OnTrack Development. تكلمي بالمصري الطبيعي وبجملتين قصيرتين كحد أقصى. لا تدعي أنك إنسان أو تؤكدي طلبات لم تنفذ. لا تخترعي أسعارًا أو مواصفات. اذكري أن الأسعار قابلة للتغيير وأن الشراء النهائي من بوابة العملاء. عند سؤال محدد عن حساب العميل وجهيه للدعم. لا تنطقي روابط كاملة. معلومات الشركة: '.implode('؛ ', $k['general']??[]).'\nالباقات وأسعارها: '.implode('؛ ',$plans).'\nبوابة العملاء: '.($k['customer_portal']??'https://services.ontrackegy.com/');
}
function require_post(): void {
 if ($_SERVER['REQUEST_METHOD']!=='POST') fail('الطريقة غير مدعومة',405);
 if (($_SERVER['HTTP_X_AYO_CSRF']??'')!==($_SESSION['ayo_csrf']??'')) fail('جلسة الصفحة غير متزامنة؛ حدّث الصفحة',403);
 $origin=$_SERVER['HTTP_ORIGIN']??''; if($origin!==''){
  $parts=parse_url($origin); if(!$parts || strcasecmp($parts['host']??'',explode(':',$_SERVER['HTTP_HOST']??'')[0])!==0) fail('Origin غير مسموح',403);
 }
}
function stream_headers(): void { header('Content-Type: application/x-ndjson; charset=utf-8'); header('X-Accel-Buffering: no'); header('Cache-Control: no-store, no-transform'); @ini_set('zlib.output_compression','0'); while(ob_get_level()>0) @ob_end_flush(); }
function llm_info(array $c): array {return match($c['llmProvider']){
 'omnira-a'=>['url'=>'https://ai.omnira-ai.net/v1/chat/completions','key'=>$c['omniraKey']],
 'omnira-b'=>['url'=>'https://v2.omnira-ai.net/v1/chat/completions','key'=>$c['omniraKey']],
 default=>['url'=>'https://api.apinex.bond/v1/chat/completions','key'=>$c['apinexKey']]
};}
function curl_base(string $url, array $headers, string $post): CurlHandle {
 if(!extension_loaded('curl')) throw new RuntimeException('PHP cURL غير مفعل على الاستضافة');
 $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>12,CURLOPT_TIMEOUT=>75,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,CURLOPT_RETURNTRANSFER=>false,CURLOPT_BUFFERSIZE=>4096]);return $ch;
}
function decodeSse(string $line, callable $cb): void {
 if(!str_starts_with($line,'data:'))return;$raw=trim(substr($line,5));if($raw==='' || $raw==='[DONE]')return;
 $d=json_decode($raw,true);if(!is_array($d))return;
 $delta=$d['choices'][0]['delta']['content']??null;
 if(is_string($delta)&&$delta!=='')$cb($delta);
 elseif(is_array($delta)){foreach($delta as $part)if(($part['type']??'')==='text'&&isset($part['text']))$cb((string)$part['text']);}
}
function llm_plain_text(array $d): string {
 $message=$d['choices'][0]['message']??[];
 $content=$message['content']??($d['output_text']??'');
 if(is_string($content))return trim($content);
 if(is_array($content)){
  $parts=[];
  foreach($content as $part){
   if(is_string($part))$parts[]=$part;
   elseif(is_array($part) && isset($part['text']) && is_string($part['text']))$parts[]=$part['text'];
  }
  return trim(implode('',$parts));
 }
 return '';
}
function llm_error_label(int $status,string $provider): string {
 $name=match($provider){'omnira-a'=>'Omnira A','omnira-b'=>'Omnira B',default=>'APInex'};
 if($status===401)return $name.' رفض المصادقة (401). راجع المفتاح المخزن على الاستضافة';
 if($status===403)return $name.' منع الطلب (403). راجع صلاحية الموديل/الحساب أو سياسة الوصول بالبوابة';
 if($status===404)return $name.' لم يجد الموديل أو المسار (404). اختر موديلًا متاحًا لهذه البوابة';
 if($status===429)return $name.' وصل لحد الاستخدام (429). جرّب بوابة أخرى أو راجع الحصة';
 if(in_array($status,[502,503,504],true))return $name.' غير متاح حاليًا (HTTP '.$status.'). مزود الخدمة نفسه أو الخادم الخلفي متعطل';
 return $name.' رفض الطلب (HTTP '.($status?:'network').'). جرّب موديلًا آخر أو افحص الاتصال';
}
function llm_fallback_plain(array $c,array $messages,string $reason): string {
 $p=llm_info($c);
 $payload=json_encode(['model'=>$c['llmModel'],'messages'=>$messages,'stream'=>false,'max_tokens'=>550,'temperature'=>0.55],JSON_UNESCAPED_UNICODE);
 $ch=curl_base($p['url'],['Authorization: Bearer '.$p['key'],'Content-Type: application/json','Accept: application/json'],(string)$payload);
 curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
 curl_setopt($ch,CURLOPT_TIMEOUT,48);
 $started=microtime(true);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 emit(['type'=>'llm_diagnostic','provider'=>$c['llmProvider'],'model'=>$c['llmModel'],'mode'=>'nonstream-fallback','reason'=>$reason,'http'=>$status,'elapsedMs'=>round((microtime(true)-$started)*1000)]);
 if($raw===false||$status>=400)throw new RuntimeException(llm_error_label($status,$c['llmProvider']));
 $doc=json_decode(is_string($raw)?$raw:'',true);
 $text=is_array($doc)?llm_plain_text($doc):'';
 if($text===''){
  $hasReasoning=is_array($doc)&&!empty($doc['choices'][0]['message']['reasoning_content']);
  throw new RuntimeException($hasReasoning?'الموديل رجّع تفكيرًا داخليًا من غير إجابة نهائية. اختار موديل Flash أو غير Thinking':'مزود التفكير رد بدون نص نهائي صالح. اختار موديل Chat Completions آخر');
 }
 return $text;
}
function llm_stream(array $c,array $messages): string {
 $p=llm_info($c);if($p['key']==='') throw new RuntimeException('اضبط مفتاح مزود التفكير المختار');
 $payload=json_encode(['model'=>$c['llmModel'],'messages'=>$messages,'stream'=>true,'max_tokens'=>250,'temperature'=>0.55],JSON_UNESCAPED_UNICODE);
 $ch=curl_base($p['url'],['Authorization: Bearer '.$p['key'],'Content-Type: application/json','Accept: text/event-stream'],(string)$payload);
 $buffer='';$whole='';$start=microtime(true);$first=false;$httpData='';$rawSuccess='';
 curl_setopt($ch,CURLOPT_WRITEFUNCTION,function($handle,$chunk)use(&$buffer,&$whole,&$first,&$httpData,&$rawSuccess,$start){
  if(curl_getinfo($handle,CURLINFO_HTTP_CODE)>=400){$httpData.=substr($chunk,0,2000);return strlen($chunk);}
  if(strlen($rawSuccess)<131072)$rawSuccess.=substr($chunk,0,131072-strlen($rawSuccess));
  $buffer.=$chunk;while(($pos=strpos($buffer,"\n"))!==false){$line=trim(substr($buffer,0,$pos));$buffer=substr($buffer,$pos+1);
   decodeSse($line,function($text)use(&$whole,&$first,$start){if(!$first){emit(['type'=>'latency','metric'=>'llmFirstTokenMs','value'=>round((microtime(true)-$start)*1000)]);$first=true;}$whole.=$text;emit(['type'=>'llm_delta','text'=>$text]);});
  }return strlen($chunk);
 });
 $ok=curl_exec($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 emit(['type'=>'llm_diagnostic','provider'=>$c['llmProvider'],'model'=>$c['llmModel'],'mode'=>'stream','http'=>$status,'elapsedMs'=>round((microtime(true)-$start)*1000)]);
 if($ok===false || $status>=400){
  if(in_array($status,[502,503,504],true))return llm_fallback_plain($c,$messages,'upstream-'.$status);
  throw new RuntimeException(llm_error_label($status,$c['llmProvider']).($ok===false&&$status===0?' · اتصال الشبكة فشل':''));
 } 
 if($buffer!=='')decodeSse(trim($buffer),function($x)use(&$whole){$whole.=$x;emit(['type'=>'llm_delta','text'=>$x]);});
 if(trim($whole)===''){
   $doc=json_decode(trim($rawSuccess),true);
   $fallback=$doc['choices'][0]['message']['content']??null;
   if(is_string($fallback)&&trim($fallback)!==''){$whole=trim($fallback);emit(['type'=>'latency','metric'=>'llmFirstTokenMs','value'=>round((microtime(true)-$start)*1000)]);emit(['type'=>'llm_delta','text'=>$whole]);}
 }
 if(trim($whole)===''){
  $whole=llm_fallback_plain($c,$messages,'empty-stream');
  emit(['type'=>'latency','metric'=>'llmFirstTokenMs','value'=>round((microtime(true)-$start)*1000)]);
  emit(['type'=>'llm_delta','text'=>$whole]);
 }
 emit(['type'=>'llm_done']); return trim($whole);
}
function tts_stream(string $text,array $c,string $callerText=''): void {
 if($c['inworldKey']==='')throw new RuntimeException('اضبط مفتاح Inworld في الإعدادات');
 if(char_len($text)>3500)$text=char_sub($text,3500);
 $styled=ayo_expression_prepare($text,$c['emotionMode'],$c['ttsModel'],$callerText);
 $payload=['text'=>$styled['text'],'voiceId'=>$c['voiceId'],'modelId'=>$c['ttsModel'],'language'=>$c['ttsLocale'],'audioConfig'=>['audioEncoding'=>'PCM','sampleRateHertz'=>24000,'speakingRate'=>1],'deliveryMode'=>$c['ttsDelivery']];
 if ($styled['synthesisContext']!==null) $payload['synthesisContext']=$styled['synthesisContext'];
 emit(['type'=>'voice_style','mode'=>$styled['mode'],'reasoningEnabled'=>$styled['reasoningEnabled'],'cue'=>$styled['nonverbalCue'],'model'=>$c['ttsModel']]);
 $ch=curl_base('https://api.inworld.ai/tts/v1/voice:stream',['Authorization: Basic '.$c['inworldKey'],'Content-Type: application/json','Accept: application/json'],(string)json_encode($payload,JSON_UNESCAPED_UNICODE));
 $buffer='';$started=microtime(true);$audio=false;$httpErr='';$processed=0;$bytes=0;$chunks=0;$streamError=null;
 $handleLine=function(string $line)use(&$audio,&$processed,&$bytes,&$chunks,&$streamError,$started){
 $d=json_decode(trim($line),true);
 if(!is_array($d)){if(trim($line)!=='')$streamError=['code'=>'INVALID_NDJSON'];return;}
  $r=$d['result']??[];
 if(isset($d['error'])){$e=$d['error'];$streamError=['code'=>is_array($e)?(int)($e['code']??0):0,'message'=>is_array($e)?char_sub((string)($e['message']??'Inworld stream error'),120):'Inworld stream error'];return;}
  if(!empty($r['reasoningInstruction'])&&is_string($r['reasoningInstruction']))emit(['type'=>'reasoning_instruction','instruction'=>char_sub($r['reasoningInstruction'],200)]);
  if(isset($r['usage']['processedCharactersCount']))$processed+=(int)$r['usage']['processedCharactersCount'];
  if(!empty($r['audioContent'])){if(!$audio){emit(['type'=>'latency','metric'=>'ttsFirstChunkMs','value'=>round((microtime(true)-$started)*1000)]);$audio=true;}
   $raw=base64_decode((string)$r['audioContent'],true);
   if($raw===false||strlen($raw)%2!==0){$streamError=['code'=>'BAD_PCM_AUDIO'];return;}
   $chunks++;$bytes+=strlen($raw);
   emit(['type'=>'audio','content'=>$r['audioContent'],'encoding'=>'PCM','sampleRate'=>24000,'chunk'=>$chunks,'bytes'=>strlen($raw)]); }
 };
 curl_setopt($ch,CURLOPT_WRITEFUNCTION,function($handle,$chunk)use(&$buffer,&$httpErr,$handleLine){
  if(curl_getinfo($handle,CURLINFO_HTTP_CODE)>=400){$httpErr.=substr($chunk,0,1500);return strlen($chunk);}
  $buffer.=$chunk;while(($pos=strpos($buffer,"\n"))!==false){$line=substr($buffer,0,$pos);$buffer=substr($buffer,$pos+1);if(trim($line)!=='')$handleLine($line);}return strlen($chunk);
 });
 $ok=curl_exec($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if($ok===false||$status>=400)throw new RuntimeException('Inworld HTTP '.($status?:'network').($err?' / اتصال غير متاح':''));
 if(trim($buffer)!=='')$handleLine($buffer);
 if($streamError){emit(['type'=>'tts_diagnostic','status'=>'upstream_error','chunks'=>$chunks,'bytes'=>$bytes,'errorCode'=>$streamError['code']??'UNKNOWN']);throw new RuntimeException('Inworld أوقف التوليد (code '.($streamError['code']??'?').')'.(!empty($streamError['message'])?' · '.$streamError['message']:''));}
 if(!$audio)throw new RuntimeException('Inworld لم يرجع أجزاء صوتية؛ راجع Voice ID والموديل وصيغة الصوت');
 $count=max($processed,char_len($text));
 emit(['type'=>'audio_done','chars'=>$count,'chunks'=>$chunks,'bytes'=>$bytes,'audioSeconds'=>round($bytes/48000,2),'estimatedUsd'=>round($count*($c['ttsModel']==='inworld-tts-2'?25:15)/1000000,6)]);
}
$action=(string)($_GET['action']??'status');
if ($action==='status') {
 $isOwner=ayo_is_owner();
 if ($isOwner) ayo_migrate_legacy_keys();
 json_out(['csrf'=>$_SESSION['ayo_csrf'],'ownerAuthorized'=>$isOwner,'config'=>public_cfg(cfg()),'running'=>isset($_SESSION['ayo_deadline'])&&$_SESSION['ayo_deadline']>time()]);
}
if ($action==='knowledge') { $k=json_decode((string)file_get_contents(__DIR__.'/ontrack.json'),true)?:[]; json_out($k); }
require_post();
if ($action==='auth') {
 $in=body();
 ayo_owner_login(is_string($in['ownerCode']??null) ? $in['ownerCode'] : '');
 ayo_migrate_legacy_keys();
 json_out(['ownerAuthorized'=>true, 'config'=>public_cfg(cfg())]);
}
ayo_require_owner();
if ($action==='transcribe') {
 $raw=file_get_contents('php://input', false, null, 0, 1500000);
 if (!is_string($raw)||strlen($raw)>1100000) fail('التسجيل الصوتي كبير جدًا',413);
 $in=json_decode($raw,true);
 $audio=$in['audio']??null; $kind=$in['encoding']??'';
 if (!is_string($audio)||strlen($audio)<1200||strlen($audio)>1000000||!in_array($kind,['WEBM','OGG_OPUS'],true)) fail('التسجيل غير صالح');
 if (base64_decode($audio,true)===false) fail('ترميز التسجيل غير صالح');
 $key=cfg()['inworldKey']??'';
 if (!$key) fail('Inworld غير مضبوط للتفريغ الصوتي',400);
 $payload=['transcribeConfig'=>['modelId'=>'inworld/inworld-stt-1','audioEncoding'=>$kind,'language'=>'ar'],'audioData'=>['content'=>$audio]];
 $ch=curl_init('https://api.inworld.ai/stt/v1/transcribe');
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Authorization: Basic '.$key,'Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false]);
 $response=curl_exec($ch);$err=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if(!is_string($response)||$http<200||$http>=300) fail('تعذر تحويل الصوت لنص · Inworld HTTP '.($http?:'network'),502);
 $data=json_decode($response,true);$transcript=trim((string)($data['transcription']['transcript']??''));
 json_out(['transcript'=>char_sub($transcript,1500),'engine'=>'Inworld STT','hasText'=>$transcript!=='']);
}
if ($action==='settings') {
 $in=body();$c=cfg();$allowed=['voiceId'=>96,'ttsModel'=>64,'ttsDelivery'=>20,'ttsLocale'=>16,'llmProvider'=>20,'llmModel'=>96,'emotionMode'=>20];
 foreach($allowed as $k=>$limit){if(isset($in[$k])&&is_string($in[$k])){if(strlen($in[$k])>$limit)fail('إعداد أطول من المطلوب');$_SESSION['ayo_config'][$k]=$in[$k];}}
 $c=cfg();if(!in_array($c['ttsModel'],['inworld-tts-2','inworld-tts-2-flash'],true)|| !in_array($c['llmProvider'],['apinex','omnira-a','omnira-b'],true)||!in_array($c['emotionMode'],['off','auto','expressive'],true)||!in_array($c['ttsDelivery'],['BALANCED','STABLE','CREATIVE'],true)||!in_array($c['ttsLocale'],['ar-EG','arz','AUTO'],true))fail('إعدادات غير صالحة');
 $newKeys=[];
 foreach(['inworldKey'=>'inworld','omniraKey'=>'omnira','apinexKey'=>'apinex'] as $inName=>$slot){
   if(!empty($in[$inName])) {
     if(!is_string($in[$inName])||strlen($in[$inName])>2200) fail('مفتاح غير صالح');
     $newKeys[$slot]=trim($in[$inName]);
   }
 }
 if($newKeys) ayo_persist_keys($newKeys);
 json_out(['config'=>public_cfg(cfg()),'keysStoredOnHost'=>true]);
}
if ($action==='start') {$_SESSION['ayo_deadline']=time()+120;$_SESSION['ayo_history']=[];json_out(['expiresIn'=>120]);}
if ($action==='end') {unset($_SESSION['ayo_deadline'],$_SESSION['ayo_history']);json_out(['ended'=>true]);}
if ($action==='clone') {
 if(empty($_POST['consent'])||$_POST['consent']!=='yes')fail('يلزم إثبات ملكية الصوت أو إذن صاحبه');
 $c=cfg();if(!$c['inworldKey'])fail('اضبط مفتاح Inworld');
 if(empty($_FILES['sample']['tmp_name'])||!is_uploaded_file($_FILES['sample']['tmp_name']))fail('اختر ملف عينة صالحًا');
 if(($_FILES['sample']['size']??0)>8*1024*1024||($_FILES['sample']['size']??0)<1024)fail('حجم العينة بين 1KB و8MB');
 $bin=file_get_contents($_FILES['sample']['tmp_name']);$mime=(new finfo(FILEINFO_MIME_TYPE))->buffer((string)$bin);
 if(!in_array($mime,['audio/mpeg','audio/x-wav','audio/wav','audio/vnd.wave','application/octet-stream'],true))fail('ملف MP3 أو WAV فقط');
 $payload=['displayName'=>'AYO Demo '.gmdate('Ymd His'),'languageCode'=>'ar-EG','voiceSamples'=>[['audioData'=>base64_encode((string)$bin)]],'description'=>'Internal demo voice — owner consent'];
 $ch=curl_init('https://api.inworld.ai/voices/v1/voices:clone');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Authorization: Basic '.$c['inworldKey'],'Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>65,CURLOPT_CONNECTTIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_FOLLOWLOCATION=>false]);$res=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 $d=json_decode(is_string($res)?$res:'',true);if($status<200||$status>=300||empty($d['voice']['voiceId']))fail('استنساخ Inworld لم ينجح؛ تحقق من المفتاح والعينة (HTTP '.$status.')',502);
 $_SESSION['ayo_config']['voiceId']=$d['voice']['voiceId'];json_out(['voiceId'=>$d['voice']['voiceId'],'config'=>public_cfg(cfg())]);
}
if ($action==='llm_test') {
 $c=cfg();if(($c['llmProvider']==='apinex'?$c['apinexKey']:$c['omniraKey'])==='')fail('المفتاح غير محفوظ للبوابة المختارة');
 session_write_close();@set_time_limit(90);stream_headers();$start=microtime(true);
 emit(['type'=>'probe_start','provider'=>$c['llmProvider'],'model'=>$c['llmModel']]);
 try {
  $reply=llm_stream($c,[['role'=>'system','content'=>'رد بجملة قصيرة فقط من غير تفكير داخلي.'],['role'=>'user','content'=>'قول: بوابة آيو شغالة']]);
  emit(['type'=>'probe_complete','usableText'=>trim($reply)!=='','elapsedMs'=>round((microtime(true)-$start)*1000)]);
 }catch(Throwable $e){emit(['type'=>'error','message'=>$e->getMessage()]);}
 exit;
}
if ($action==='preview'||$action==='turn') {
 $in=body();$text=trim((string)($in[$action==='preview'?'text':'question']??''));if($text===''||char_len($text)>1500)fail('النص مطلوب (بحد أقصى 1500 حرف)');
 $c=cfg();if($action==='turn'&&(!isset($_SESSION['ayo_deadline'])||$_SESSION['ayo_deadline']<time()))fail('انتهت جلسة الدقيقتين؛ ابدأ من جديد',408);
 $hist=$_SESSION['ayo_history']??[];if($action==='turn')$_SESSION['ayo_history']=array_slice([...$hist,['role'=>'user','content'=>$text]],-12);
 session_write_close();@set_time_limit(95);stream_headers();$start=microtime(true);
 try {
  if($action==='turn'){
   emit(['type'=>'started']);$messages=[['role'=>'system','content'=>prompt()]];foreach(array_slice($hist,-10) as $r)if(in_array($r['role'],['user','assistant'],true))$messages[]=$r;
   $messages[]=['role'=>'user','content'=>$text];$reply=llm_stream($c,$messages);$reply=preg_replace('/<think>.*?<\/think>/su','',$reply)??$reply;
   if($c['inworldKey']!=='')tts_stream($reply,$c,$text);else emit(['type'=>'tts_notice','message'=>'تم توليد الرد النصي فقط: مفتاح Inworld غير محفوظ']);
  } else {emit(['type'=>'started']);tts_stream($text,$c);}
  emit(['type'=>'complete','totalMs'=>round((microtime(true)-$start)*1000)]);
 }catch(Throwable $e){emit(['type'=>'error','message'=>$e->getMessage()]);}
 exit;
}
fail('مسار غير معروف',404);
