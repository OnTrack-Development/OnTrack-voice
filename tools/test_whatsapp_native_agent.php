<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/WhatsAppNativeAgent.php';
function wa_native_assert(bool $condition,string $reason):void {
    if(!$condition)throw new RuntimeException('WHATSAPP_NATIVE_FAIL: '.$reason);
}
seed_console();
$agent=one("SELECT * FROM agents WHERE account_id=4 AND site_id=1 ORDER BY id LIMIT 1");
wa_native_assert($agent!==null,'demo site has OnTrack agent');
$accountId=4;$siteId=(int)$agent['site_id'];$agentId=(int)$agent['id'];
$previousStatus=(string)$agent['status'];
$previous=published_agent_flow($accountId,$siteId,$agentId);
$previousVersion=(int)($previous['revision']??0);
$channel=null;
$oldKey=getenv('ONTRACK_GEMINI_API_KEY');
$oldModel=getenv('ONTRACK_GEMINI_LIVE_MODEL');
try {
    if(!$previous)publish_agent_flow($accountId,$agentId,1);
    $channel=wa_create_connection($accountId,$siteId,'OnTrack Independent Support',
        '1099999555444','meta-token-for-demo-only','meta-secret-demo-12345',
        'verify-token-demo-only-555','','',$agentId);
    $conn=wa_connection($channel,$accountId);
    wa_native_assert((int)$conn['ai_agent_id']===$agentId,'native agent bound to site');
    wa_native_assert((int)$conn['ai_enabled']===0,'native AI disabled by default');
    wa_native_assert(empty($conn['whmcs_bridge_url']),'no WHMCS bridge needed');
    wa_test_meta_connection($conn,static fn()=>[
        'status'=>200,'body'=>'{"id":"1099999555444"}']);
    putenv('ONTRACK_GEMINI_API_KEY=demo-only-placeholder-no-network');
    putenv('ONTRACK_GEMINI_LIVE_MODEL=gemini-3.5-flash');
    wa_native_toggle($accountId,$channel,true);
    $conn=wa_connection($channel,$accountId);
    wa_native_assert((int)$conn['ai_enabled']===1,'standalone mode enabled without WHMCS');

    $payload=['object'=>'whatsapp_business_account','entry'=>[[
        'changes'=>[['value'=>[
            'metadata'=>['phone_number_id'=>'1099999555444'],
            'messages'=>[['id'=>'wamid.native_001','from'=>'201002223344',
                'type'=>'text','text'=>['body'=>'ما هي مواعيد المتجر؟']]]
        ]]]
    ]]];
    $res=wa_webhook_ingest($conn,$payload);
    wa_native_assert($res['accepted']===1,'native inbound received');
    $conversationId=(int)$res['conversation_ids'][0];
    $job=one("SELECT id,status FROM wa_ai_jobs WHERE connection_id=? AND conversation_id=?",
        [$channel,$conversationId]);
    wa_native_assert($job!==null&&$job['status']==='pending','native job queued');
    $calls=0;$sent=0;
    $generator=static function(array $request)use(&$calls):string{
        $calls++;
        wa_native_assert(isset($request['systemInstruction']['parts'][0]['text']),
            'server-owned instructions used');
        return $calls===1
          ? '{"decision":"reply","reply_text":"المتجر مفتوح حسب المعلومات المنشورة."}'
          : '{"decision":"reply","reply_text":"المتجر مفتوح حسب المعلومات المنشورة.","approved":true}';
    };
    $meta=static function(string $url,string $token,string $body)use(&$sent):array{
        $sent++;
        wa_native_assert(str_starts_with($url,'https://graph.facebook.com/'),'official Meta only');
        wa_native_assert(!str_contains($body,'WHMCS'),'no addon data dependency');
        return ['status'=>200,'body'=>'{"messages":[{"id":"wamid.native_out_001"}]}'];
    };
    $out=wa_native_run_job((int)$job['id'],$generator,$meta);
    wa_native_assert($out['status']==='accepted_not_delivered','native reviewed response accepted by Meta');
    wa_native_assert($calls===2&&$sent===1,'two AI passes, one Meta send');
    wa_native_assert(wa_native_run_job((int)$job['id'],$generator,$meta)['status']==='not_pending',
        'processed jobs never sent twice');
    $row=one("SELECT role,delivery_status FROM wa_messages WHERE connection_id=?
        AND provider_message_id='wamid.native_out_001'",[$channel]);
    wa_native_assert($row['role']==='ai'&&$row['delivery_status']==='accepted',
        'AI attributed; not falsely marked delivered');

    $buttonsSent=0;
    wa_manual_reply(4,$conversationId,'اختار المناسب',static function($url,$token,$body)use(&$buttonsSent):array{
        $p=json_decode($body,true,20,JSON_THROW_ON_ERROR);
        wa_native_assert(($p['type']??null)==='interactive','official interactive button payload');
        wa_native_assert(count($p['interactive']['action']['buttons']??[])===2,'two quick replies');
        $buttonsSent++;
        return ['status'=>200,'body'=>'{"messages":[{"id":"wamid.manual_buttons_001"}]}'];
    },['التفاصيل','موظف']);
    wa_native_assert($buttonsSent===1,'human buttons sent to Meta mock');
    wa_native_assert(!wa_ai_allowed(wa_conversation($conversationId,4)),
        'manual reply pauses native agent');

    wa_native_toggle($accountId,$channel,false);
    wa_native_assert((int)wa_connection($channel,4)['ai_enabled']===0,'native AI can be disabled per number');
    $payload['entry'][0]['changes'][0]['value']['messages'][0]['id']='wamid.native_002';
    $res=wa_webhook_ingest(wa_connection($channel,4),$payload);
    wa_native_assert($res['accepted']===1,'inbound remains available with AI disabled');
    wa_native_assert((int)one('SELECT COUNT(*) n FROM wa_ai_jobs WHERE connection_id=?',[$channel])['n']===1,
        'disabled agent queues no second reply');
    echo "WHATSAPP_NATIVE_INDEPENDENT_AGENT_REVIEW_AND_BUTTONS_PASS\n";
}finally{
    if($channel!==null){
        execute('DELETE FROM wa_ai_jobs WHERE connection_id=?',[$channel]);
        execute('DELETE FROM wa_audit WHERE account_id=? AND conversation_id IN
            (SELECT id FROM wa_conversations WHERE connection_id=?)',[$accountId,$channel]);
        execute('DELETE FROM wa_messages WHERE connection_id=?',[$channel]);
        execute('DELETE FROM wa_conversations WHERE connection_id=?',[$channel]);
        execute('DELETE FROM wa_connections WHERE id=? AND account_id=?',[$channel,$accountId]);
    }
    if(!$previous){
        execute('DELETE FROM agent_flow_versions WHERE account_id=? AND site_id=? AND agent_id=? AND revision>?',
            [$accountId,$siteId,$agentId,$previousVersion]);
        execute('UPDATE agents SET status=? WHERE id=? AND account_id=?',[$previousStatus,$agentId,$accountId]);
    }
    putenv('ONTRACK_GEMINI_API_KEY'.($oldKey===false?'':'='.$oldKey));
    putenv('ONTRACK_GEMINI_LIVE_MODEL'.($oldModel===false?'':'='.$oldModel));
}
