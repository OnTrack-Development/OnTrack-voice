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

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Accel-Buffering: no');
header('Referrer-Policy: no-referrer');
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
    init_schema();
    $binding=device_binding_authorize(substr($auth,7));
    if(!$binding)io_premium_error(401,'device_not_authorized');
    device_binding_heartbeat($binding);
    // The heartbeat returns a public profile, not the authorization row.
    $binding=device_binding_authorize(substr($auth,7));
    if(!$binding)io_premium_error(401,'device_not_authorized');
    if(!tenant_voice_entitled($binding))io_premium_error(403,'voice_subscription_inactive');
    $published=published_agent_flow((int)$binding['account_id'],
        (int)$binding['site_id'],(int)$binding['agent_id']);
    if(!$published||(string)$published['voice']!=='io.premium')
        io_premium_error(403,'premium_voice_not_published');
    $grant=one("SELECT v.id FROM voices v JOIN voice_grants g ON g.voice_id=v.id
        WHERE v.code='io.premium' AND v.provider='fish_audio'
        AND v.tier='premium' AND v.ready=1 AND v.status='active'
        AND g.account_id=?",[(int)$binding['account_id']]);
    if(!$grant)io_premium_error(403,'premium_voice_not_granted');
    $cfg=private_path().'/voice-lab/owner-1.json';
    if(is_link($cfg)||!is_file($cfg))io_premium_error(503,'premium_voice_unavailable');
    $saved=json_decode((string)file_get_contents($cfg),true);
    $secret=is_array($saved)?($saved['keys']['fish']??null):null;
    $key=is_string($secret)?(unseal_secret($secret)??''):'';
    $voice=is_array($saved)?strtolower((string)($saved['settings']['voiceId']??'')):'';
    $model=is_array($saved)?(string)($saved['settings']['fishModel']??''):'';
    if($key===''||strlen($key)>2048||preg_match('/[\x00-\x1f\x7f]/',$key)
        ||!preg_match('/^[0-9a-f]{32}$/D',$voice)
        ||!in_array($model,['drama-3','s2.1-pro-free','s2.1-pro'],true))
        io_premium_error(503,'premium_voice_unavailable');
} catch(Throwable $e) {
    io_premium_error(503,'premium_voice_unavailable');
}
if(!function_exists('curl_init'))io_premium_error(503,'premium_voice_unavailable');
$curl=curl_init('https://api.fish.audio/v1/tts');
if($curl===false)io_premium_error(503,'premium_voice_unavailable');
$upstreamStatus=502;
$started=false;
$sent=0;
$maxAudioBytes=44100*2*55; // upper bound per short TTS phrase
// Fish PCM is headerless, signed 16-bit mono; fixed 44.1kHz.
$payload=json_encode([
    'text'=>$text,'reference_id'=>$voice,'format'=>'pcm',
    'sample_rate'=>44100,'streaming'=>true,'latency'=>'low'
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
    CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$started,&$sent,&$upstreamStatus,$maxAudioBytes):int{
        $length=strlen($chunk);
        $sent+=$length;
        if($sent>$maxAudioBytes)return 0;
        if(!$started){
            http_response_code($upstreamStatus>=200&&$upstreamStatus<300?200:502);
            header('Content-Type: '.($upstreamStatus>=200&&$upstreamStatus<300
                ?'application/octet-stream':'text/plain; charset=utf-8'));
            if($upstreamStatus>=200&&$upstreamStatus<300){
                header('X-PCM-Sample-Rate: 44100');
                header('X-PCM-Channels: 1');
                header('X-PCM-Format: s16le');
            }
            $started=true;
        }
        if($upstreamStatus>=200&&$upstreamStatus<300)echo $chunk;
        @flush();
        return $length;
    }
]);
$ok=curl_exec($curl);
curl_close($curl);
if(!$ok&&!$started)io_premium_error(502,'premium_tts_network_error');
if(!$started)io_premium_error(502,'premium_tts_empty_audio');
