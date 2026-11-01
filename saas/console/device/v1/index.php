<?php
declare(strict_types=1);
require __DIR__.'/../../_core/Bootstrap.php';
require __DIR__.'/../../_core/DevicePairing.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function device_answer(int $code,array $body):never {
    http_response_code($code);
    echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')
    device_answer(405,['ok'=>false,'error'=>'method_not_allowed']);
// Device bearer and one-time codes MUST never traverse cleartext HTTP.
if(PHP_SAPI!=='cli' && (empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'))
    device_answer(426,['ok'=>false,'error'=>'https_required']);
$raw=(string)file_get_contents('php://input');
if(strlen($raw)>4096)device_answer(413,['ok'=>false,'error'=>'request_too_large']);
$data=json_decode($raw,true);
if(!is_array($data))device_answer(422,['ok'=>false,'error'=>'invalid_json']);
try{
    init_schema();
    $operation=(string)($data['operation']??'');
    if($operation==='pair'){
        $slot=$data['sim_slot']??null;
        if(!is_int($slot))device_answer(422,['ok'=>false,'error'=>'invalid_slot']);
        $bound=device_pairing_redeem(
            (string)($data['code']??''),(string)($data['installation_id']??''),$slot,
            trim((string)($data['device_name']??''))
        );
        device_answer(201,['ok'=>true,'binding'=>$bound,'state'=>'paired_control_plane_only']);
    }
    if(in_array($operation,['heartbeat','disconnect','published_flow'],true)){
        $auth=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
        if(!str_starts_with($auth,'Bearer '))device_answer(401,['ok'=>false,'error'=>'authorization_required']);
        $binding=device_binding_authorize(substr($auth,7));
        if(!$binding)device_answer(401,['ok'=>false,'error'=>'device_not_authorized']);
        if($operation==='disconnect'){
            if(!device_binding_disconnect($binding))device_answer(409,['ok'=>false,'error'=>'device_disconnect_conflict']);
            device_answer(200,['ok'=>true,'state'=>'revoked']);
        }
        $profile=device_binding_heartbeat($binding);
        if($operation==='published_flow'){
            $flow=published_agent_flow((int)$binding['account_id'],(int)$binding['site_id'],(int)$binding['agent_id']);
            if(!$flow)device_answer(409,['ok'=>false,'error'=>'agent_flow_not_published']);
            device_answer(200,['ok'=>true,'agent_id'=>(int)$binding['agent_id'],
                'site_id'=>(int)$binding['site_id'],'sim_slot'=>(int)$binding['sim_slot'],
                'revision'=>(int)$flow['revision'],'hash'=>(string)$flow['flow_hash'],
                'voice'=>(string)$flow['voice'],'instructions'=>(string)$flow['instructions'],
                'knowledge'=>(string)$flow['knowledge'],
                'flow'=>json_decode((string)$flow['flow_json'],true,32,JSON_THROW_ON_ERROR),
                'execution'=>'published_contract_not_live_telephone_ai']);
        }
        device_answer(200,['ok'=>true,'profile'=>$profile]);
    }
    device_answer(404,['ok'=>false,'error'=>'unknown_operation']);
}catch(InvalidArgumentException $e){
    device_answer(422,['ok'=>false,'error'=>$e->getMessage()]);
}catch(PDOException $e){
    // The unique slot_claim prevents assigning the same device SIM slot to two tenants.
    $code=(string)$e->getCode();
    if(str_starts_with($code,'23')||$code==='19')
        device_answer(409,['ok'=>false,'error'=>'sim_slot_already_paired']);
    device_answer(503,['ok'=>false,'error'=>'database_error']);
}catch(Throwable $e){
    device_answer(503,['ok'=>false,'error'=>'server_unavailable']);
}
