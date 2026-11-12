<?php
declare(strict_types=1);
require '/home/appontrack/public_html/_core/Bootstrap.php';
$data=json_decode((string)file_get_contents('/home/appontrack/.ontrack-saas/voice-lab/owner-1.json'),true);
$box=is_array($data)?($data['keys']['fish']??null):null;
$key=is_string($box)?(unseal_secret($box)??''):'';
$voice=strtolower((string)($data['settings']['voiceId']??''));
$model=(string)($data['settings']['fishModel']??'');
if($key===''||!preg_match('/^[a-f0-9]{32}$/D',$voice)||!in_array($model,['drama-3','s2.1-pro-free','s2.1-pro'],true))throw new RuntimeException('fish_config_unavailable');
$p=json_encode(['text'=>'أهلاً بيك.','reference_id'=>$voice,'format'=>'pcm','sample_rate'=>44100,'streaming'=>true,'latency'=>'low'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
$h=curl_init('https://api.fish.audio/v1/tts');$first=null;$n=0;$prefix='';$begin=microtime(true);
curl_setopt_array($h,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$p,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json','Accept: application/octet-stream','model: '.$model],CURLOPT_RETURNTRANSFER=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>22,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_WRITEFUNCTION=>function($ch,string $chunk)use(&$first,&$n,&$prefix,$begin){if($first===null)$first=(int)round((microtime(true)-$begin)*1000);$n+=strlen($chunk);if(strlen($prefix)<8)$prefix.=substr($chunk,0,8-strlen($prefix));return strlen($chunk);}]);
$ok=curl_exec($h);$code=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);$type=(string)curl_getinfo($h,CURLINFO_CONTENT_TYPE);$errno=curl_errno($h);curl_close($h);
echo json_encode(['ok'=>(bool)$ok&&$code===200&&$n>=128,'http_status'=>$code,'bytes'=>$n,'first_byte_ms'=>$first,'content_type'=>$type,'prefix_hex'=>bin2hex($prefix),'curl_errno'=>$errno],JSON_UNESCAPED_SLASHES).PHP_EOL;
if(!$ok||$code!==200||$n<128)exit(2);
