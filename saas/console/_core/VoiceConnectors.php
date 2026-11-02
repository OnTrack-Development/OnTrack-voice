<?php
declare(strict_types=1);
/** Read-only connector contracts. Arbitrary URLs and commands are never accepted from voice/LLM. */
function voice_connector_type(string $type):string {
    if(!in_array($type,['wordpress','whmcs','laravel','custom'],true))
        throw new InvalidArgumentException('connector_type_invalid');
    return $type;
}
function voice_connector_path(string $path):string {
    if(!preg_match('~^/[A-Za-z0-9/_.,?=&%+-]{1,350}$~D',$path)||
        str_contains($path,'..')||str_contains($path,'//')||
        preg_match('/[?&](?:url|redirect|path|host)=/i',$path))
        throw new InvalidArgumentException('connector_path_rejected');
    return $path;
}
function voice_connector_url(string $url):array {
    $parts=parse_url($url);
    if(!$parts||strtolower((string)($parts['scheme']??''))!=='https'||
       empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||
       isset($parts['query'])||isset($parts['fragment'])||
       (isset($parts['port'])&&(int)$parts['port']!==443)||
       !preg_match('/^[a-z0-9.-]{4,253}$/Di',(string)$parts['host'])||
       filter_var($parts['host'],FILTER_VALIDATE_IP))
        throw new InvalidArgumentException('connector_https_public_host_required');
    $hostname=strtolower((string)$parts['host']);
    if($hostname==='localhost'||str_ends_with($hostname,'.local')||
       str_ends_with($hostname,'.internal')||str_ends_with($hostname,'.localhost'))
        throw new InvalidArgumentException('connector_host_denied');
    return ['host'=>$hostname,'url'=>rtrim($url,'/')];
}
function voice_connector_public_ip(string $host):string {
    $addresses=[];
    foreach((array)dns_get_record($host,DNS_A|DNS_AAAA) as $r){
        if(isset($r['ip']))$addresses[]=$r['ip'];
        if(isset($r['ipv6']))$addresses[]=$r['ipv6'];
    }
    if(!$addresses)throw new RuntimeException('connector_dns_unavailable');
    foreach($addresses as $ip){
        if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))
            throw new InvalidArgumentException('connector_private_dns_denied');
    }
    $ipv4=array_values(array_filter($addresses,static fn($v)=>filter_var($v,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)));
    if(!$ipv4)throw new RuntimeException('connector_requires_public_ipv4');
    return $ipv4[0];
}
function voice_connector_add(int $accountId,int $siteId,string $type,string $name,
  string $baseUrl,string $readPath,string $authMode,string $credential=''):int {
    $type=voice_connector_type($type);$url=voice_connector_url(trim($baseUrl));
    $readPath=voice_connector_path($readPath);
    if(!in_array($authMode,['none','bearer','wp_application','whmcs'],true)||
       ($type==='whmcs'&&$authMode!=='whmcs')||
       ($type!=='whmcs'&&$authMode==='whmcs')||
       trim($name)===''||mb_strlen($name)>120||
       strlen($credential)>2000)
        throw new InvalidArgumentException('connector_configuration_invalid');
    if(!one("SELECT id FROM sites WHERE id=? AND account_id=? AND status='active'",[$siteId,$accountId]))
        throw new InvalidArgumentException('site_unavailable');
    $secret=$credential===''?null:seal_secret($credential);
    return insert("INSERT INTO voice_data_connectors
       (account_id,site_id,type,name,base_url,read_path,auth_mode,secret_enc,status,created_at)
       VALUES (?,?,?,?,?,?,?,?,'draft',?)",[
        $accountId,$siteId,$type,trim($name),$url['url'],$readPath,$authMode,$secret,now()]);
}
function voice_connector_request(array $connector):array {
    $url=voice_connector_url((string)$connector['base_url']);
    $path=voice_connector_path((string)$connector['read_path']);
    $ip=voice_connector_public_ip($url['host']);
    if(!function_exists('curl_init'))throw new RuntimeException('connector_curl_unavailable');
    $type=voice_connector_type((string)$connector['type']);
    $headers=['Accept: application/json'];
    $auth=(string)$connector['auth_mode'];
    $credential=unseal_secret($connector['secret_enc']??null)??'';
    if($auth==='bearer'){
        if($credential==='')throw new InvalidArgumentException('connector_key_missing');
        $headers[]='Authorization: Bearer '.$credential;
    }
    if($auth==='wp_application'){
        if(!str_contains($credential,':'))throw new InvalidArgumentException('connector_wp_auth_invalid');
        $headers[]='Authorization: Basic '.base64_encode($credential);
    }
    $endpoint=$url['url'].$path;
    $post=false;$body='';
    if($type==='whmcs'){
        // WHMCS API requires POST. Hardcode a single read-only public catalogue
        // operation; never execute commands provided by a caller or an LLM.
        if($path!=='/includes/api.php')throw new InvalidArgumentException('connector_whmcs_path_invalid');
        $pair=explode(':',$credential,2);
        if(count($pair)!==2||$pair[0]===''||$pair[1]==='')
            throw new InvalidArgumentException('connector_whmcs_key_invalid');
        $post=true;
        $body=http_build_query(['identifier'=>$pair[0],'secret'=>$pair[1],
            'action'=>'GetProducts','responsetype'=>'json']);
    }
    $ch=curl_init($endpoint);
    curl_setopt_array($ch,[
       CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
       CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_CONNECTTIMEOUT=>6,
       CURLOPT_TIMEOUT=>13,CURLOPT_HTTPHEADER=>$headers,CURLOPT_PROXY=>'',
       CURLOPT_RESOLVE=>[$url['host'].':443:'.$ip],
       CURLOPT_MAXFILESIZE=>131072,
       CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
       CURLOPT_USERAGENT=>'OnTrackVoiceConnector/1.0'
    ]);
    if($post){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,$body);}
    try{$response=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);}
    finally{curl_close($ch);}
    if(!is_string($response)||$http!==200||strlen($response)>131072)
        throw new RuntimeException('connector_read_unavailable');
    $data=json_decode($response,true,20);
    if(!is_array($data))throw new RuntimeException('connector_response_not_json');
    if($type==='whmcs'&&($data['result']??'')!=='success')
        throw new RuntimeException('connector_whmcs_error');
    return $data;
}
function voice_connector_test(int $accountId,int $id):array {
    $connector=one('SELECT * FROM voice_data_connectors WHERE id=? AND account_id=?',[$id,$accountId]);
    if(!$connector)throw new InvalidArgumentException('connector_unavailable');
    $response=voice_connector_request($connector);
    execute("UPDATE voice_data_connectors SET status='verified',last_tested_at=? WHERE id=? AND account_id=?",
        [now(),$id,$accountId]);
    return ['ok'=>true,'type'=>$connector['type'],'keys'=>array_slice(array_keys($response),0,12)];
}

