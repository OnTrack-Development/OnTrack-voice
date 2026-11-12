<?php
declare(strict_types=1);
/**
 * Single Meta App callback for tenant numbers onboarded via Embedded Signup.
 * Meta app identity verified BEFORE any phone-to-tenant lookup. Never trusts
 * account_id supplied in a webhook body.
 */
require __DIR__.'/_core/Bootstrap.php';
require __DIR__.'/_core/WhatsAppOnboarding.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try{init_schema();}catch(Throwable){http_response_code(503);echo '{"ok":false}';exit;}
$config=wa_platform_onboarding();
if(($_SERVER['REQUEST_METHOD']??'')==='GET'){
    $mode=(string)($_GET['hub_mode']??$_GET['hub.mode']??'');
    $token=(string)($_GET['hub_verify_token']??$_GET['hub.verify_token']??'');
    $challenge=(string)($_GET['hub_challenge']??$_GET['hub.challenge']??'');
    if($mode!=='subscribe'||$token===''||strlen($token)>200||strlen($challenge)>300||
        !hash_equals((string)($config['verify_token_hash']??''),hash('sha256',$token))){
        http_response_code(403);echo '{"ok":false}';exit;
    }
    header('Content-Type: text/plain; charset=utf-8');echo $challenge;exit;
}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo '{"ok":false}';exit;}
$raw=file_get_contents('php://input',false,null,0,262145);
if(!is_string($raw)||strlen($raw)>262144){http_response_code(413);echo '{"ok":false}';exit;}
$secret=unseal_secret($config['app_secret_enc']??null);
$sig=(string)($_SERVER['HTTP_X_HUB_SIGNATURE_256']??'');
if(!$secret||!preg_match('/^sha256=[a-f0-9]{64}$/D',$sig)||
    !hash_equals('sha256='.hash_hmac('sha256',$raw,$secret),$sig)){
    http_response_code(403);echo '{"ok":false}';exit;
}
try{
    $payload=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($payload)||($payload['object']??null)!=='whatsapp_business_account')
        throw new InvalidArgumentException('invalid_platform_meta_payload');
    $accepted=0;$duplicates=0;$pending=[];
    foreach((array)($payload['entry']??[]) as $entry){
        foreach((array)($entry['changes']??[]) as $change){
            $value=$change['value']??null;
            if(!is_array($value))continue;
            $phone=(string)($value['metadata']['phone_number_id']??'');
            if(!preg_match('/^[0-9]{5,100}$/D',$phone))continue;
            $connection=one("SELECT * FROM wa_connections
                WHERE phone_number_id=? AND onboarding_method IN ('embedded_signup','coexistence')
                LIMIT 1",[$phone]);
            if(!$connection)continue;
            // One phone per tenant/account globally. Pass ONLY this asset's
            // event into the ordinary tenant-isolated message ingestion.
            $scoped=['object'=>'whatsapp_business_account',
                'entry'=>[['changes'=>[['value'=>$value,'field'=>'messages']]]]];
            $result=wa_webhook_ingest($connection,$scoped);
            $accepted+=(int)($result['accepted']??0);
            $duplicates+=(int)($result['duplicates']??0);
            if($accepted>0 && !empty($connection['ai_enabled'])){
                $pending[]=['connection'=>$connection,'ids'=>$result['conversation_ids']??[]];
            }
        }
    }
    http_response_code(200);
    echo json_encode(['ok'=>true,'accepted'=>$accepted,'duplicates'=>$duplicates],JSON_THROW_ON_ERROR);
    if($pending&&(function_exists('fastcgi_finish_request')||function_exists('litespeed_finish_request'))){
        if(function_exists('fastcgi_finish_request'))fastcgi_finish_request();
        else litespeed_finish_request();
        ignore_user_abort(true);
        require_once __DIR__.'/_core/WhatsAppNativeAgent.php';
        foreach(array_slice($pending,0,5) as $job){
            try{wa_native_run_pending($job['connection'],$job['ids']);}
            catch(Throwable){/* durable worker remains available */}
        }
    }
}catch(Throwable){http_response_code(503);echo '{"ok":false}';}
