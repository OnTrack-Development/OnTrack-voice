<?php
declare(strict_types=1);
/**
 * Meta webhook: public, signature verified, never uses user session or CSRF.
 * Does not execute model output, ticket actions or create WHMCS customers.
 */
require __DIR__.'/_core/Bootstrap.php';
require __DIR__.'/_core/WhatsAppSupport.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
try{init_schema();}
catch(Throwable){http_response_code(503);echo '{"ok":false}';exit;}
$public=(string)($_GET['id']??'');
if(!preg_match('/^[a-f0-9]{36}$/D',$public)){http_response_code(404);echo '{"ok":false}';exit;}
$connection=one('SELECT * FROM wa_connections WHERE public_id=?',[$public]);
if(!$connection){http_response_code(404);echo '{"ok":false}';exit;}
if(($_SERVER['REQUEST_METHOD']??'')==='GET'){
    $mode=(string)($_GET['hub_mode']??$_GET['hub.mode']??'');
    $token=(string)($_GET['hub_verify_token']??$_GET['hub.verify_token']??'');
    $challenge=(string)($_GET['hub_challenge']??$_GET['hub.challenge']??'');
    $response=wa_webhook_challenge($connection,$mode,$token,$challenge);
    if($response===null){http_response_code(403);echo '{"ok":false}';exit;}
    header('Content-Type: text/plain; charset=utf-8');
    echo $response;exit;
}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo '{"ok":false}';exit;}
$raw=file_get_contents('php://input',false,null,0,262145);
if(!is_string($raw)||strlen($raw)>262144){
    http_response_code(413);echo '{"ok":false}';exit;
}
$signature=(string)($_SERVER['HTTP_X_HUB_SIGNATURE_256']??'');
if(!wa_signature_valid($connection,$signature,$raw)){
    http_response_code(403);echo '{"ok":false}';exit;
}
try{
    $data=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($data))throw new InvalidArgumentException('Invalid payload');
    $result=wa_webhook_ingest($connection,$data);
    http_response_code(200);
    echo json_encode(['ok'=>true,'accepted'=>$result['accepted'],'duplicates'=>$result['duplicates']],
        JSON_THROW_ON_ERROR);
}catch(Throwable){
    // Real DB/provider errors must cause Meta retry rather than dropping events.
    http_response_code(503);echo '{"ok":false}';
}