/** Explicitly export only public catalogue/knowledge-like JSON fields to Gemini.
 * No customer records, invoice identifiers, contact details, API keys or secrets.
 */
function voice_connector_public_content(mixed $data,int $limit=2600):string {
    $allowed=['name','title','description','excerpt','content','rendered','text','summary',
        'pages','posts','products','product','services','service','faq','faqs',
        'availability','hours','category','categories','price','pricing','currency'];
    $walk=function(mixed $value,int $depth) use (&$walk,$allowed):mixed {
        if($depth>5)return null;
        if(is_string($value))return mb_substr(trim(strip_tags($value)),0,600);
        if(is_numeric($value)||is_bool($value))return $value;
        if(!is_array($value))return null;
        $result=[];
        foreach(array_slice($value,0,35,true) as $key=>$inner){
            if(is_int($key)){ $result[]=$walk($inner,$depth+1);continue; }
            if(!in_array(strtolower((string)$key),$allowed,true))continue;
            $result[$key]=$walk($inner,$depth+1);
        }
        return $result;
    };
    $safe=$walk($data,0);
    return mb_substr(json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),0,$limit);
}
function voice_connector_context(int $accountId,int $siteId):string {
    $rows=all("SELECT * FROM voice_data_connectors WHERE account_id=? AND site_id=?
        AND status='verified' ORDER BY id DESC LIMIT 2",[$accountId,$siteId]);
    $parts=[];
    foreach($rows as $connector){
        try{
            $data=voice_connector_request($connector);$excerpt=voice_connector_public_content($data);
            if($excerpt!=='[]'&&$excerpt!=='{}')
                $parts[]='['.$connector['type'].': '.mb_substr($connector['name'],0,70).'] '.$excerpt;
        }catch(Throwable){$parts[]='['.$connector['name'].'] المصدر الخارجي غير متاح حاليًا؛ لا تخمّن معلوماته.';}
    }
    return mb_substr(implode("\n",$parts),0,5000);
}
