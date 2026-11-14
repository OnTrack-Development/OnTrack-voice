<?php
declare(strict_types=1);
/**
 * IO Premium PCM streaming for paired Android SIM calls.
 * Public endpoint but NEVER public credentials: scoped device bearer,
 * published agent selection, premium voice grant, and active entitlement.
 */
require_once __DIR__.'/../../_core/Bootstrap.php';
require_once __DIR__.'/../../_core/DevicePairing.php';
require_once __DIR__.'/../../_core/TenantVoiceSession.php';
require_once __DIR__.'/FishTtsFallbackPolicy.php';

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Accel-Buffering: no');
header('Referrer-Policy: no-referrer');
header('Connection: keep-alive');
header('Keep-Alive: timeout=30, max=1000');
function io_premium_error(int $http,string $code):never {
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>$code],JSON_UNESCAPED_SLASHES);
    exit;
}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){
    header('Allow: POST');
    io_premium_error(405,'method_not_allowed');
}
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')
    io_premium_error(426,'https_required');
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
$host=strtolower((string)parse_url('https://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST));
if($origin!=='' && (parse_url($origin,PHP_URL_SCHEME)!=='https'
    ||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host))
    io_premium_error(403,'wrong_origin');
$auth=(string)($_SERVER['HTTP_AUTHORIZATION']??'');
if(!str_starts_with($auth,'Bearer ')||strlen($auth)>128)
    io_premium_error(401,'device_authorization_required');
if((int)($_SERVER['CONTENT_LENGTH']??0)>2400)
    io_premium_error(413,'request_too_large');
$body=json_decode((string)file_get_contents('php://input'),true);
$text=is_array($body)&&is_string($body['text']??null)?trim($body['text']):'';
if($text===''||mb_strlen($text)>400||strlen($text)>1600)
    io_premium_error(422,'invalid_premium_text');
try {
    $binding=device_binding_authorize(substr($auth,7));
    if(!$binding)io_premium_error(401,'device_not_authorized');
    if(!tenant_voice_entitled($binding))io_premium_error(403,'voice_subscription_inactive');
    $published=published_agent_flow((int)$binding['account_id'],
        (int)$binding['site_id'],(int)$binding['agent_id']);
    $publishedVoice=$published?(string)$published['voice']:'';
    if(!$published||!tenant_voice_is_fish($publishedVoice))
        io_premium_error(403,'premium_voice_not_published');
    require_once __DIR__.'/../../_core/IoPremiumProvider.php';
    if($publishedVoice==='io.premium'){
        if(!io_premium_access_allowed((int)$binding['account_id']))
            io_premium_error(403,'premium_voice_not_available');
    }else{
        $voiceRow=one("SELECT id,source_ref_id,ready,status FROM voices WHERE code=?",[$publishedVoice]);
        if(!$voiceRow||(int)$voiceRow['ready']!==1||(string)$voiceRow['status']!=='active')
            io_premium_error(403,'premium_voice_not_available');
        $acct=account((int)$binding['account_id']);
        if(!$acct||(($acct['kind']??'')!=='platform'
            && !one('SELECT id FROM voice_grants WHERE account_id=? AND voice_id=?',[(int)$binding['account_id'],(int)$voiceRow['id']])))
            io_premium_error(403,'premium_voice_not_available');
    }
    $cfg=private_path().'/voice-lab/owner-1.json';
    if(is_link($cfg)||!is_file($cfg))io_premium_error(503,'premium_voice_unavailable');
    $saved=json_decode((string)file_get_contents($cfg),true);
    $secret=is_array($saved)?($saved['keys']['fish']??null):null;
    $key=is_string($secret)?(unseal_secret($secret)??''):'';
    $voice=tenant_voice_resolve_fish((int)$binding['account_id'],$publishedVoice)??'';
    $model=is_array($saved)?(string)($saved['settings']['fishModel']??'drama-3'):'';
    if($model==='')$model='drama-3';
    if($key===''||strlen($key)>2048||preg_match('/[\x00-\x1f\x7f]/',$key)
        ||!preg_match('/^[0-9a-f]{32}$/D',$voice)
        ||!in_array($model,['drama-3','s2.1-pro-free','s2.1-pro'],true))
        io_premium_error(503,'premium_voice_unavailable');
} catch(Throwable $e) {
    io_premium_error(503,'premium_voice_unavailable');
}
// A depleted shared key must not incur a new paid request for every caller.
// This is the *same authorized account/session*, only the TTS transport changes.
$fingerprint=FishTtsFallbackPolicy::fingerprint($secret);
if(($circuit=FishTtsFallbackPolicy::active($fingerprint))!==null)
    io_premium_error(FishTtsFallbackPolicy::clientHttp($circuit),$circuit);
if(!function_exists('curl_init'))io_premium_error(503,'premium_tts_provider_unavailable');
$curl=curl_init('https://api.fish.audio/v1/tts');
if($curl===false)io_premium_error(503,'premium_voice_unavailable');
$upstreamStatus=502;
$started=false;
$sent=0;
$errorBody='';
$maxAudioBytes=48000*2*55; // upper bound per short TTS phrase
// Fish PCM is headerless, signed 16-bit mono; direct 48kHz.
$payload=json_encode([
    'text'=>$text,'reference_id'=>$voice,'format'=>'pcm',
    'sample_rate'=>48000,'streaming'=>true,'latency'=>'low'
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
@ini_set('output_buffering','off');
@ini_set('zlib.output_compression','0');
while(ob_get_level()>0){@ob_end_flush();}
curl_setopt_array($curl,[
    CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>[
        'Authorization: Bearer '.$key,
        'Content-Type: application/json','Accept: application/octet-stream',
        'model: '.$model,
    ],
    CURLOPT_POSTFIELDS=>$payload,
    CURLOPT_RETURNTRANSFER=>false,
    CURLOPT_CONNECTTIMEOUT=>7,
    CURLOPT_TIMEOUT=>60,
    CURLOPT_FOLLOWLOCATION=>false,
    CURLOPT_SSL_VERIFYPEER=>true,
    CURLOPT_SSL_VERIFYHOST=>2,
    CURLOPT_HEADERFUNCTION=>static function($ch,string $header)use(&$upstreamStatus):int{
        if(preg_match('~^HTTP/[\d.]+\s+(\d{3})\b~',$header,$m))
            $upstreamStatus=(int)$m[1];
        return strlen($header);
    },
    CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$started,&$sent,&$upstreamStatus,&$errorBody,$maxAudioBytes):int{
        $length=strlen($chunk);
        // Never stream upstream error JSON as signed PCM (nor leak provider
        // errors/credentials). The HTTP status is finalized only after cURL.
        if($upstreamStatus<200||$upstreamStatus>=300){
            if(strlen($errorBody)<4096)
                $errorBody.=substr($chunk,0,4096-strlen($errorBody));
            return $length;
        }
        $sent+=$length;
        if($sent>$maxAudioBytes)return 0;
        if(!$started){
            http_response_code(200);
            header('Content-Type: application/octet-stream');
            header('X-PCM-Sample-Rate: 48000');
            header('X-PCM-Channels: 1');
            header('X-PCM-Format: s16le');
            $started=true;
        }
        echo $chunk;
        @flush();
        return $length;
    }
]);
$ok=curl_exec($curl);
$curlStatus=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
curl_close($curl);
$status=$curlStatus>0?$curlStatus:$upstreamStatus;
if($status>=400){
    $code=FishTtsFallbackPolicy::classify($status,$errorBody);
    FishTtsFallbackPolicy::mark($fingerprint,$code);
    io_premium_error(FishTtsFallbackPolicy::clientHttp($code),$code);
}
if(!$ok&&!$started)io_premium_error(503,'premium_tts_network_error');
if(!$started)io_premium_error(502,'premium_tts_empty_audio');
