<?php
declare(strict_types=1);

/**
 * Local HTTP regression for the public bearer-key API.
 * Loaded after test_voice_saas_console.php seeded an isolated SQLite demo DB.
 * No outbound requests, calls or payment activity. Secrets are random and
 * never logged. PHP's built-in server executes the REAL API front controller.
 */
function api_http_request(string $base,string $token,string $op,?array $payload=null,array $query=[]):array {
    $headers="Authorization: Bearer ".$token."\r\n";
    if($payload!==null)$headers.="Content-Type: application/json\r\n";
    $ctx=stream_context_create(['http'=>[
        'method'=>$payload===null?'GET':'POST',
        'header'=>$headers,
        'content'=>$payload===null?'':json_encode($payload,JSON_UNESCAPED_SLASHES),
        'ignore_errors'=>true,'timeout'=>5,
    ]]);
    $raw=@file_get_contents($base.'?'.http_build_query(array_merge(['op'=>$op],$query)),false,$ctx);
    if($raw===false)throw new RuntimeException('api_http_transport_failed');
    $status=0;
    if(isset($http_response_header[0]) && preg_match('/^HTTP\/\S+ (\d{3})/',$http_response_header[0],$m))
        $status=(int)$m[1];
    $body=json_decode($raw,true);
    if(!is_array($body)||$status===0)throw new RuntimeException('api_http_invalid_response');
    return [$status,$body];
}
function api_http_assert(bool $condition,string $label):void {
    if(!$condition)throw new RuntimeException('FAIL api_http: '.$label);
    echo "PASS api_http: ".$label."\n";
}
function api_http_key(int $accountId,?int $siteId,string $scopes):string {
    $token='otv_'.bin2hex(random_bytes(30));
    insert("INSERT INTO api_keys(account_id,site_id,name,prefix,token_hash,scopes,status,created_at)
        VALUES (?,?,?,?,?,?,'active',?)",[$accountId,$siteId,'api-archive-regression',substr($token,0,12),hash('sha256',$token),$scopes,now()]);
    return $token;
}
function test_voice_api_http():void {
    $site=insert("INSERT INTO sites(account_id,name,domain,status,created_at)
        VALUES (4,'Archived API fixture','archived-api.invalid','archived',?)",[now()]);
    $retiredAgent=insert("INSERT INTO agents(account_id,site_id,name,voice,knowledge,status,created_at)
        VALUES (4,1,'Retired agent','google.aoede','RETIRED_PRIVATE_KNOWLEDGE','archived',?)",[now()]);
    $retiredSiteAgent=insert("INSERT INTO agents(account_id,site_id,name,voice,knowledge,status,created_at)
        VALUES (4,?,'Retired site agent','google.aoede','SITE_PRIVATE_KNOWLEDGE','draft',?)",[$site,now()]);
    $key=api_http_key(4,null,'knowledge:read,leads:write');
    $retiredKey=api_http_key(4,$site,'knowledge:read,leads:write');
    $foreignKey=api_http_key(5,null,'knowledge:read,leads:write');

    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
    if($socket===false)throw new RuntimeException('api_http_socket: '.$errstr);
    $address=stream_socket_get_name($socket,false);
    fclose($socket);
    $port=(int)substr(strrchr($address,':'),1);
    $root=realpath(__DIR__.'/../saas/console');
    $router=$root.'/api/v1/index.php';
    $command=[PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$root,$router];
    $process=proc_open($command,[0=>['pipe','r'],1=>['file','/dev/null','a'],2=>['file','/dev/null','a']],$pipes,dirname(__DIR__));
    if(!is_resource($process))throw new RuntimeException('api_http_server_failed');
    fclose($pipes[0]);
    $base='http://127.0.0.1:'.$port.'/api/v1/';
    try {
        $ready=false;
        for($i=0;$i<40;$i++){
            $conn=@fsockopen('127.0.0.1',$port,$e,$msg,0.15);
            if($conn!==false){fclose($conn);$ready=true;break;}
            usleep(100000);
        }
        if(!$ready)throw new RuntimeException('api_http_server_startup_timeout');
        [$status,$body]=api_http_request($base,$key,'sites');
        $ids=array_map('intval',array_column($body['sites']??[],'id'));
        api_http_assert($status===200 && in_array(1,$ids,true) && !in_array($site,$ids,true),
            'sites listing excludes archived sites while preserving active sites');

        [$status,$body]=api_http_request($base,$key,'agents');
        $ids=array_map('intval',array_column($body['agents']??[],'id'));
        api_http_assert($status===200 && in_array(1,$ids,true) &&
            !in_array($retiredAgent,$ids,true) && !in_array($retiredSiteAgent,$ids,true),
            'agents listing excludes archived agents and agents on archived sites');

        [$status,$body]=api_http_request($base,$key,'knowledge',null,['agent_id'=>1]);
        api_http_assert($status===200 && (int)($body['agent']['id']??0)===1,
            'active agent knowledge remains readable');
        foreach([$retiredAgent,$retiredSiteAgent] as $id){
            [$status,$body]=api_http_request($base,$key,'knowledge',null,['agent_id'=>$id]);
            api_http_assert($status===404 && ($body['error']??'')==='agent_unavailable',
                'retired knowledge returns 404 without leaking data');
        }
        [$status,$body]=api_http_request($base,$retiredKey,'sites');
        api_http_assert($status===403 && ($body['error']??'')==='site_unavailable',
            'site-bound key cannot access archived site');

        $lead=['site_id'=>$site,'name'=>'Synthetic archived test','phone'=>'+99900000001','consent'=>true];
        $before=(int)one('SELECT COUNT(*) n FROM contacts WHERE account_id=4')['n'];
        [$status,$body]=api_http_request($base,$key,'lead.create',$lead);
        api_http_assert($status===403 && ($body['error']??'')==='site_unavailable' &&
            (int)one('SELECT COUNT(*) n FROM contacts WHERE account_id=4')['n']===$before,
            'archived site rejects lead writes without side effects');

        [$status,$body]=api_http_request($base,$key,'lead.create',array_merge($lead,['site_id'=>3]));
        api_http_assert($status===403 && ($body['error']??'')==='cross_account',
            'cross-account lead write denied');

        [$status,$body]=api_http_request($base,$key,'lead.create',array_merge($lead,['site_id'=>1]));
        api_http_assert($status===201 && ($body['status']??'')==='created' &&
            (int)one('SELECT COUNT(*) n FROM contacts WHERE account_id=4')['n']===$before+1,
            'active site still accepts consented synthetic lead');

        [$status,$body]=api_http_request($base,$foreignKey,'sites');
        api_http_assert($status===200 && !in_array(1,array_map('intval',array_column($body['sites']??[],'id')),true),
            'another tenant cannot enumerate active site');

        execute("UPDATE api_keys SET status='revoked' WHERE token_hash=?",[hash('sha256',$key)]);
        [$status,$body]=api_http_request($base,$key,'sites');
        api_http_assert($status===401 && ($body['error']??'')==='invalid_api_key',
            'revoked key rejected');
        echo "VOICE_API_HTTP_PASS\n";
    }finally{
        proc_terminate($process);
        proc_close($process);
    }
}
