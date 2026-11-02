<?php
declare(strict_types=1);
/**
 * Meta webhook: public, signature verified, never uses user session or CSRF.
 * Does not execute model output, ticket actions or create WHMCS customers.
 */
require __DIR__.'/_core/Bootstrap.php';
require __DIR__.'/_core/WhatsAppSupport.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
try{init_schema();}
catch(Throwable){http_response_code(503);echo '{"ok":false}';exit;}
$public=(string)($_GET['id']??'');
if(!preg_match('/^[a-f0-9]{36}$/D',$public)){http_response_code(404);echo '{"ok":false}';exit;}
$connection=one('SELECT * FROM wa_connections WHERE public_id=?',[$public]);
if(!$connection){http_response_code(404);echo '{"ok":false}';exit;}
if(($_SERVER['REQUEST_METHOD']??'')==='GET'){
    $mode=(string)($_GET['hub_mode']??$_GET['hub.mode']??'');
    $token=(string)($_GET['hub_verify_token']??$_GET['hub.verify_token']??'');
    $challenge=(string)($_GET['hub_challenge']??$_GET['hub.challenge']??'');
    $response=wa_webhook_challenge($connection,$mode,$token,$challenge);
    if($response===null){http_response_code(403);echo '{"ok":false}';exit;}
    header('Content-Type: text/plain; charset=utf-8');
    echo $response;exit;
}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo '{"ok":false}';exit;}
$raw=file_get_contents('php://input',false,null,0,262145);
if(!is_string($raw)||strlen($raw)>262144){
    http_response_code(413);echo '{"ok":false}';exit;
}
$metaSignature=(string)($_SERVER['HTTP_X_HUB_SIGNATURE_256']??'');
$relaySignature=(string)($_SERVER['HTTP_X_ONTRACK_WEBHOOK_SIGNATURE_256']??'');
$fromMeta=wa_signature_valid($connection,$metaSignature,$raw);
$fromRelay=!$fromMeta&&wa_relay_signature_valid($connection,$relaySignature,$raw);
if(!$fromMeta&&!$fromRelay){
    http_response_code(403);echo '{"ok":false}';exit;
}
try{
    $data=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($data))throw new InvalidArgumentException('Invalid payload');
    // Only a valid tenant relay signature can authorize normalization of
    // third-party events. Normal Meta delivery remains untouched.
    if($fromRelay)$data=wa_relay_normalize($connection,$data);
    $result=wa_webhook_ingest($connection,$data);
    http_response_code(200);
    echo json_encode(['ok'=>true,'accepted'=>$result['accepted'],'duplicates'=>$result['duplicates']],
        JSON_THROW_ON_ERROR);
    // Acknowledge Meta before expensive native or optional external AI calls. Without a
    // supported flush primitive the durable queue remains for a scheduled worker.
    $native=!empty($connection['ai_enabled'])&&!empty($connection['ai_agent_id']);
    $legacy=!$native&&empty($connection['ai_agent_id'])&&!empty($connection['whmcs_bridge_verified_at']);
    if($result['accepted']>0 && ($native||$legacy) &&
       $connection['state']==='verified'){
        $finished=false;
        if(function_exists('fastcgi_finish_request')){
            fastcgi_finish_request();$finished=true;
        }elseif(function_exists('litespeed_finish_request')){
            litespeed_finish_request();$finished=true;
        }
        if($finished){
            ignore_user_abort(true);
            try{
                if($native){
                    require_once __DIR__.'/_core/WhatsAppNativeAgent.php';
                    wa_native_run_pending($connection,$result['conversation_ids']??[]);
                }else{
                    require_once __DIR__.'/_core/WhatsAppWhmcsBridge.php';
                    wa_whmcs_run_pending($connection,$result['conversation_ids']??[]);
                }
            }catch(Throwable){/* Audit/queued job owns any recoverable error; no leaked details. */}
        }
    }
}catch(Throwable){
    // Real DB/provider errors must cause Meta retry rather than dropping events.
    http_response_code(503);echo '{"ok":false}';
}
