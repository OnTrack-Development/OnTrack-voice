<?php
declare(strict_types=1);
require __DIR__.'/../../_core/Bootstrap.php';
require __DIR__.'/../../_core/DevicePairing.php';
require __DIR__.'/../../_core/TenantVoiceSession.php';
require __DIR__.'/../../_core/TenantWorkspace.php';
require __DIR__.'/../../_core/TenantCallReports.php';
require __DIR__.'/../../_core/TenantFileParser.php';
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
if(strlen($raw)>1490000)device_answer(413,['ok'=>false,'error'=>'request_too_large']);
$data=json_decode($raw,true);
if(!is_array($data))device_answer(422,['ok'=>false,'error'=>'invalid_json']);
try{
    init_schema();
    $operation=(string)($data['operation']??'');
    $maxBody=$operation==='workspace_file_import'?1490000:
       ($operation==='workspace_import'?350000:4096);
    if(strlen($raw)>$maxBody)device_answer(413,['ok'=>false,'error'=>'request_too_large']);
    if($operation==='pair'){
        $slot=$data['sim_slot']??null;
        if(!is_int($slot))device_answer(422,['ok'=>false,'error'=>'invalid_slot']);
        $bound=device_pairing_redeem(
            (string)($data['code']??''),(string)($data['installation_id']??''),$slot,
            trim((string)($data['device_name']??''))
        );
        device_answer(201,['ok'=>true,'binding'=>$bound,'state'=>'paired_control_plane_only']);
    }
    if(in_array($operation,['heartbeat','disconnect','published_flow','voice_session','voice_preflight',
        'workspace_contacts','workspace_contact_dnc','workspace_import','workspace_calls',
        'workspace_call_event','workspace_report_event','workspace_campaigns','workspace_campaign_create','workspace_campaign_action','workspace_file_import'],true)){
        $auth=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
        if(!str_starts_with($auth,'Bearer '))device_answer(401,['ok'=>false,'error'=>'authorization_required']);
        $binding=device_binding_authorize(substr($auth,7));
        if(!$binding)device_answer(401,['ok'=>false,'error'=>'device_not_authorized']);
        if($operation==='disconnect'){
            if(!device_binding_disconnect($binding))device_answer(409,['ok'=>false,'error'=>'device_disconnect_conflict']);
            device_answer(200,['ok'=>true,'state'=>'revoked']);
        }
        if(str_starts_with($operation,'workspace_')){
            if($operation==='workspace_contacts')device_answer(200,['ok'=>true]+tenant_workspace_contacts($binding));
            if($operation==='workspace_calls')device_answer(200,['ok'=>true]+tenant_workspace_calls($binding));
            if($operation==='workspace_campaigns')device_answer(200,['ok'=>true]+tenant_workspace_campaigns($binding));
            if($operation==='workspace_call_event')device_answer(200,['ok'=>true]+tenant_workspace_call_event($binding,$data));
            if($operation==='workspace_report_event'){
                if(!is_int($data['sim_slot']??null) || $data['sim_slot']!==(int)$binding['sim_slot'] ||
                    !is_int($data['device_id']??null) || $data['device_id']!==(int)$binding['id'])
                    device_answer(422,['ok'=>false,'error'=>'report_binding_mismatch']);
                device_binding_heartbeat($binding);
                $stored=tenant_report_append($binding,$data,'device');
                if(($data['kind']??'')==='finalize'){
                    try {
                        $summary=tenant_report_summarize($binding,(string)$data['call_id'],
                            static fn(string $transcript):string=>tenant_report_google_summary($transcript));
                        device_answer(200,['ok'=>true,'report'=>$stored,'summary'=>$summary]);
                    }catch(InvalidArgumentException $e){
                        // No premature summary: keep the ordered end marker to retry.
                        device_answer(409,['ok'=>false,'error'=>$e->getMessage()]);
                    }catch(Throwable $e){
                        error_log('tenant voice summary failed: '.$e->getMessage());
                        device_answer(200,['ok'=>true,'report'=>$stored,
                            'summary_status'=>'retry_required']);
                    }
                }
                device_answer(200,['ok'=>true,'report'=>$stored]);
            }
            if(($data['confirmed']??false)!==true)device_answer(422,['ok'=>false,'error'=>'confirmation_required']);
            if($operation==='workspace_import'){
                if(!is_array($data['contacts']??null))device_answer(422,['ok'=>false,'error'=>'invalid_contact_list']);
                device_answer(200,['ok'=>true,'counts'=>tenant_workspace_import($binding,$data['contacts'])]);
            }
            if($operation==='workspace_file_import'){
                $name=(string)($data['name']??'');$encoded=$data['data_b64']??null;
                if(!preg_match('/^.{1,140}\\.(csv|txt|xlsx)$/iD',$name)||!is_string($encoded))
                    device_answer(422,['ok'=>false,'error'=>'invalid_file']);
                $decoded=base64_decode($encoded,true);
                if(!is_string($decoded)||strlen($decoded)<1||strlen($decoded)>1048576)
                    device_answer(422,['ok'=>false,'error'=>'invalid_file']);
                $file=tempnam(sys_get_temp_dir(),'otv-tenant-');
                if($file===false)device_answer(503,['ok'=>false,'error'=>'temporary_file_failed']);
                try{
                    if(file_put_contents($file,$decoded,LOCK_EX)===false)throw new RuntimeException('file_write_failed');
                    $rows=tenant_file_rows($file,$name);
                    $counts=tenant_workspace_import($binding,$rows);
                }finally{if(is_file($file))unlink($file);}
                device_answer(200,['ok'=>true,'counts'=>$counts]);
            }
            if($operation==='workspace_contact_dnc'){
                if(!is_bool($data['blocked']??null))device_answer(422,['ok'=>false,'error'=>'invalid_blocked']);
                device_answer(200,['ok'=>true]+tenant_workspace_dnc($binding,$data['id']??null,$data['blocked']));
            }
            if($operation==='workspace_campaign_create')device_answer(200,['ok'=>true]+tenant_workspace_create_campaign($binding,$data));
            if($operation==='workspace_campaign_action')device_answer(200,['ok'=>true]+tenant_workspace_campaign_action($binding,$data));
        }
        $profile=device_binding_heartbeat($binding);
        if($operation==='voice_preflight'){
            try{$ready=tenant_voice_preflight($binding);}
            catch(RuntimeException $problem){
                $reason=$problem->getMessage();
                if($reason==='voice_provider_not_configured')
                    device_answer(409,['ok'=>false,'error'=>$reason]);
                device_answer(503,['ok'=>false,'error'=>'voice_provider_unavailable']);
            }
            device_answer(200,['ok'=>true,'preflight'=>$ready]);
        }
        if($operation==='voice_session'){
            // No global pilot policy, unpaired device, foreign SIM, or draft agent can receive a token.
            try{$session=tenant_voice_session($binding);}
            catch(RuntimeException $problem){
                $reason=$problem->getMessage();
                if(in_array($reason,['tenant_voice_not_launched','voice_provider_not_configured'],true))
                    device_answer(409,['ok'=>false,'error'=>$reason]);
                device_answer(503,['ok'=>false,'error'=>'voice_provider_unavailable']);
            }
            device_answer(200,['ok'=>true,'session'=>$session]);
        }
        if($operation==='published_flow'){
            $flow=published_agent_flow((int)$binding['account_id'],(int)$binding['site_id'],(int)$binding['agent_id']);
            if(!$flow)device_answer(409,['ok'=>false,'error'=>'agent_flow_not_published']);
            device_answer(200,['ok'=>true,'agent_id'=>(int)$binding['agent_id'],
                'site_id'=>(int)$binding['site_id'],'sim_slot'=>(int)$binding['sim_slot'],
                'revision'=>(int)$flow['revision'],'hash'=>(string)$flow['flow_hash'],
                'voice'=>(string)$flow['voice'],'instructions'=>(string)$flow['instructions'],
                'knowledge'=>(string)$flow['knowledge'],
                'answer_policy'=>answer_policy_normalize((array)(json_decode((string)($flow['answer_policy_json']??''),true)??[])),
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
