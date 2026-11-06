<?php
declare(strict_types=1);
require_once __DIR__.'/VoiceToolGateway.php';

/**
 * Import an explicitly selected, verified REST connector's OpenAPI 3 document.
 * Discovery is informational only: it NEVER grants write permissions or
 * follows schema-supplied servers, external $refs, webhooks or arbitrary URLs.
 */
function voice_openapi_init():void {
    $mark=private_path().'/.schema.voice-openapi.v1';
    if(is_file($mark))return;
    $mysql=driver()==='mysql';
    $int=$mysql?'BIGINT':'INTEGER';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $large=$mysql?'MEDIUMTEXT':'TEXT';
    db()->exec("CREATE TABLE IF NOT EXISTS voice_openapi_discoveries (
      account_id $int NOT NULL,site_id $int NOT NULL,connector_id $int NOT NULL,
      source_path VARCHAR(40) NOT NULL,document_sha256 CHAR(64) NOT NULL,
      endpoints_json $large NOT NULL,checked_at VARCHAR(19) NOT NULL,
      PRIMARY KEY(account_id,site_id,connector_id))".$engine);
    file_put_contents($mark,now(),LOCK_EX);@chmod($mark,0600);
}
function voice_openapi_connector(int $accountId,int $siteId,int $connectorId):array {
    voice_openapi_init();
    $connector=one("SELECT * FROM voice_data_connectors WHERE id=? AND account_id=? AND site_id=?
      AND status='verified' AND type IN ('laravel','custom')",
      [$connectorId,$accountId,$siteId]);
    if(!$connector||!one("SELECT id FROM sites WHERE id=? AND account_id=? AND status='active'",
        [$siteId,$accountId]))throw new InvalidArgumentException('openapi_connector_unverified_or_foreign');
    return $connector;
}
function voice_openapi_fetch(array $connector,string $path):string {
    if(!in_array($path,['/openapi.json','/api/openapi.json','/docs/openapi.json'],true))
        throw new InvalidArgumentException('openapi_path_not_allowlisted');
    $target=voice_connector_url((string)$connector['base_url']);
    $ip=voice_connector_public_ip($target['host']);
    if(!function_exists('curl_init'))throw new RuntimeException('openapi_curl_unavailable');
    // Reuse the connector base URL only; never trust the servers field in a document.
    $url=$target['url'].$path;
    $curl=curl_init($url);
    $body='';
    curl_setopt_array($curl,[
       CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
       CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_SSL_VERIFYPEER=>true,
       CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,
       CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
       CURLOPT_RESOLVE=>[$target['host'].':443:'.$ip],
       CURLOPT_HTTPHEADER=>['Accept: application/json'],
       CURLOPT_USERAGENT=>'OnTrack-OpenAPI-Discovery/1.0',
       CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$body):int {
           if(strlen($body)+strlen($chunk)>196608)return 0;
           $body.=$chunk;return strlen($chunk);
       }
    ]);
    try{$ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
        $contentType=(string)curl_getinfo($curl,CURLINFO_CONTENT_TYPE);}
    finally{curl_close($curl);}
    if($ok!==true||$status!==200||!str_starts_with(strtolower(trim($contentType)),'application/json')||
       $body===''||strlen($body)>196608)
        throw new RuntimeException('openapi_document_unavailable');
    return $body;
}
function voice_openapi_parse(string $raw):array {
    if(strlen($raw)>196608||strlen($raw)<25)throw new InvalidArgumentException('openapi_document_size_invalid');
    $doc=json_decode($raw,true,24,JSON_THROW_ON_ERROR);
    if(!is_array($doc)||!preg_match('/^3\.(?:0|1)\.[0-9]+$/D',(string)($doc['openapi']??''))||
       !is_array($doc['paths']??null)||count($doc['paths'])>100)
       throw new InvalidArgumentException('openapi_version_or_paths_invalid');
    $endpoints=[];
    foreach($doc['paths'] as $path=>$methods){
        if(!is_string($path)||!is_array($methods)||count($methods)>18||
            str_contains($path,'{')||str_contains($path,'}')||
            str_contains($path,'?'))continue;
        try{$safe=voice_connector_path($path);}
        catch(InvalidArgumentException){continue;}
        if($safe==='/')continue;
        foreach(['get','post'] as $method){
            $details=$methods[$method]??null;
            if(!is_array($details))continue;
            // Map only fixed API contracts. A discovered schema is NOT a tool permission.
            $operation=null;
            if($method==='get'&&preg_match('~(?:^|/)(?:slots|availability)$~iD',$safe))
                $operation='rest.slots';
            if($method==='post'&&preg_match('~(?:^|/)(?:bookings|reservations)$~iD',$safe))
                $operation='rest.booking.create';
            if(!$operation)continue;
            $summary=$details['summary']??$details['operationId']??'';
            if(!is_string($summary))$summary='';
            $endpoints[]=['path'=>$safe,'method'=>strtoupper($method),
                'operation'=>$operation,'summary'=>mb_substr(strip_tags($summary),0,160)];
            if(count($endpoints)>=30)break 2;
        }
    }
    return $endpoints;
}
function voice_openapi_discover(int $accountId,int $siteId,int $connectorId,
   string $path='/openapi.json',?callable $transport=null):array {
    $connector=voice_openapi_connector($accountId,$siteId,$connectorId);
    if(!in_array($path,['/openapi.json','/api/openapi.json','/docs/openapi.json'],true))
        throw new InvalidArgumentException('openapi_path_not_allowlisted');
    $raw=$transport!==null?$transport($connector,$path):voice_openapi_fetch($connector,$path);
    if(!is_string($raw))throw new InvalidArgumentException('openapi_response_invalid');
    $endpoints=voice_openapi_parse($raw);
    $hash=hash('sha256',$raw);
    $json=json_encode($endpoints,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $prior=one("SELECT connector_id FROM voice_openapi_discoveries
       WHERE account_id=? AND site_id=? AND connector_id=?",[$accountId,$siteId,$connectorId]);
    if($prior)execute("UPDATE voice_openapi_discoveries SET source_path=?,
        document_sha256=?,endpoints_json=?,checked_at=?
        WHERE account_id=? AND site_id=? AND connector_id=?",
        [$path,$hash,$json,now(),$accountId,$siteId,$connectorId]);
    else insert("INSERT INTO voice_openapi_discoveries
      (account_id,site_id,connector_id,source_path,document_sha256,endpoints_json,checked_at)
      VALUES (?,?,?,?,?,?,?)",[$accountId,$siteId,$connectorId,$path,$hash,$json,now()]);
    return ['connector_id'=>$connectorId,'sha256'=>$hash,'checked_at'=>now(),
      'count'=>count($endpoints),'candidates'=>$endpoints,'writes_enabled'=>false];
}
function voice_openapi_candidates(int $accountId,int $siteId):array {
    voice_openapi_init();
    $rows=all("SELECT d.connector_id,d.document_sha256,d.checked_at,d.endpoints_json,c.name
       FROM voice_openapi_discoveries d JOIN voice_data_connectors c
          ON c.id=d.connector_id AND c.account_id=d.account_id AND c.site_id=d.site_id
       WHERE d.account_id=? AND d.site_id=? AND c.status='verified'
       ORDER BY d.checked_at DESC LIMIT 25",[$accountId,$siteId]);
    $out=[];
    foreach($rows as $row){
        $entries=json_decode((string)$row['endpoints_json'],true);
        if(!is_array($entries))continue;
        foreach($entries as $item)if(is_array($item))
            $out[]=['connector_id'=>(int)$row['connector_id'],'connector_name'=>(string)$row['name'],
              'sha256'=>(string)$row['document_sha256'],'checked_at'=>(string)$row['checked_at']]+$item;
    }
    return array_slice($out,0,120);
}
/**
 * Explicit owner action creates a DISABLED policy only. A second action in
 * Integration Manager is needed to enable it. Never execute a write on import.
 */
function voice_openapi_draft_policy(int $accountId,int $siteId,int $connectorId,
    string $operation,string $path,string $documentHash):int {
    voice_openapi_connector($accountId,$siteId,$connectorId);
    voice_tools_init();
    if(!preg_match('/^[a-f0-9]{64}$/D',$documentHash))throw new InvalidArgumentException('openapi_hash_invalid');
    $row=one("SELECT endpoints_json FROM voice_openapi_discoveries
       WHERE account_id=? AND site_id=? AND connector_id=? AND document_sha256=?",
       [$accountId,$siteId,$connectorId,$documentHash]);
    if(!$row)throw new InvalidArgumentException('openapi_document_changed');
    $entries=json_decode((string)$row['endpoints_json'],true);
    if(!is_array($entries))throw new InvalidArgumentException('openapi_candidates_invalid');
    $matched=false;
    foreach($entries as $entry)if(($entry['path']??null)===$path&&($entry['operation']??null)===$operation)$matched=true;
    if(!$matched)throw new InvalidArgumentException('openapi_endpoint_not_discovered');
    // Avoid silently disabling an already-approved operational policy.
    $existing=one("SELECT id,enabled FROM voice_tool_policies
      WHERE account_id=? AND site_id=? AND connector_id=? AND operation=?",
      [$accountId,$siteId,$connectorId,$operation]);
    if($existing&&((int)$existing['enabled'])===1)
        throw new RuntimeException('openapi_existing_enabled_policy_requires_manual_change');
    return voice_tool_policy_save($accountId,$siteId,$connectorId,$operation,$path,false);
}
