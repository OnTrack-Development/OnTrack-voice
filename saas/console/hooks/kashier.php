<?php
declare(strict_types=1);
require __DIR__.'/../_core/Bootstrap.php';
require __DIR__.'/../_core/OrderClaim.php';
require __DIR__.'/../_core/Checkout.php';
require __DIR__.'/../_core/KashierWebhook.php';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function kashier_reply(int $code):never {http_response_code($code);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')kashier_reply(405);
if(PHP_SAPI!=='cli' && (empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'))kashier_reply(426);
$raw=(string)file_get_contents('php://input',false,null,0,16385);
if(strlen($raw)>16384)kashier_reply(413);
try{
    // Hosted out of public_html; unavailable until a real merchant is configured.
    $configPath=private_path().'/kashier.json';
    if(!is_file($configPath))kashier_reply(503);
    $config=json_decode((string)file_get_contents($configPath),true,16,JSON_THROW_ON_ERROR);
    $key=is_array($config)?($config['payment_api_key']??null):null;
    $mid=is_array($config)?($config['merchant_id']??null):null;
    $integration=is_array($config)?($config['integration_id']??null):null;
    if(!is_string($key)||strlen($key)<5||!is_string($mid)||$mid===''||($integration!==null&&(!is_int($integration)||$integration<1)))
        kashier_reply(503);
    $payload=json_decode($raw,true,24,JSON_THROW_ON_ERROR);
    if(!is_array($payload)||!kashier_verified($payload,
        (string)($_SERVER['HTTP_X_KASHIER_SIGNATURE']??''),$key))kashier_reply(401);
    $data=$payload['data'];
    // Official payment webhook samples do not contain merchantId. The private
    // per-merchant API key verifies source; optional MID must itself be signed.
    if(isset($data['merchantId'])&&in_array('merchantId',$data['signatureKeys']??[],true)
       &&(!is_string($data['merchantId'])||!hash_equals($mid,$data['merchantId'])))
        kashier_reply(403);
    // Authenticated provider events must be tied to configured OnTrack integration.
    init_schema();
    if($integration!==null){
        $row=one("SELECT id FROM integrations WHERE id=? AND status='active'",[$integration]);
        if(!$row)kashier_reply(403);
    }
    $result=kashier_settle($payload,$integration);
    // 200 and 409 both acknowledge Kashier deliveries.
    kashier_reply(($result['duplicate']??false)?409:200);
}catch(JsonException|InvalidArgumentException $e){kashier_reply(422);}
catch(Throwable $e){kashier_reply(503);}
