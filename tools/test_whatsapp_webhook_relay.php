<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/WhatsAppSupport.php';

function relay_test(bool $ok,string $why):void {
    if(!$ok)throw new RuntimeException('WHATSAPP_RELAY_FAILED: '.$why);
}
seed_console();
$site=one("SELECT id FROM sites WHERE account_id=4 AND status='active' ORDER BY id LIMIT 1");
relay_test($site!==null,'seeded demo site');
$secret=bin2hex(random_bytes(32));$verify=bin2hex(random_bytes(24));
$public=bin2hex(random_bytes(18));
$id=null;
try{
    $id=wa_create_connection(4,(int)$site['id'],'Webhook Relay Demo','1099888777666',
        'fake-meta-access-token-12345','fake-meta-app-secret-12345',$verify,'','',0,
        '123456789012345','987654321098765','v26.0',$public,$secret);
    $conn=wa_connection($id,4);
    relay_test($conn['state']==='draft','no fake active state');
    relay_test((int)$conn['webhook_relay_enabled']===1,'relay enabled only when requested');
    relay_test($conn['webhook_relay_secret_enc']!==$secret,'relay signing key encrypted');
    relay_test($conn['waba_id']==='123456789012345'&&$conn['meta_app_id']==='987654321098765',
        'Meta manual identifiers persisted');
    relay_test(wa_graph_version($conn)==='v26.0','Meta Graph API selected version');
    relay_test(str_contains(wa_public_callback($public),$public),'unique callback for account');

    $event=['version'=>1,'channel'=>'whatsapp','event'=>'message.received',
        'phone_number_id'=>'1099888777666','message'=>[
            'id'=>'wamid.relay_test_001','from'=>'201002223344',
            'name'=>'عميل تجريبي','text'=>'محتاج أعرف تفاصيل المنتج']];
    $raw=json_encode($event,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $signed='sha256='.hash_hmac('sha256',$raw,$secret);
    relay_test(wa_relay_signature_valid($conn,$signed,$raw),'HMAC verified');
    relay_test(!wa_relay_signature_valid($conn,$signed,$raw.' '),'modified body rejected');
    relay_test(!wa_relay_signature_valid($conn,'sha256='.str_repeat('0',64),$raw),
        'wrong signature rejected');
    relay_test(!wa_signature_valid($conn,$signed,$raw),'provider signature separate from Meta App Secret');
    $normalized=wa_relay_normalize($conn,$event);
    relay_test($normalized['object']==='whatsapp_business_account','normalized into Meta contract');
    $received=wa_webhook_ingest($conn,$normalized);
    relay_test($received['accepted']===1,'signed provider event inserted');
    relay_test(wa_webhook_ingest($conn,$normalized)['duplicates']===1,'relay retry idempotent');
    $conv=one('SELECT * FROM wa_conversations WHERE account_id=? AND connection_id=?',[4,$id]);
    relay_test($conv!==null&&$conv['contact_name']==='عميل تجريبي','inbox customer attributed');
    relay_test(one('SELECT * FROM wa_conversations WHERE account_id=? AND connection_id=?',[5,$id])===null,
        'tenant isolation');
    $wrong=$event;$wrong['phone_number_id']='99999999999';
    $rejected=false;
    try{wa_relay_normalize($conn,$wrong);}catch(InvalidArgumentException){$rejected=true;}
    relay_test($rejected,'foreign sender phone id cannot spoof tenant');
    $bad=$event;$bad['event']='payment.received';
    $rejected=false;
    try{wa_relay_normalize($conn,$bad);}catch(InvalidArgumentException){$rejected=true;}
    relay_test($rejected,'out-of-scope webhook cannot execute other integrations');
    $providerPayload=$normalized;
    relay_test(wa_relay_normalize($conn,$providerPayload)===$providerPayload,
        'raw Meta payload can be forwarded unchanged by trusted provider');
    // No AI activation without a published agent or Meta phone verification.
    relay_test((int)$conn['ai_enabled']===0,'AI disabled on new connection');
    echo "WHATSAPP_SIGNED_WEBHOOK_RELAY_PASS\n";
}finally{
    if($id!==null){
        execute('DELETE FROM wa_ai_jobs WHERE connection_id=?',[$id]);
        execute('DELETE FROM wa_messages WHERE connection_id=?',[$id]);
        execute('DELETE FROM wa_conversations WHERE connection_id=?',[$id]);
        execute('DELETE FROM wa_connections WHERE id=? AND account_id=?',[$id,4]);
    }
}
