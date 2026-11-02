<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/WhatsAppWhmcsBridge.php';
function bridge_check(bool $ok,string $message):void{
    if(!$ok)throw new RuntimeException('WHMCS_WHATSAPP_BRIDGE_FAIL: '.$message);
}
seed_console();
$site=one("SELECT id FROM sites WHERE account_id=4 AND status='active' LIMIT 1");
bridge_check($site!==null,'test account has active site');
$url='https://services.ontrackegy.com/modules/addons/ai_support_agent/whatsapp_bridge.php';
$phoneId='1099888776655';
$cid=null;$channel=null;
try{
    $channel=wa_create_connection(4,(int)$site['id'],'WHMCS AI Demo',$phoneId,
        'dummy-meta-token-555','dummy-app-secret-555','test-verify-token-whmcs-555',
        $url,'whmcs-bridge-shared-secret-555');
    $conn=wa_connection($channel,4);
    $rejected=false;
    try{wa_whmcs_probe($conn,static fn($endpoint,$token,$body)=>[
        'status'=>200,'body'=>'{"agent":"other_agent","ready":true}'
    ]);}catch(RuntimeException){$rejected=true;}
    bridge_check($rejected,'false bridge capability cannot activate');
    bridge_check(wa_connection($channel,4)['whmcs_bridge_verified_at']===null,
        'false capability never verifies addon');
    wa_test_meta_connection($conn,static fn()=>[
        'status'=>200,'body'=>json_encode(['id'=>$phoneId],JSON_THROW_ON_ERROR)
    ]);
    $conn=wa_connection($channel,4);
    $probe=wa_whmcs_probe($conn,static function($endpoint,$token,$json)use($url){
        bridge_check($endpoint===$url,'only original addon endpoint invoked');
        bridge_check($token==='whmcs-bridge-shared-secret-555','bridge token decrypted only for call');
        $request=json_decode($json,true);
        bridge_check($request['action']==='capabilities','capability handshake');
        return ['status'=>200,'body'=>json_encode([
            'agent'=>'ai_support_agent','channel'=>'whatsapp','ready'=>true,
            'reviewer_required'=>true,'tool_scope'=>'read_only'
        ],JSON_THROW_ON_ERROR)];
    });
    bridge_check($probe['verified'],'reviewer-safe original agent capabilities verified');
    $conn=wa_connection($channel,4);
    bridge_check($conn['whmcs_bridge_verified_at']!==null,'verified timestamp persisted');
    $payload=['object'=>'whatsapp_business_account','entry'=>[[
        'changes'=>[['value'=>[
            'metadata'=>['phone_number_id'=>$phoneId],
            'messages'=>[['id'=>'wamid.whmcs_001','from'=>'201009991122','type'=>'text',
                'text'=>['body'=>'موقعي بيعرض خطأ SSL']]]]
        ]]]]];
    $in=wa_webhook_ingest($conn,$payload);
    bridge_check($in['accepted']===1,'incoming WhatsApp message accepted');
    $convId=(int)($in['conversation_ids'][0]??0);
    bridge_check($convId>0,'conversation returned for internal job');
    $cid=$convId;
    $job=one("SELECT * FROM wa_ai_jobs WHERE account_id=4 AND connection_id=? AND conversation_id=?",
        [$channel,$cid]);
    bridge_check($job!==null&&$job['status']==='pending','durable job created');
    $calls=0;
    $validBridge=static function($endpoint,$token,$json)use(&$calls):array{
        $calls++;
        $p=json_decode($json,true);
        bridge_check($p['action']==='draft_reviewed_reply','asks existing WHMCS AI support reviewer');
        bridge_check($p['customer_identity_verified']===false,'CallerID never proves WHMCS identity');
        bridge_check($p['allowed_actions']===[],'no billing, ticket edits or WHMCS writes');
        bridge_check(count($p['history'])>=1,'WHMCS agent receives contextual transcript');
        return ['status'=>200,'body'=>json_encode([
            'agent'=>'ai_support_agent','decision'=>'reply',
            'reviewer_approved'=>true,'actions'=>[],
            'reply_text'=>'أهلاً بحضرتك، خلينا نراجع شهادة SSL من غير ما نغيّر إعدادات حسابك.'
        ],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
    };
    $meta=static function($endpoint,$token,$body):array{
        bridge_check(str_starts_with($endpoint,'https://graph.facebook.com/'),
            'sends only to Meta Graph');
        bridge_check(str_contains($body,'messaging_product'),'official Meta payload');
        return ['status'=>200,'body'=>'{"messages":[{"id":"wamid.ai_reply_001"}]}'];
    };
    $out=wa_whmcs_run_job((int)$job['id'],$validBridge,$meta);
    bridge_check($out['status']==='accepted_not_delivered','approved WHMCS draft sent to Meta');
    bridge_check($calls===1,'WHMCS agent asked once');
    bridge_check(wa_whmcs_run_job((int)$job['id'],$validBridge,$meta)['status']==='not_pending',
        'no duplicate AI sends on reprocessing');
    $msg=one("SELECT * FROM wa_messages WHERE account_id=4 AND conversation_id=? AND role='ai'",[$cid]);
    bridge_check($msg!==null&&$msg['delivery_status']==='accepted','AI reply attributed and not labeled delivered');
    $payload['entry'][0]['changes'][0]['value']['messages'][0]['id']='wamid.whmcs_002';
    wa_webhook_ingest($conn,$payload);
    $next=one("SELECT id FROM wa_ai_jobs WHERE account_id=4 AND conversation_id=? AND status='pending'",[$cid]);
    bridge_check($next!==null,'second message queues a new job');
    wa_human_takeover(4,$cid,true);
    bridge_check(wa_whmcs_run_job((int)$next['id'],$validBridge,$meta)['status']==='skipped',
        'human takeover cancels stale pending AI reply');
    bridge_check($calls===1,'no model call after human lock');
    wa_release_human(4,$cid);
    execute("UPDATE wa_conversations SET ai_pause_until='2000-01-01 00:00:00' WHERE id=? AND account_id=4",[$cid]);
    $payload['entry'][0]['changes'][0]['value']['messages'][0]['id']='wamid.whmcs_003';
    wa_webhook_ingest($conn,$payload);
    $unapproved=one("SELECT id FROM wa_ai_jobs WHERE account_id=4 AND conversation_id=? AND status='pending'",[$cid]);
    bridge_check($unapproved!==null,'new inbound after human release queued');
    $denial=wa_whmcs_run_job((int)$unapproved['id'],static fn()=>[
        'status'=>200,'body'=>'{"agent":"ai_support_agent","decision":"reply","reviewer_approved":false,"actions":[],"reply_text":"Unreviewed"}'
    ],$meta);
    bridge_check($denial['status']==='failed','unreviewed LLM reply is never sent');
    bridge_check((int)one("SELECT COUNT(*) n FROM wa_messages WHERE account_id=4 AND conversation_id=? AND role='ai'",[$cid])['n']===1,
        'no phantom AI message from rejected reviewer');
    echo "WHMCS_AI_SUPPORT_WHATSAPP_REVIEWED_REPLY_AND_HUMAN_TAKEOVER_PASS\n";
}finally{
    if($channel!==null){
        execute('DELETE FROM wa_ai_jobs WHERE connection_id=?',[$channel]);
        execute('DELETE FROM wa_audit WHERE account_id=4 AND conversation_id IN (SELECT id FROM wa_conversations WHERE connection_id=?)',[$channel]);
        execute('DELETE FROM wa_messages WHERE connection_id=?',[$channel]);
        execute('DELETE FROM wa_conversations WHERE connection_id=?',[$channel]);
        execute('DELETE FROM wa_connections WHERE id=? AND account_id=4',[$channel]);
    }
}
