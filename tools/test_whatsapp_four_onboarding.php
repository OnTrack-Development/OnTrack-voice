<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/WhatsAppOnboarding.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
function onboarding_check(bool $pass,string $reason):void {
    if(!$pass)throw new RuntimeException('WA_FOUR_METHODS_FAIL: '.$reason);
}
seed_console();
$site=one("SELECT id FROM sites WHERE account_id=4 AND status='active' ORDER BY id LIMIT 1");
onboarding_check($site!==null,'demo tenant site');
$previous=wa_platform_onboarding();
$ids=[];
try{
    onboarding_check(!wa_onboarding_ready($previous,'coexist')||!empty($previous['coexist_enabled']),
        'admin feature gate');
    wa_onboarding_save([
        'app_id'=>'1234567890123',
        'app_secret'=>'test_platform_meta_secret_1234567',
        'signup_config_id'=>'1000000000001',
        'coexist_config_id'=>'1000000000002',
        'verify_token'=>'test_platform_verify_1234567890',
        'graph_version'=>'v26.0',
        'signup_enabled'=>true,'coexist_enabled'=>false
    ]);
    $cfg=wa_platform_onboarding();
    onboarding_check(wa_onboarding_ready($cfg,'signup'),'admin-enabled Embedded Signup shown');
    onboarding_check(!wa_onboarding_ready($cfg,'coexist'),'disabled Coexistence hidden');
    $member=one("SELECT user_id FROM memberships WHERE account_id=4 AND role='owner' LIMIT 1");
    onboarding_check($member!==null,'tenant owner can open onboarding');
    $_SESSION=['uid'=>(int)$member['user_id'],'account_id'=>4];
    $_GET=['tab'=>'whatsapp'];
    ob_start();render_whatsapp(['account_id'=>4,'kind'=>'customer','role'=>'owner']);
    $html=(string)ob_get_clean();
    onboarding_check(str_contains($html,'data-wa-method="meta_direct"'),'direct Meta mode shown');
    onboarding_check(str_contains($html,'data-wa-method="existing_webhook"'),'previously connected mode shown');
    onboarding_check(str_contains($html,'data-wa-method="signup"'),'enabled signup mode shown');
    onboarding_check(!str_contains($html,'data-wa-method="coexist"'),'disabled coexist hidden');
    onboarding_check(str_contains($html,'https://business.facebook.com/settings/system-users'),
        'access token field includes direct Meta help link');
    onboarding_check(str_contains($html,'https://developers.facebook.com/apps/'),
        'App ID and App Secret source links present');
    onboarding_check(str_contains($html,'wa-existing-receiver'),'existing callback preserved with separate OnTrack relay');
    $blocked=false;
    try{wa_onboarding_finish(4,(int)$site['id'],0,'coexist',
        'meta_demo_auth_code_123','2000000000012','2000000000013');}
    catch(InvalidArgumentException){$blocked=true;}
    onboarding_check($blocked,'disabled mode denied on server');
    $calls=[];
    $transport=static function(string $method,string $url,array $params,string $token)use(&$calls):array{
        $calls[]=['method'=>$method,'url'=>$url,'token_present'=>$token!==''];
        onboarding_check(str_starts_with($url,'https://graph.facebook.com/v26.0/'),
            'API host fixed to Meta Graph');
        if(str_ends_with($url,'/oauth/access_token'))return ['status'=>200,
            'body'=>json_encode(['access_token'=>str_repeat('T',48)])];
        if(str_ends_with($url,'/2000000000012/phone_numbers'))return ['status'=>200,
            'body'=>json_encode(['data'=>[['id'=>'2000000000013']]])];
        if(str_ends_with($url,'/2000000000012/subscribed_apps'))return ['status'=>200,
            'body'=>'{"success":true}'];
        throw new RuntimeException('Unexpected Meta call');
    };
    $result=wa_onboarding_finish(4,(int)$site['id'],0,'signup',
        'meta_demo_auth_code_123','2000000000012','2000000000013',$transport);
    $ids[]=$result['connection_id'];
    onboarding_check($result['status']==='pending_verification','no falsely verified Meta connection');
    $conn=wa_connection($result['connection_id'],4);
    onboarding_check($conn['onboarding_method']==='embedded_signup','signup method attributed');
    onboarding_check((int)$conn['ai_enabled']===0,'AI disabled until explicitly approved');
    onboarding_check($conn['account_id']==4&&$conn['site_id']===$site['id'],'owned by selected tenant');
    onboarding_check($conn['access_token_enc']!==str_repeat('T',48),'tenant token encrypted');
    onboarding_check(count($calls)===3&&$calls[0]['method']==='GET'&&$calls[2]['method']==='POST',
        'Meta auth code exchanged, asset checked, WABA subscribed');
    $blocked=false;
    try{wa_onboarding_finish(4,(int)$site['id'],0,'signup',
        'meta_demo_auth_code_123','2000000000012','2000000000013',$transport);}
    catch(InvalidArgumentException){$blocked=true;}
    onboarding_check($blocked,'globally bound phone not silently hijacked');
    wa_onboarding_save([
        'app_id'=>'1234567890123','app_secret'=>'',
        'signup_config_id'=>'1000000000001','coexist_config_id'=>'1000000000002',
        'verify_token'=>'','graph_version'=>'v26.0',
        'signup_enabled'=>false,'coexist_enabled'=>true
    ]);
    onboarding_check(!wa_onboarding_ready(wa_platform_onboarding(),'signup'),'admin hides signup');
    onboarding_check(wa_onboarding_ready(wa_platform_onboarding(),'coexist'),'coexist only enabled');
    ob_start();render_whatsapp(['account_id'=>4,'kind'=>'customer','role'=>'owner']);
    $coexistMarkup=(string)ob_get_clean();
    onboarding_check(!str_contains($coexistMarkup,'data-wa-method="signup"'),
        'admin-disabled signup disappears from client');
    onboarding_check(str_contains($coexistMarkup,'data-wa-method="coexist"'),
        'admin-enabled coexist appears for client');
    $original='https://services.ontrackegy.com/modules/addons/whatsapp_notifications/webhook.php';
    $existsId=wa_create_existing_connection(4,(int)$site['id'],0,
        'Already connected WhatsApp', $original, 'legacy_verify_token_123456789',
        '2000000000015','2000000000016','1234567890123','v26.0',
        'example_access_token_123456789','example_app_secret_123456789',
        bin2hex(random_bytes(18)),bin2hex(random_bytes(32)));
    $ids[]=$existsId;
    $existing=wa_connection($existsId,4);
    onboarding_check($existing['onboarding_method']==='existing_webhook','existing mode persisted');
    onboarding_check($existing['source_callback_url']===$original,'original callback preserved');
    onboarding_check($existing['source_verify_hash']===hash('sha256','legacy_verify_token_123456789'),
        'original verification token hashed, not stored plaintext');
    onboarding_check(!str_contains(wa_public_callback((string)$existing['public_id']),'services.ontrackegy.com'),
        'OnTrack receiver separate from previous Meta callback');
    onboarding_check((int)$existing['webhook_relay_enabled']===1,'provider relay enabled');
    echo "WHATSAPP_FOUR_ONBOARDING_MODES_GATES_AND_EXISTING_CALLBACK_PASS\n";
}finally{
    foreach($ids as $cid){
        execute('DELETE FROM wa_ai_jobs WHERE connection_id=?',[$cid]);
        execute('DELETE FROM wa_messages WHERE connection_id=?',[$cid]);
        execute('DELETE FROM wa_conversations WHERE connection_id=?',[$cid]);
        execute('DELETE FROM wa_connections WHERE id=? AND account_id=4',[$cid]);
    }
    execute('UPDATE wa_platform_onboarding SET app_id=?,app_secret_enc=?,signup_config_id=?,
        coexist_config_id=?,graph_version=?,verify_token_hash=?,signup_enabled=?,
        coexist_enabled=?,updated_at=? WHERE id=1',[
        $previous['app_id']??'', $previous['app_secret_enc']??null,
        $previous['signup_config_id']??'', $previous['coexist_config_id']??'',
        $previous['graph_version']??'v26.0', $previous['verify_token_hash']??null,
        (int)($previous['signup_enabled']??0),(int)($previous['coexist_enabled']??0),now()
    ]);
}
