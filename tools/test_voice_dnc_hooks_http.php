<?php
declare(strict_types=1);

/**
 * Real signed HTTP webhook regression using an isolated seeded SQLite DB.
 * No external network calls, payments, telephony or customer data.
 */
function dnc_hook_http_request(string $url,string $token,array $event):array {
    $raw=json_encode($event,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if(!is_string($raw))throw new RuntimeException('dnc_hook_json');
    $timestamp=(string)time();
    $signature=hash_hmac('sha256',$timestamp."\n".$raw,$token);
    $headers="Authorization: Bearer ".$token."\r\n".
        "X-OnTrack-Timestamp: ".$timestamp."\r\n".
        "X-OnTrack-Signature: ".$signature."\r\n".
        "Content-Type: application/json\r\n";
    $context=stream_context_create(['http'=>[
        'method'=>'POST','header'=>$headers,'content'=>$raw,
        'ignore_errors'=>true,'timeout'=>5,
    ]]);
    $reply=@file_get_contents($url,false,$context);
    if($reply===false)throw new RuntimeException('dnc_hook_transport');
    $status=0;
    if(isset($http_response_header[0])&&preg_match('/^HTTP\/\S+ (\d{3})/',$http_response_header[0],$m))
        $status=(int)$m[1];
    $body=json_decode($reply,true);
    if(!is_array($body)||$status===0)throw new RuntimeException('dnc_hook_response');
    return [$status,$body];
}
function dnc_hook_event(string $eventId,int $siteId,string $phone,bool $consent):array {
    return ['event_id'=>$eventId,'type'=>'booking.created','data'=>[
        'site_id'=>$siteId,'name'=>'Synthetic signed booking','phone'=>$phone,'consent'=>$consent,
    ]];
}
function test_voice_dnc_hooks_http():void {
    require_once __DIR__.'/../saas/console/_core/DncGuard.php';
    $sourcePhone='+20 (11) 4444-8888';
    insert("INSERT INTO contacts(account_id,site_id,name,phone,dnc,created_at)
        VALUES (4,2,'Opted-out test person',?,1,?)",[$sourcePhone,now()]);
    api_http_assert(dnc_phone_key($sourcePhone)===dnc_phone_key('01144448888'),
        'DNC canonicalizes Egyptian international and national numbers');
    api_http_assert(dnc_phone_key('00201144448888')===dnc_phone_key($sourcePhone),
        'DNC canonicalizes 00 international prefix');
    api_http_assert(dnc_is_suppressed(4,'01144448888') && !dnc_is_suppressed(5,'01144448888'),
        'DNC lookup is strictly account-scoped');

    $token='otint_'.bin2hex(random_bytes(32));
    $integrationId=insert("INSERT INTO integrations(account_id,name,provider,token_hash,token_prefix,status,created_at)
        VALUES (4,'dnc-hook-fixture','laravel',?,?,'active',?)",
        [hash('sha256',$token),substr($token,0,12),now()]);
    $foreignToken='otint_'.bin2hex(random_bytes(32));
    insert("INSERT INTO integrations(account_id,name,provider,token_hash,token_prefix,status,created_at)
        VALUES (5,'dnc-other-tenant','whmcs',?,?,'active',?)",
        [hash('sha256',$foreignToken),substr($foreignToken,0,12),now()]);

    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
    if($socket===false)throw new RuntimeException('dnc_hook_socket: '.$errstr);
    $addr=stream_socket_get_name($socket,false);
    fclose($socket);
    $port=(int)substr(strrchr($addr,':'),1);
    $root=realpath(__DIR__.'/../saas/console');
    $router=$root.'/hooks/index.php';
    $proc=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$root,$router],
        [0=>['pipe','r'],1=>['file','/dev/null','a'],2=>['file','/dev/null','a']],$pipes,dirname(__DIR__));
    if(!is_resource($proc))throw new RuntimeException('dnc_hook_server');
    fclose($pipes[0]);
    $url='http://127.0.0.1:'.$port.'/hooks/';
    try {
        $ready=false;
        for($i=0;$i<40;$i++){
            $conn=@fsockopen('127.0.0.1',$port,$err,$msg,0.15);
            if($conn!==false){fclose($conn);$ready=true;break;}
            usleep(100000);
        }
        if(!$ready)throw new RuntimeException('dnc_hook_startup_timeout');

        $event=dnc_hook_event('dnc-booking-test-001',1,'01144448888',true);
        [$status,$body]=dnc_hook_http_request($url,$token,$event);
        $leadId=(int)($body['contact_id']??0);
        api_http_assert($status===200 && ($body['dnc']??null)===true &&
            ($body['dial_requested']??null)===false &&
            (int)(one('SELECT dnc FROM contacts WHERE id=?',[$leadId])['dnc']??0)===1,
            'signed consented booking preserves prior DNC across sites');

        [$status,$body]=dnc_hook_http_request($url,$token,$event);
        api_http_assert($status===200 && ($body['duplicate']??false)===true &&
            (int)one("SELECT COUNT(*) n FROM events WHERE integration_id=? AND external_id=?",
                [$integrationId,'dnc-booking-test-001'])['n']===1,
            'signed event replay is idempotent');

        [$status,$body]=dnc_hook_http_request($url,$token,
            dnc_hook_event('dnc-booking-test-002',1,'+201177700001',false));
        $noConsentId=(int)($body['contact_id']??0);
        api_http_assert($status===200 && ($body['dnc']??null)===true &&
            (int)(one('SELECT dnc FROM contacts WHERE id=?',[$noConsentId])['dnc']??0)===1,
            'booking without consent is always DNC');
        $noConsentKey=dnc_phone_key('+201177700001');
        $entry=one('SELECT source FROM dnc_suppressions WHERE account_id=? AND phone_key=?',
            [4,$noConsentKey]);
        api_http_assert(($entry['source']??'')==='booking_no_consent',
            'signed booking opt-out is persisted independently of contact');
        execute('DELETE FROM contacts WHERE id=?',[$noConsentId]);
        api_http_assert(dnc_is_suppressed(4,'01177700001') &&
            !dnc_is_suppressed(5,'01177700001'),
            'signed booking suppression survives contact deletion and stays tenant-scoped');

        [$status,$body]=dnc_hook_http_request($url,$foreignToken,
            dnc_hook_event('dnc-booking-test-003',3,'01144448888',true));
        $foreignId=(int)($body['contact_id']??0);
        api_http_assert($status===200 && ($body['dnc']??null)===false &&
            (int)(one('SELECT dnc FROM contacts WHERE id=?',[$foreignId])['dnc']??1)===0,
            'signed booking DNC does not leak to another tenant');

        [$status,$body]=dnc_hook_http_request($url,$token,
            dnc_hook_event('dnc-booking-test-004',3,'01144448888',true));
        api_http_assert($status===422 && ($body['error']??'')==='site_not_found',
            'signed booking cannot create contact on another tenant site');
        echo "VOICE_DNC_HOOKS_HTTP_PASS\n";
    }finally{
        proc_terminate($proc);
        proc_close($proc);
    }
}
