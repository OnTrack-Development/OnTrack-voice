<?php
declare(strict_types=1);
/**
 * Password-proof mail queue. Real token-bearing payload remains encrypted in
 * the server's private vault and is erased on acceptance/expiration. SMTP
 * acceptance is NOT proof that a human opened the email.
 */
function email_delivery_init():void{
    $engine=driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $pk=driver()==='mysql'?'BIGINT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $blob=driver()==='mysql'?'MEDIUMTEXT':'TEXT';
    db()->exec("CREATE TABLE IF NOT EXISTS operational_email_jobs(
        id $pk,
        purpose VARCHAR(24) NOT NULL,
        recipient_hash CHAR(64) NOT NULL,
        payload_enc $blob NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'pending',
        attempts INTEGER NOT NULL DEFAULT 0,
        retry_at VARCHAR(19) NOT NULL,
        expires_at VARCHAR(19) NOT NULL,
        last_error VARCHAR(60) NULL,
        created_at VARCHAR(19) NOT NULL,
        updated_at VARCHAR(19) NOT NULL
    )".$engine);
}
function email_delivery_config():array{
    $path=private_path().'/email.json';
    $config=is_file($path)?json_decode((string)file_get_contents($path),true):[];
    $config=is_array($config)?$config:[];
    $from=(string)($config['from']??'no-reply@ontrackegy.com');
    if(!filter_var($from,FILTER_VALIDATE_EMAIL)||strlen($from)>190)
        throw new RuntimeException('mail_from_invalid');
    $transport=(string)($config['transport']??'php_mail');
    if(!in_array($transport,['php_mail','smtp'],true))
        throw new RuntimeException('mail_transport_invalid');
    if($transport==='smtp'){
        $host=(string)($config['smtp_host']??'');
        $port=(int)($config['smtp_port']??0);
        if(!preg_match('/^(?=.{4,190}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])$/iD',$host)||
           !str_contains($host,'.')||!in_array($port,[465,587],true)||
           strlen((string)($config['smtp_user']??''))<1||
           strlen((string)($config['smtp_password']??''))<1)
            throw new RuntimeException('smtp_not_configured');
    }
    return $config+['from'=>$from,'transport'=>$transport];
}
function email_smtp_code($socket,array $wanted):int{
    $code=0;
    for($i=0;$i<20;$i++){
        $line=fgets($socket,2048);
        if(!is_string($line)||!preg_match('/^([0-9]{3})([ -])/', $line,$m))
            throw new RuntimeException('smtp_response_unavailable');
        $code=(int)$m[1];
        if($m[2]===' ')break;
        if($i===19)throw new RuntimeException('smtp_response_too_long');
    }
    if(!in_array($code,$wanted,true))throw new RuntimeException(
        $code>=500?'smtp_permanent':'smtp_temporary');
    return $code;
}
function email_smtp_command($socket,string $cmd,array $expect):void{
    if(fwrite($socket,$cmd."\r\n")!==strlen($cmd)+2)
        throw new RuntimeException('smtp_write_failed');
    email_smtp_code($socket,$expect);
}
function email_smtp_send(array $config,string $address,string $subject,string $body):void{
    $host=(string)$config['smtp_host'];$port=(int)$config['smtp_port'];
    $ctx=stream_context_create(['ssl'=>[
        'verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,
        'peer_name'=>$host,'SNI_enabled'=>true
    ]]);
    $url=($port===465?'tls://':'tcp://').$host.':'.$port;
    $socket=@stream_socket_client($url,$errno,$error,8,STREAM_CLIENT_CONNECT,$ctx);
    if(!is_resource($socket))throw new RuntimeException('smtp_connect_failed');
    stream_set_timeout($socket,12);
    $dataStarted=false;
    try{
        email_smtp_code($socket,[220]);
        email_smtp_command($socket,'EHLO app.ontrackegy.com',[250]);
        if($port===587){
            email_smtp_command($socket,'STARTTLS',[220]);
            if(stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)!==true)
                throw new RuntimeException('smtp_tls_verification_failed');
            email_smtp_command($socket,'EHLO app.ontrackegy.com',[250]);
        }
        email_smtp_command($socket,'AUTH LOGIN',[334]);
        email_smtp_command($socket,base64_encode((string)$config['smtp_user']),[334]);
        email_smtp_command($socket,base64_encode((string)$config['smtp_password']),[235]);
        email_smtp_command($socket,'MAIL FROM:<'.$config['from'].'>',[250]);
        email_smtp_command($socket,'RCPT TO:<'.$address.'>',[250,251]);
        email_smtp_command($socket,'DATA',[354]);
        $dataStarted=true;
        $headers="From: ".$config['from']."\r\nTo: ".$address.
            "\r\nSubject: =?UTF-8?B?".base64_encode($subject)."?=".
            "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
        $payload=$headers."\r\n\r\n".$body;
        $payload=preg_replace('/\r?\n/','\r\n',$payload);
        $payload=preg_replace('/^\./m','..',$payload);
        if(fwrite($socket,$payload."\r\n.\r\n")!==strlen($payload)+5)
            throw new RuntimeException('smtp_delivery_uncertain');
        email_smtp_code($socket,[250]);
        $dataStarted=false;
    }catch(Throwable $e){
        // An error after DATA began may have actually delivered the message.
        // Don't silently retry a potentially accepted password reset token.
        if($dataStarted)throw new RuntimeException('smtp_delivery_uncertain',0,$e);
        throw $e;
    }finally{fclose($socket);}
}
/** Transport adapter injectable only in regression fixtures. */
function email_delivery_transport(string $to,string $subject,string $body,
    ?callable $transport=null):void{
    $config=email_delivery_config();
    if($transport!==null){$transport($to,$subject,$body,$config);return;}
    if($config['transport']==='smtp'){
        email_smtp_send($config,$to,$subject,$body);return;
    }
    $sent=mail($to,$subject,$body,[
        'From'=>$config['from'],'Content-Type'=>'text/plain; charset=UTF-8']);
    if(!$sent)throw new RuntimeException('php_mail_rejected');
}
function email_queue_submit(string $purpose,string $address,string $subject,string $body):bool{
    if(!in_array($purpose,['signup','password_reset'],true)||
       !filter_var($address,FILTER_VALIDATE_EMAIL)||
       strlen($body)>8000||strlen($subject)>250||
       preg_match('/[\\r\\n]/',$subject.$address))
        throw new InvalidArgumentException('mail_job_invalid');
    email_delivery_init();
    $payload=json_encode(['to'=>$address,'subject'=>$subject,'body'=>$body],
        JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $id=insert("INSERT INTO operational_email_jobs
        (purpose,recipient_hash,payload_enc,status,attempts,retry_at,expires_at,created_at,updated_at)
        VALUES (?,?,?,'pending',0,?,?,?,?)",[
        $purpose,hash('sha256',strtolower($address)),seal_secret($payload),now(),
        gmdate('Y-m-d H:i:s',time()+1800),now(),now()]);
    // Immediate attempt avoids waiting for the next cron and records its real
    // acceptance/failure; remaining transient failures have a durable retry.
    email_job_process($id);
    $job=one('SELECT status FROM operational_email_jobs WHERE id=?',[$id]);
    return in_array((string)($job['status']??''),['accepted','pending'],true);
}
function email_job_process(int $id,?callable $transport=null):array{
    email_delivery_init();
    $row=one('SELECT * FROM operational_email_jobs WHERE id=? AND status=? AND retry_at<=?',
        [$id,'pending',now()]);
    if(!$row)return ['status'=>'not_due'];
    if($row['expires_at']<=now()){
        execute("UPDATE operational_email_jobs SET status='expired',payload_enc=NULL,updated_at=?
          WHERE id=? AND status='pending'",[now(),$id]);
        return ['status'=>'expired'];
    }
    if(execute("UPDATE operational_email_jobs SET status='processing',
        attempts=attempts+1,updated_at=? WHERE id=? AND status='pending' AND attempts<3",
        [now(),$id])!==1)return ['status'=>'claimed_or_exhausted'];
    try{
        $unsealed=unseal_secret($row['payload_enc']??null);
        $payload=is_string($unsealed)?json_decode($unsealed,true):null;
        if(!is_array($payload) || !is_string($payload['to']??null)||
           !is_string($payload['subject']??null)||!is_string($payload['body']??null))
            throw new RuntimeException('mail_payload_unavailable');
        email_delivery_transport($payload['to'],$payload['subject'],$payload['body'],$transport);
        execute("UPDATE operational_email_jobs SET status='accepted',
          payload_enc=NULL,last_error=NULL,updated_at=? WHERE id=? AND status='processing'",
          [now(),$id]);
        return ['status'=>'accepted'];
    }catch(Throwable $error){
        $code=(string)$error->getMessage();
        $uncertain=$code==='smtp_delivery_uncertain';
        $permanent=$code==='smtp_permanent'||$code==='mail_payload_unavailable';
        $limit=(int)$row['attempts']>=2;
        $status=$uncertain?'needs_review':($permanent||$limit?'failed':'pending');
        $retry=gmdate('Y-m-d H:i:s',time()+60);
        execute("UPDATE operational_email_jobs SET status=?,payload_enc=?,retry_at=?,
            last_error=?,updated_at=? WHERE id=? AND status='processing'",[
            $status,$status==='pending'?$row['payload_enc']:null,
            $retry,mb_substr(get_class($error).':'.$code,0,60),now(),$id
        ]);
        return ['status'=>$status];
    }
}
function email_queue_batch(int $limit=20,?callable $transport=null):array{
    email_delivery_init();
    $before=gmdate('Y-m-d H:i:s',time()-180);
    // If the SMTP attempt died mid-send, its result is unknown: do NOT retry.
    execute("UPDATE operational_email_jobs SET status='needs_review',
      payload_enc=NULL,updated_at=? WHERE status='processing' AND updated_at<?",
      [now(),$before]);
    execute("UPDATE operational_email_jobs SET status='expired',
      payload_enc=NULL,updated_at=? WHERE status='pending' AND expires_at<=?",
      [now(),now()]);
    $list=all("SELECT id FROM operational_email_jobs
      WHERE status='pending' AND attempts<3 AND retry_at<=? AND expires_at>?
      ORDER BY id ASC LIMIT ".max(1,min(100,$limit)),[now(),now()]);
    $states=['scanned'=>count($list),'accepted'=>0,'pending'=>0,'failed'=>0,'needs_review'=>0];
    foreach($list as $item){
        $out=email_job_process((int)$item['id'],$transport);
        $state=(string)$out['status'];
        if(isset($states[$state]))$states[$state]++;
    }
    return $states;
}
function email_queue_status():array{
    email_delivery_init();
    return all("SELECT purpose,status,COUNT(*) amount
        FROM operational_email_jobs GROUP BY purpose,status
        ORDER BY purpose,status");
}
