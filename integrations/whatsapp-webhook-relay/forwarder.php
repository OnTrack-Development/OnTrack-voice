<?php
declare(strict_types=1);

/**
 * Optional adapter for an existing WhatsApp webhook receiver (for example the
 * WHMCS WhatsApp Notifications module) to forward an ORIGINAL Meta event to
 * OnTrack Voice. Include/call this only AFTER the source has independently
 * validated X-Hub-Signature-256 and accepted the event. The existing webhook
 * must remain in place; OnTrack must not replace it without an explicit cutover.
 * The source must provide the full original raw body; do not re-encode JSON.
 */
function ontrack_forward_whatsapp_meta_event(
    string $rawMetaBody,
    string $ontrackCallback,
    string $relaySecret,
    ?callable $transport=null
):bool {
    if(strlen($rawMetaBody)<2||strlen($rawMetaBody)>262144||
        !preg_match('/^[a-f0-9]{64}$/D',$relaySecret)||
        !preg_match('~\Ahttps://app\.ontrackegy\.com/saas/console/whatsapp-webhook\.php\?id=[a-f0-9]{36}\z~D',$ontrackCallback))
        throw new InvalidArgumentException('Invalid OnTrack webhook relay configuration');
    $decoded=json_decode($rawMetaBody,true,64);
    if(!is_array($decoded)||($decoded['object']??null)!=='whatsapp_business_account')
        throw new InvalidArgumentException('Only official WhatsApp webhook events may be relayed');
    $signature='sha256='.hash_hmac('sha256',$rawMetaBody,$relaySecret);
    if($transport!==null){
        $response=$transport($ontrackCallback,$signature,$rawMetaBody);
    }else{
        if(!function_exists('curl_init'))throw new RuntimeException('cURL unavailable');
        $ch=curl_init($ontrackCallback);
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$rawMetaBody,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json',
                'X-OnTrack-Webhook-Signature-256: '.$signature],
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,
            CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_PROXY=>'',
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS
        ]);
        $raw=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        $response=['status'=>$status,'body'=>$raw];
    }
    $result=json_decode((string)($response['body']??''),true);
    return (int)($response['status']??0)===200 &&
        is_array($result)&&($result['ok']??false)===true;
}
