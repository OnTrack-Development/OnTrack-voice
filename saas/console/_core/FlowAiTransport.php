<?php
declare(strict_types=1);

/**
 * Bounded, read-only transport for Google text-editor proposals.
 * Never publishes a flow, never rotates credentials and never retries
 * authentication, schema, permission or model-not-found failures.
 *
 * Injectable exchange is used by offline tests ONLY; normal callers always
 * send directly to the fixed verified Google host via HTTPS.
 */
function flow_ai_google_request(
    array $payload,
    string $apiKey,
    string $model,
    int $maxBytes,
    ?callable $exchange=null
):string {
    if($maxBytes<1024||$maxBytes>200000)
        throw new InvalidArgumentException('invalid_google_response_limit');
    if(!preg_match('/\A[a-zA-Z0-9._-]{4,120}\z/D',$model))
        throw new InvalidArgumentException('invalid_google_model');
    $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if(strlen($body)>110000)
        throw new InvalidArgumentException('ai_editor_request_too_large');

    $started=microtime(true);
    $lastStatus=0;$lastErrno=0;$lastError='';
    for($attempt=0;$attempt<2;$attempt++){
        if($exchange!==null){
            $result=$exchange($attempt,$body,$model);
            if(!is_array($result))throw new RuntimeException('ai_editor_invalid_transport');
            $response=$result['body']??false;
            $status=(int)($result['status']??0);
            $errno=(int)($result['errno']??0);
        }else{
            $remaining=max(7,32-(int)(microtime(true)-$started));
            $timeout=min(25,$remaining);
            $curl=curl_init('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent');
            if(!$curl)throw new RuntimeException('تعذر تجهيز اتصال Google.');
            curl_setopt_array($curl,[
                CURLOPT_POST=>true,
                CURLOPT_POSTFIELDS=>$body,
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-goog-api-key: '.$apiKey],
                CURLOPT_CONNECTTIMEOUT=>min(6,$timeout),
                CURLOPT_TIMEOUT=>$timeout,
                CURLOPT_SSL_VERIFYPEER=>true,
                CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_MAXREDIRS=>0,
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS
            ]);
            $response=curl_exec($curl);
            $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
            $errno=(int)curl_errno($curl);
            curl_close($curl);
        }
        $lastStatus=$status;$lastErrno=$errno;
        if($status===200&&$errno===0&&is_string($response)){
            if(strlen($response)>$maxBytes)
                throw new RuntimeException('رد Google أكبر من الحد الآمن؛ المخطط لم يتغير.');
            return $response;
        }
        // A zero HTTP code is a transport error, not evidence that the key
        // or Gemini model was rejected. Do not expose raw cURL details/keys.
        if($status===0){
            $lastError=match($errno){
                28=>'انتهت مهلة الاتصال بـGoogle',
                6=>'تعذر حل عنوان Google عبر DNS',
                7=>'تعذر فتح اتصال Google',
                35,60=>'تعذر إنشاء اتصال HTTPS آمن مع Google',
                default=>'انقطع الاتصال قبل وصول رد من Google'
            };
        }else{
            $lastError=match($status){
                400=>'طلب Google غير مقبول؛ تحقق من توافق المخطط مع الموديل',
                401=>'مفتاح Google API مرفوض',
                403=>'حساب Google لا يملك صلاحية الموديل',
                404=>'الموديل غير متاح للمفتاح الحالي',
                429=>'Google رفض الطلب مؤقتًا بسبب حد الاستخدام',
                500,502,503,504=>'خدمة Google غير متاحة مؤقتًا',
                default=>'Google رفض الطلب'
            };
        }
        // Retry only transient failures. Reusing the exact immutable request
        // is safe: this is a non-executable preview, never a billing/action call.
        $retryable=in_array($status,[429,500,502,503,504],true)||
            ($status===0&&in_array($errno,[6,7,28,52,56],true));
        if(!$retryable||$attempt===1)break;
        if($exchange===null){
            if(microtime(true)-$started>29)break;
            usleep(250000);
        }
    }
    throw new RuntimeException('تعذر الاتصال بمساعد Google (HTTP '.$lastStatus.
        ($lastStatus===0?', cURL '.$lastErrno:'').'): '.$lastError.
        '. لم تتغير مسودة الإيجنت.');
}
