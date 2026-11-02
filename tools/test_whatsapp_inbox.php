<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/WhatsAppSupport.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
function wa_check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException('WHATSAPP_INBOX_TEST_FAIL: '.$why);}
seed_console();
$site=one("SELECT id FROM sites WHERE account_id=4 AND status='active' ORDER BY id LIMIT 1");
wa_check($site!==null,'seeded tenant site');
$siteId=(int)$site['id'];
$numberId='1099999112233';$verify='test-verify-token-secret-100';
try{
    $id=wa_create_connection(4,$siteId,'Support', $numberId,'meta-access-token-12345',
        'meta-app-secret-12345',$verify);
    $conn=wa_connection($id,4);
    wa_check($conn['state']==='draft','starts draft not live');
    wa_check($conn['app_secret_enc']!=='meta-app-secret-12345','Meta app secret encrypted');
    wa_check($conn['access_token_enc']!=='meta-access-token-12345','Meta token encrypted');
    wa_check(wa_webhook_challenge($conn,'subscribe',$verify,'abc123')==='abc123','Meta GET handshake');
    wa_check(wa_webhook_challenge($conn,'subscribe','invalid','abc123')===null,'bad verify token blocked');
    $raw='{"object":"whatsapp_business_account"}';
    $signature='sha256='.hash_hmac('sha256',$raw,'meta-app-secret-12345');
    wa_check(wa_signature_valid($conn,$signature,$raw),'valid HMAC');
    wa_check(!wa_signature_valid($conn,$signature,$raw.'x'),'modified raw body rejected');
    wa_check(!wa_signature_valid($conn,'sha256=bad',$raw),'invalid Meta HMAC rejected');
    $result=wa_test_meta_connection($conn,static fn($url,$token)=>[
        'status'=>200,'body'=>json_encode(['id'=>$numberId,'display_phone_number'=>'+201122334455'])
    ]);
    wa_check($result['verified'],'Meta phone ID verification through mocked provider');
    $conn=wa_connection($id,4);
    wa_check($conn['state']==='verified','only verified test activates manual send');
    $payload=['object'=>'whatsapp_business_account','entry'=>[[
        'id'=>'waba','changes'=>[['field'=>'messages','value'=>[
        'metadata'=>['phone_number_id'=>$numberId],
        'contacts'=>[['wa_id'=>'201001122334','profile'=>['name'=>'أحمد']]],
        'messages'=>[['from'=>'201001122334','id'=>'wamid.test_999','type'=>'text',
            'text'=>['body'=>'موقعي مش شغال، ممكن مساعدة؟']]]
    ]]]]]];
    $received=wa_webhook_ingest($conn,$payload);
    wa_check($received['accepted']===1,'verified inbound message saved');
    wa_check(wa_webhook_ingest($conn,$payload)['duplicates']===1,'Meta retry idempotent');
    $conv=one('SELECT * FROM wa_conversations WHERE connection_id=?',[$id]);
    wa_check($conv!==null&&$conv['contact_name']==='أحمد','conversation keyed by phone and name');
    $cid=(int)$conv['id'];
    wa_check((int)one('SELECT COUNT(*) n FROM wa_messages WHERE conversation_id=?',[$cid])['n']===1,
        'exactly one inbound message');
    $crossTenantDenied=false;
    try{wa_conversation($cid,5);}catch(InvalidArgumentException){$crossTenantDenied=true;}
    wa_check($crossTenantDenied,'cross tenant denied');
    $wrong=$payload;
    $wrong['entry'][0]['changes'][0]['value']['metadata']['phone_number_id']='00000000000';
    wa_check(wa_webhook_ingest($conn,$wrong)['accepted']===0,'foreign Meta number ignored');
    $sent=wa_manual_reply(4,$cid,'أهلاً بحضرتك، هتابع المشكلة معاك',
        static fn($url,$token,$body)=>[
            'status'=>200,'body'=>'{"messages":[{"id":"wamid.sent_123"}]}'
        ]);
    wa_check($sent['status']==='accepted_not_delivered','Meta acceptance is not confirmed delivery');
    wa_check((int)one('SELECT COUNT(*) n FROM wa_messages WHERE conversation_id=?',[$cid])['n']===2,
        'manual reply stored after Meta accepted');
    $after=wa_conversation($cid,4);
    wa_check(!wa_ai_allowed($after),'human reply pauses AI for 15 minutes');
    wa_check((int)$after['generation']>=2,'human takeover increments state generation');
    wa_human_takeover(4,$cid,true);
    $locked=wa_conversation($cid,4);
    wa_check($locked['human_locked_at']!==null,'human permanent lock');
    wa_release_human(4,$cid);
    wa_check(wa_conversation($cid,4)['human_locked_at']===null,'manual human unlock');
    execute("UPDATE wa_conversations SET ai_pause_until='2000-01-01 00:00:00' WHERE id=? AND account_id=?",[$cid,4]);
    wa_check(wa_ai_allowed(wa_conversation($cid,4)),'AI can resume after pause expires');
    execute("UPDATE wa_conversations SET last_customer_at='2000-01-01 00:00:00' WHERE id=? AND account_id=?",[$cid,4]);
    $blocked=false;try{wa_manual_reply(4,$cid,'Outside 24h',
        static fn()=>['status'=>200,'body'=>'{"messages":[{"id":"should-not-send"}]}']);}
    catch(RuntimeException){$blocked=true;}
    wa_check($blocked,'24h window prevents free-form send');
    $blocked=false;try{wa_bridge_url('https://127.0.0.1/modules/addons/ai_support_agent/bridge.php');}
    catch(InvalidArgumentException){$blocked=true;}
    wa_check($blocked,'bridge cannot target localhost or arbitrary URL');
    $blocked=false;try{wa_bridge_url('https://services.ontrackegy.com/admin/login.php');}
    catch(InvalidArgumentException){$blocked=true;}
    wa_check($blocked,'bridge only within original WHMCS AI addon namespace');
    wa_check(str_contains(wa_bridge_url('https://services.ontrackegy.com/modules/addons/ai_support_agent/whatsapp_bridge.php'),
        'ai_support_agent'),'original WHMCS addon path');
    $member=one("SELECT user_id FROM memberships WHERE account_id=4 AND role='owner' ORDER BY user_id LIMIT 1");
    wa_check($member!==null,'site owner fixture');
    $_SESSION=['uid'=>(int)$member['user_id'],'account_id'=>4];
    $_GET=['tab'=>'whatsapp','conversation'=>(string)$cid];
    ob_start();render_whatsapp(['account_id'=>4,'role'=>'owner','kind'=>'customer']);
    $html=(string)ob_get_clean();
    wa_check(str_contains($html,'WhatsApp · صندوق الوارد'),'operator Inbox renders');
    wa_check(str_contains($html,'إرسال عبر واتساب'),'manual reply UI renders');
    wa_check(str_contains($html,'استلام المحادثة بالكامل'),'human takeover UI renders');
    wa_check(str_contains($html,'whatsapp-webhook.php?id='),'verified webhook URL displayed');
    echo "WHATSAPP_SIGNED_INBOX_HUMAN_PAUSE_AND_MANUAL_META_SEND_PASS\n";
}finally{
    if(isset($id)){
        execute('DELETE FROM wa_audit WHERE account_id=4 AND conversation_id IN (SELECT id FROM wa_conversations WHERE connection_id=?)',[$id]);
        execute('DELETE FROM wa_messages WHERE connection_id=?',[$id]);
        execute('DELETE FROM wa_conversations WHERE connection_id=?',[$id]);
        execute('DELETE FROM wa_connections WHERE id=? AND account_id=?',[$id,4]);
    }
}
