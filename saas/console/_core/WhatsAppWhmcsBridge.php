<?php
declare(strict_types=1);
require_once __DIR__.'/WhatsAppSupport.php';
require_once __DIR__.'/WhatsAppJobSafety.php';
require_once __DIR__.'/VoiceConnectors.php';

/**
 * Delegates decisions to the REAL WHMCS AI Support Agent addon, never to the
 * Voice Gemini agent. Live response requires a separate bridge installed in
 * the WHMCS addon and an explicit independent reviewer approval.
 */
function wa_whmcs_request(array $conn,array $payload,?callable $transport=null):array {
    $url=wa_bridge_url((string)($conn['whmcs_bridge_url']??''));
    $token=unseal_secret($conn['whmcs_bridge_token_enc']??null);
    if($url===''||!$token||strlen($token)<12)
        throw new RuntimeException('WHMCS AI Support Agent bridge is not configured');
    $encoded=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if(strlen($encoded)>18000)throw new InvalidArgumentException('WHMCS bridge payload too large');
    if($transport!==null)$response=$transport($url,$token,$encoded);
    else{
        if(!function_exists('curl_init'))throw new RuntimeException('cURL unavailable');
        // Fixed first-party hostname + DNS public-IP pinning protects against SSRF.
        $host='services.ontrackegy.com';
        $ip=voice_connector_public_ip($host);
        $ch=curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$encoded,CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,
                'Accept: application/json','Content-Type: application/json','X-OnTrack-Channel: whatsapp'],
            CURLOPT_PROXY=>'',CURLOPT_RESOLVE=>[$host.':443:'.$ip],
            CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>11,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2
        ]);
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $err=curl_errno($ch);curl_close($ch);
        if($err!==0)throw new RuntimeException('WHMCS AI Support Agent transport unavailable');
        $response=['status'=>$status,'body'=>$raw];
    }
    if((int)($response['status']??0)!==200)
        throw new RuntimeException('WHMCS AI Support Agent HTTP '.(int)($response['status']??0));
    $result=json_decode((string)($response['body']??''),true);
    if(!is_array($result))throw new RuntimeException('WHMCS AI Support Agent JSON contract invalid');
    return $result;
}
function wa_whmcs_probe(array $connection,?callable $transport=null):array {
    $result=wa_whmcs_request($connection,[
        'action'=>'capabilities','channel'=>'whatsapp',
        'probe'=>'ontrack_whmcs_channel_bridge_v1'
    ],$transport);
    if(($result['agent']??null)!=='ai_support_agent'||
       ($result['channel']??null)!=='whatsapp'||
       ($result['ready']??false)!==true||
       ($result['reviewer_required']??false)!==true||
       ($result['tool_scope']??null)!=='read_only')
        throw new RuntimeException('WHMCS addon has no verified WhatsApp reviewer-safe bridge');
    execute('UPDATE wa_connections SET whmcs_bridge_verified_at=?,updated_at=? WHERE id=? AND account_id=?',
        [now(),now(),(int)$connection['id'],(int)$connection['account_id']]);
    wa_audit((int)$connection['account_id'],null,'whatsapp.whmcs.verified','actual addon bridge capability confirmed');
    return ['verified'=>true,'agent'=>'ai_support_agent'];
}
function wa_whmcs_reviewed_draft(array $connection,array $conversation,
 ?callable $transport=null):?string {
    $messages=all('SELECT role,body FROM wa_messages WHERE account_id=? AND conversation_id=? ORDER BY id DESC LIMIT 12',
        [(int)$conversation['account_id'],(int)$conversation['id']]);
    $history=[];
    foreach(array_reverse($messages) as $m)
        $history[]=['role'=>in_array($m['role'],['customer','staff','ai'],true)?$m['role']:'customer',
            'text'=>mb_substr((string)$m['body'],0,2000)];
    $result=wa_whmcs_request($connection,[
        'action'=>'draft_reviewed_reply','channel'=>'whatsapp','version'=>1,
        'conversation_reference'=>'wa:'.(int)$conversation['id'],
        'external_contact'=>(string)$conversation['contact'],
        'customer_identity_verified'=>false,
        'allowed_actions'=>[],
        'history'=>$history
    ],$transport);
    // The addon MUST perform its independent review on its side; no direct
    // provider output, unreviewed text or WHMCS write action can be published.
    if(($result['agent']??null)!=='ai_support_agent'||
       ($result['reviewer_approved']??false)!==true||
       ($result['actions']??[])!==[]||
       !in_array($result['decision']??null,['reply','handoff','no_reply'],true))
        throw new RuntimeException('WHMCS support reply was not independently approved');
    if($result['decision']!=='reply')return null;
    $body=trim((string)($result['reply_text']??''));
    if($body===''||mb_strlen($body)>4096)throw new RuntimeException('WHMCS reviewed reply invalid');
    return $body;
}
function wa_whmcs_run_job(int $jobId,?callable $bridgeTransport=null,?callable $metaTransport=null):array {
    $job=one("SELECT * FROM wa_ai_jobs WHERE id=? AND status='pending'",[$jobId]);
    if(!$job)return ['status'=>'not_pending'];
    $accountId=(int)$job['account_id'];
    if(!account_service_active(account($accountId)))
        return ['status'=>'service_suspended'];
    $conv=wa_conversation((int)$job['conversation_id'],$accountId);
    $conn=wa_connection((int)$job['connection_id'],$accountId);
    if(empty($conn['whmcs_bridge_verified_at'])||$conn['state']!=='verified'||
       (int)$conv['generation']!==(int)$job['generation']||!wa_ai_allowed($conv)){
        execute("UPDATE wa_ai_jobs SET status='skipped',updated_at=? WHERE id=? AND status='pending'",[now(),$jobId]);
        return ['status'=>'skipped'];
    }
    if(execute("UPDATE wa_ai_jobs SET status='processing',attempts=attempts+1,updated_at=?
        WHERE id=? AND status='pending'",[now(),$jobId])!==1)return ['status'=>'claimed_elsewhere'];
    try{
        $body=wa_whmcs_reviewed_draft($conn,$conv,$bridgeTransport);
        if($body===null){
            execute("UPDATE wa_ai_jobs SET status='skipped',updated_at=? WHERE id=?",[now(),$jobId]);
            return ['status'=>'handoff_or_no_reply'];
        }
        return wa_job_send_once($job,$body,$metaTransport,[],false);
    }catch(Throwable $e){
        // Never auto-resend an uncertain message. Keep a visible failed job for
        // a human to examine; don't expose raw provider error or payload.
        execute("UPDATE wa_ai_jobs SET status='failed',updated_at=? WHERE id=? AND status='processing'",
            [now(),$jobId]);
        wa_audit($accountId,(int)$job['conversation_id'],'whatsapp.ai.failed',
            mb_substr(get_class($e),0,50));
        return ['status'=>'failed'];
    }
}
function wa_whmcs_run_pending(array $connection,array $conversationIds):void {
    if(!$conversationIds||empty($connection['whmcs_bridge_verified_at'])||
       $connection['state']!=='verified')return;
    foreach(array_slice(array_values(array_unique($conversationIds)),0,4) as $cid){
        $job=one("SELECT id FROM wa_ai_jobs WHERE account_id=? AND connection_id=?
            AND conversation_id=? AND status='pending' ORDER BY id DESC LIMIT 1",
            [(int)$connection['account_id'],(int)$connection['id'],(int)$cid]);
        if($job)wa_whmcs_run_job((int)$job['id']);
    }
}
