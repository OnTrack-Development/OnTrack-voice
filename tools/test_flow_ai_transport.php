<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/_core/FlowAiTransport.php';
function ensure_flow_transport(bool $ok,string $message):void {
    if(!$ok)throw new RuntimeException('FLOW_GOOGLE_RETRY_TEST_FAILED: '.$message);
}
$payload=['systemInstruction'=>['parts'=>[['text'=>'Preview-only agent editor']]],
    'contents'=>[['role'=>'user','parts'=>[['text'=>'اختبر بدون نشر']]]]];
$model='gemini-3.8-flash';
$key='fixture_not_a_real_google_key';
$attempts=0;
$exchange=static function(int $try,string $body,string $m)use(&$attempts):array {
    ++$attempts;
    ensure_flow_transport($m==='gemini-3.8-flash','model remains configured');
    ensure_flow_transport(str_contains($body,'اختبر بدون نشر'),'retry reuses same prompt');
    return $try===0
        ?['status'=>503,'body'=>'{"error":"temporary"}','errno'=>0]
        :['status'=>200,'body'=>'{"candidates":[{"finishReason":"STOP"}]}','errno'=>0];
};
$result=flow_ai_google_request($payload,$key,$model,90000,$exchange);
ensure_flow_transport(str_contains($result,'candidates')&&$attempts===2,
    'HTTP 503 gets one bounded retry then succeeds');
$attempts=0;
$result=flow_ai_google_request($payload,$key,$model,90000,
    static function(int $try)use(&$attempts):array{
        ++$attempts;
        return $try===0?['status'=>0,'errno'=>28,'body'=>false]:
            ['status'=>200,'errno'=>0,'body'=>'{"ok":true}'];
    });
ensure_flow_transport($result==='{"ok":true}'&&$attempts===2,
    'HTTP 0 timeout gets retry when no HTTP response arrived');
$attempts=0;$denied=false;
try{
    flow_ai_google_request($payload,$key,$model,90000,
        static function()use(&$attempts):array{
            ++$attempts;
            return ['status'=>403,'errno'=>0,'body'=>'{"error":"permission denied"}'];
        });
}catch(RuntimeException $error){
    $denied=str_contains($error->getMessage(),'HTTP 403') &&
        !str_contains($error->getMessage(),$key);
}
ensure_flow_transport($denied&&$attempts===1,
    'HTTP 403 does not retry or disclose Google credential');
$attempts=0;$dns=false;
try{
    flow_ai_google_request($payload,$key,$model,90000,
        static function()use(&$attempts):array{
            ++$attempts;
            return ['status'=>0,'errno'=>6,'body'=>false];
        });
}catch(RuntimeException $error){
    $dns=str_contains($error->getMessage(),'DNS') &&
        str_contains($error->getMessage(),'HTTP 0') &&
        !str_contains($error->getMessage(),$key);
}
ensure_flow_transport($dns&&$attempts===2,'HTTP0 shows DNS error after two attempts');
$denied=false;
try{
    flow_ai_google_request($payload,$key,$model,1024,
        static fn():array=>['status'=>200,'errno'=>0,'body'=>str_repeat('a',1025)]);
}catch(RuntimeException $error){
    $denied=str_contains($error->getMessage(),'أكبر من الحد');
}
ensure_flow_transport($denied,'oversized response is rejected without publishing');
$attempts=0;
try{
    flow_ai_google_request($payload,$key,$model,90000,
        static function()use(&$attempts):array{
            ++$attempts;return ['status'=>400,'errno'=>0,'body'=>'{}'];
        });
}catch(RuntimeException){}
ensure_flow_transport($attempts===1,'schema error is never hidden by another retry');
echo "FLOW_AI_GOOGLE_HTTP_503_HTTP_0_RECOVERY_PASS\n";
