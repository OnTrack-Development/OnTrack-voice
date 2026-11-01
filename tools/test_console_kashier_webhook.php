<?php
declare(strict_types=1);
require dirname(__DIR__).'/saas/console/_core/KashierWebhook.php';
function kassert(bool $b,string $text):void {if(!$b)throw new RuntimeException($text);}
$data=['amount'=>1,'channel'=>'online | e-commerce','currency'=>'EGP',
'kashierOrderId'=>'9ad06b17-755b-4e21-9774-aff3e2726ac9','merchantOrderId'=>'1653481557813',
'method'=>'card','orderReference'=>'TEST-ORD-38855','status'=>'SUCCESS',
'transactionId'=>'TX-249893963','transactionResponseCode'=>'00'];
$data['signatureKeys']=array_keys($data);
$sample='9610477b2255b2a8ef84fd89adfaa5f1305ff9c20324205851890f1ea03109f4';
kassert(kashier_verified(['event'=>'pay','data'=>$data],$sample,'11111'),'official signature vector');
kassert(!kashier_verified(['event'=>'pay','data'=>$data],$sample,'wrong'),'wrong credential');
$data['amount']=2;
kassert(!kashier_verified(['event'=>'pay','data'=>$data],$sample,'11111'),'tampering denied');
$data['amount']=1;
$data['signatureKeys']=array_values(array_diff($data['signatureKeys'],['status']));
kassert(!kashier_verified(['event'=>'pay','data'=>$data],$sample,'11111'),'unsigned status rejected');
$data['signatureKeys'][]='status';$data['signatureKeys'][]='status';
kassert(!kashier_verified(['event'=>'pay','data'=>$data],$sample,'11111'),'duplicate keys denied');
kassert(kashier_minor_units('1.50')===150,'minor unit conversion');
kassert(kashier_minor_units('0.01')===1,'single piastre');
kassert(kashier_minor_units('1.009')===null,'fraction error denied');
kassert(kashier_minor_units('1e3')===null,'scientific notation denied');
echo "KASHIER_VERIFIED_WEBHOOK_PASS\n";
