<?php
declare(strict_types=1);
require_once __DIR__.'/../../_core/Bootstrap.php';
require_once __DIR__.'/../../_core/DevicePairing.php';
require_once __DIR__.'/../../_core/TenantVoiceSession.php';
require_once __DIR__.'/../../_core/TenantWorkspace.php';
require_once __DIR__.'/../../_core/TenantFileParser.php';
require_once __DIR__.'/../../_core/VoiceAccess.php';
require_once __DIR__.'/../../_core/HumanEscalations.php';

$t0 = hrtime(true);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

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
       ($operation==='workspace_import'?350000:(in_array($operation,['workspace_call_analysis','workspace_call_transcript'],true)?65536:4096));
    if(strlen($raw)>$maxBody)device_answer(413,['ok'=>false,'error'=>'request_too_large']);
    if($operation==='pair'){
        $slot=$data['sim_slot']??null;
        if(!is_int($slot))device_answer(422,['ok'=>false,'error'=>'invalid_slot']);
        device_pairing_rate_guard(
            (string)($_SERVER['REMOTE_ADDR']??''),
            (string)($data['installation_id']??'')
        );
        $bound=device_pairing_redeem(
            (string)($data['code']??''),(string)($data['installation_id']??''),$slot,
            trim((string)($data['device_name']??''))
        );
        device_answer(201,['ok'=>true,'binding'=>$bound,'state'=>'paired_control_plane_only']);
    }
    if(in_array($operation,['heartbeat','disconnect','published_flow','voice_session','voice_failover','voice_preflight','caller_identify','caller_verify',
        'workspace_contacts','workspace_contact_dnc','workspace_import','workspace_calls','workspace_call_transcript',
        'workspace_call_event','workspace_call_report','workspace_call_analysis','workspace_handoff_claim','workspace_handoff_ack','workspace_campaigns','workspace_campaign_create','workspace_campaign_action','workspace_campaign_claim',
        'workspace_campaign_verify','workspace_campaign_finish','workspace_contact_consent',
        'workspace_file_import','workspace_call_timeline','engine_manifest','engine_download',
        'flow_start','flow_step','flow_state'],true)){
        $auth=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
        if(!str_starts_with($auth,'Bearer '))device_answer(401,['ok'=>false,'error'=>'authorization_required']);
        $tAuth0 = hrtime(true);
        $binding=device_binding_authorize(substr($auth,7));
        $authMs = (hrtime(true) - $tAuth0) / 1e6;
        header('X-OnTrack-Auth-Ms: ' . number_format($authMs, 2, '.', ''));
        if(!$binding)device_answer(401,['ok'=>false,'error'=>'device_not_authorized']);
        // Protected engine is not Android OTA. Module delivery is opt-in,
        // signed, encrypted at rest and requires the live SIM entitlement.
        // No module is available without a pinned, owner-signed release.
        if($operation==='engine_manifest'||$operation==='engine_download'){
            require_once __DIR__.'/../../_core/ProtectedEngine.php';
            try{
                $abi=(string)($data['abi']??'');
                if($operation==='engine_manifest'){
                    device_answer(200,['ok'=>true]+protected_engine_manifest($binding,$abi));
                }
                $license=(string)($data['grant']??'');
                $module=protected_engine_download($binding,$abi,$license);
                // All download grants arrive by authenticated HTTPS POST,
                // never query strings or access logs. Do not return a raw
                // plaintext module through JSON, user logs, or public files.
                header('Content-Type: application/octet-stream');
                header('Content-Length: '.strlen($module['bytes']));
                header('X-Content-SHA256: '.$module['sha256']);
                header('X-Module-Signature-Ed25519: '.$module['signature']);
                header('Content-Disposition: attachment; filename="ontrack-protected-engine.bin"');
                header('Cache-Control: private, no-store, max-age=0');
                echo $module['bytes'];
                exit;
            }catch(InvalidArgumentException){
                device_answer(422,['ok'=>false,'error'=>'engine_request_invalid']);
            }catch(RuntimeException $error){
                $reason=$error->getMessage();
                if(in_array($reason,['engine_device_not_authorized',
                    'engine_subscription_inactive'],true))
                    device_answer(403,['ok'=>false,'error'=>'engine_entitlement_denied']);
                if(in_array($reason,['engine_release_not_configured',
                    'engine_download_grant_invalid','engine_release_revoked_or_changed'],true))
                    device_answer(409,['ok'=>false,'error'=>$reason]);
                device_answer(503,['ok'=>false,'error'=>'engine_verification_unavailable']);
            }
        }
        if($operation==='disconnect'){
            if(!device_binding_disconnect($binding))device_answer(409,['ok'=>false,'error'=>'device_disconnect_conflict']);
            device_answer(200,['ok'=>true,'state'=>'revoked']);
        }
        if(str_starts_with($operation,'workspace_')){
            if($operation==='workspace_contacts')device_answer(200,['ok'=>true]+tenant_workspace_contacts($binding));
            if($operation==='workspace_calls')device_answer(200,['ok'=>true]+tenant_workspace_calls($binding));
            if($operation==='workspace_call_transcript')device_answer(200,['ok'=>true]+tenant_workspace_call_transcript($binding,$data));
            if($operation==='workspace_call_timeline'){
                require_once __DIR__.'/../../_core/VoiceCallEvidence.php';
                device_answer(200,['ok'=>true]+voice_call_timeline_store($binding,$data));
            }
            if($operation==='workspace_campaigns')device_answer(200,['ok'=>true]+tenant_workspace_campaigns($binding));
            if($operation==='workspace_campaign_claim')device_answer(200,['ok'=>true]+tenant_workspace_campaign_claim($binding));
            if($operation==='workspace_campaign_verify')device_answer(200,['ok'=>true]+tenant_workspace_campaign_verify($binding,$data));
            if($operation==='workspace_campaign_finish')device_answer(200,['ok'=>true]+tenant_workspace_campaign_finish($binding,$data));
            if($operation==='workspace_call_event')device_answer(200,['ok'=>true]+tenant_workspace_call_event($binding,$data));
            if($operation==='workspace_call_report')device_answer(200,['ok'=>true]+tenant_workspace_call_report($binding,$data));
            if($operation==='workspace_call_analysis')device_answer(200,['ok'=>true]+tenant_workspace_call_analysis($binding,$data));
            if($operation==='workspace_handoff_claim')device_answer(200,['ok'=>true]+he_claim($binding));
            if($operation==='workspace_handoff_ack')
                device_answer(200,['ok'=>true]+he_ack($binding,
                    (int)($data['job_id']??0),(string)($data['stage']??'')));
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
            if($operation==='workspace_contact_consent')device_answer(200,['ok'=>true]+tcq_consent(
                $binding,(string)($data['id']??''),(string)($data['method']??''),
                (string)($data['evidence']??'')));
            if($operation==='workspace_campaign_create')device_answer(200,['ok'=>true]+tenant_workspace_create_campaign($binding,$data));
            if($operation==='workspace_campaign_action')device_answer(200,['ok'=>true]+tenant_workspace_campaign_action($binding,$data));
        }
        $profile=device_binding_heartbeat($binding);
        if($operation==='caller_identify'){
            // Paired SIM is the only account resolver. Caller ID alone never discloses data.
            $phone=voice_phone_normalize((string)($data['phone']??''));
            $match=one("SELECT v.id FROM voice_staff_numbers v
                JOIN memberships m ON m.account_id=v.account_id AND m.user_id=v.user_id
                JOIN users u ON u.id=v.user_id AND u.status='active'
                WHERE v.account_id=? AND v.phone_e164=? AND v.status='active'",
                [(int)$binding['account_id'],$phone]);
            device_answer(200,['ok'=>true,'recognized'=>$match!==null,
                'pin_required'=>true,'facts'=>[],'authenticated'=>false]);
        }
        if($operation==='caller_verify'){
            // PIN must come from private DTMF device input, never from Gemini transcript.
            $pin=$data['pin']??null;
            if(!is_string($pin)||strlen($pin)>10)device_answer(422,['ok'=>false,'error'=>'invalid_pin']);
            $principal=voice_staff_authenticate((int)$binding['account_id'],(string)($data['phone']??''),$pin);
            if(!$principal)device_answer(403,['ok'=>false,'error'=>'caller_not_verified','facts'=>[]]);
            // Never return the credential or raw PIN; only bounded facts in authorized categories.
            $facts=voice_visible_facts($principal,45);
            insert('INSERT INTO audit_events(actor_user_id,account_id,action,detail,created_at) VALUES (?,?,?,?,?)',
                [$principal['user_id'],$principal['account_id'],'voice.caller.verified',
                 json_encode(['device_binding_id'=>$binding['id']??null,'categories'=>$principal['scopes']],JSON_THROW_ON_ERROR),now()]);
            device_answer(200,['ok'=>true,'authenticated'=>true,
                'principal'=>['name'=>$principal['name'],'role'=>$principal['role'],'scopes'=>$principal['scopes']],
                'facts'=>$facts,'actions_enabled'=>false]);
        }

        if($operation==='voice_preflight'){
            try{$ready=tenant_voice_preflight($binding);}
            catch(RuntimeException $problem){
                $reason=$problem->getMessage();
                if($reason==='voice_subscription_inactive')
                    device_answer(403,['ok'=>false,'error'=>$reason]);
                if($reason==='voice_provider_not_configured')
                    device_answer(409,['ok'=>false,'error'=>$reason]);
                device_answer(503,['ok'=>false,'error'=>'voice_provider_unavailable']);
            }
            device_answer(200,['ok'=>true,'preflight'=>$ready]);
        }
        if($operation==='voice_session'){
            // No global pilot policy, unpaired device, foreign SIM, or draft agent can receive a token.
            $tVault0 = hrtime(true);
            try{$session=tenant_voice_session($binding);}
            catch(RuntimeException $problem){
                $reason=$problem->getMessage();
                if($reason==='voice_subscription_inactive')
                    device_answer(403,['ok'=>false,'error'=>$reason]);
                if($reason==='voice_provider_not_configured')
                    device_answer(409,['ok'=>false,'error'=>$reason]);
                device_answer(503,['ok'=>false,'error'=>'voice_provider_unavailable']);
            }
            $vaultMs = (hrtime(true) - $tVault0) / 1e6;
            $totalMs = (hrtime(true) - $t0) / 1e6;
            header('X-OnTrack-Vault-Ms: ' . number_format($vaultMs, 2, '.', ''));
            header('X-OnTrack-Internal-Ms: ' . number_format($totalMs, 2, '.', ''));
            device_answer(200,['ok'=>true,'session'=>$session]);
        }
        if($operation==='voice_failover'){
            // Authenticated device binding is checked above. Never accept a
            // tenant/account/key id or raw API key from the request.
            require_once __DIR__.'/../../_core/GoogleVoiceFailover.php';
            $generation=$data['failover_generation']??null;
            if(!is_int($generation))device_answer(422,['ok'=>false,'error'=>'invalid_failover_generation']);
            try{
                $rotated=google_voice_failover_rotate($binding,
                    (string)($data['failover_ticket']??''),$generation,
                    (string)($data['failure_code']??''));
            }catch(RuntimeException $error){
                $code=$error->getMessage();
                if($code==='voice_subscription_inactive')
                    device_answer(403,['ok'=>false,'error'=>$code]);
                if(in_array($code,['voice_failover_ticket_invalid_or_stale',
                    'voice_failover_pool_changed','voice_failover_key_not_owned',
                    'voice_failover_concurrent_request'],true))
                    device_answer(409,['ok'=>false,'error'=>'voice_failover_conflict']);
                device_answer(503,['ok'=>false,'error'=>'voice_failover_no_healthy_keys']);
            }
            device_answer(200,['ok'=>true,'session'=>$rotated]);
        }
        // Deterministic device-side Flow control plane, scoped to the active
        // paired SIM and immutable published agent snapshot. The LLM does NOT
        // choose or execute external tool actions; a human must approve them
        // separately on the account workspace.
        if(in_array($operation,['flow_start','flow_step','flow_state'],true)){
            if(!tenant_voice_entitled($binding))
                device_answer(403,['ok'=>false,'error'=>'voice_subscription_inactive']);
            require_once __DIR__.'/../../_core/VoiceFlowRuntime.php';
            $accountId=(int)$binding['account_id'];
            $deviceId=(int)$binding['id'];
            if($operation==='flow_start'){
                $callId=(string)($data['call_id']??'');
                $opened=voice_flow_start($accountId,(int)$binding['site_id'],
                    (int)$binding['agent_id'],$callId,'device',0,$deviceId);
                $row=voice_flow_session($accountId,(int)$opened['session_id'],0,$deviceId);
                $advanced=null;
                if(!$opened['reused']&&$row['status']==='active')
                    $advanced=voice_flow_process($accountId,(int)$opened['session_id'],0,null,$deviceId);
                $row=voice_flow_session($accountId,(int)$opened['session_id'],0,$deviceId);
                device_answer(200,['ok'=>true,'session_id'=>(int)$row['id'],
                    'status'=>$row['status'],'revision'=>(int)$row['published_revision'],
                    'step_version'=>(int)$row['step_version'],
                    'events'=>$advanced['events']??[],
                    'state'=>json_decode((string)$row['state_json'],true,32,JSON_THROW_ON_ERROR),
                    'reused'=>$opened['reused'],
                    'tool_execution'=>'requires_separate_verified_human_approval']);
            }
            $id=$data['session_id']??null;
            if(!is_int($id)||$id<1)device_answer(422,['ok'=>false,'error'=>'flow_session_id_invalid']);
            $row=voice_flow_session($accountId,$id,0,$deviceId);
            voice_flow_require_active_context($row);
            if($operation==='flow_state')
                device_answer(200,['ok'=>true,'session_id'=>$id,'status'=>$row['status'],
                    'step_version'=>(int)$row['step_version'],
                    'state'=>json_decode((string)$row['state_json'],true,32,JSON_THROW_ON_ERROR)]);
            $expected=$data['step_version']??null;
            $reply=$data['reply']??null;
            if(!is_int($expected)||$expected<1||$expected!==(int)$row['step_version'])
                device_answer(409,['ok'=>false,'error'=>'flow_step_version_conflict',
                    'step_version'=>(int)$row['step_version']]);
            if(!is_string($reply)||trim($reply)===''||mb_strlen($reply)>500)
                device_answer(422,['ok'=>false,'error'=>'flow_reply_invalid']);
            $out=voice_flow_process($accountId,$id,0,$reply,$deviceId);
            $fresh=voice_flow_session($accountId,$id,0,$deviceId);
            device_answer(200,['ok'=>true,'session_id'=>$id,'status'=>$out['status'],
                'step_version'=>(int)$fresh['step_version'],'events'=>$out['events'],
                'state'=>$out['state'],'done'=>$out['done'],
                'tool_execution'=>'requires_separate_verified_human_approval']);
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
                'conversation_mode'=>'dynamic',
                // Legacy field only for older app versions; it must not be
                // interpreted as an executable conversation decision tree.
                'flow'=>json_decode((string)$flow['flow_json'],true,32,JSON_THROW_ON_ERROR),
                'execution'=>'dynamic_gemini_live_profile_legacy_graph_ignored']);
        }
        device_answer(200,['ok'=>true,'profile'=>$profile]);
    }
    device_answer(404,['ok'=>false,'error'=>'unknown_operation']);
}catch(InvalidArgumentException $e){
    if($e->getMessage()==='device_pairing_rate_limited')
        device_answer(429,['ok'=>false,'error'=>'device_pairing_rate_limited']);
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
