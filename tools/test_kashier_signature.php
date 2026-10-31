<?php
declare(strict_types=1);
function config(string $key,mixed $default=null):mixed {
    if($key==='services.kashier.api_key')return '11111';
    return $default;
}
require dirname(__DIR__).'/saas/app/Support/KashierGateway.php';
use App\Support\KashierGateway;
$data=[
    'amount'=>1,'channel'=>'online | e-commerce','currency'=>'EGP',
    'kashierOrderId'=>'9ad06b17-755b-4e21-9774-aff3e2726ac9',
    'merchantOrderId'=>'1653481557813','method'=>'card','orderReference'=>'TEST-ORD-38855',
    'status'=>'SUCCESS','transactionId'=>'TX-249893963','transactionResponseCode'=>'00'
];
$data['signatureKeys']=array_keys($data);
$signature='9610477b2255b2a8ef84fd89adfaa5f1305ff9c20324205851890f1ea03109f4';
if(!KashierGateway::validWebhook(['event'=>'pay','data'=>$data],$signature))
    throw new RuntimeException('valid Kashier sample rejected');
$data['status']='FAILURE';
if(KashierGateway::validWebhook(['event'=>'pay','data'=>$data],$signature))
    throw new RuntimeException('tampered status accepted');
$data['status']='SUCCESS';unset($data['signatureKeys'][7]);$data['signatureKeys']=array_values($data['signatureKeys']);
if(KashierGateway::validWebhook(['event'=>'pay','data'=>$data],$signature))
    throw new RuntimeException('missing signed status accepted');
echo "Kashier webhook HMAC tests PASS\n";
