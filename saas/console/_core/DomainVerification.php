<?php
declare(strict_types=1);
/** Verification proves control of a DNS name, never TLS/domain routing itself. */
function domain_dns_name(string $hostname):string{
    if(!preg_match('/^(?=.{4,190}$)([a-z0-9-]+\.)+[a-z]{2,}$/D',$hostname))
        throw new InvalidArgumentException('invalid_domain_hostname');
    return '_ontrack-verify.'.$hostname;
}
function domain_dns_verify(int $account,int $domainId,?callable $lookup=null):array{
    $row=one('SELECT id,hostname,verification_token,status
        FROM domains WHERE id=? AND account_id=?',[$domainId,$account]);
    if(!$row)throw new InvalidArgumentException('domain_not_owned');
    if($row['status']==='active')return ['verified'=>true,'tls_active'=>true];
    $name=domain_dns_name((string)$row['hostname']);
    $records=$lookup!==null?$lookup($name):@dns_get_record($name,DNS_TXT);
    if(!is_array($records))$records=[];
    $needle='ontrack-verify='.(string)$row['verification_token'];
    $matched=false;
    foreach($records as $record){
        if(!is_array($record))continue;
        $text=isset($record['entries'])&&is_array($record['entries'])
            ?implode('',$record['entries']):(string)($record['txt']??'');
        if(strlen($text)<=256 && hash_equals($needle,$text)){$matched=true;break;}
    }
    if(!$matched)return ['verified'=>false,'tls_active'=>false,'reason'=>'dns_txt_unverified','name'=>$name];
    execute("UPDATE domains SET status='dns_verified' WHERE id=? AND account_id=?
      AND status IN ('pending','dns_verified')",[$domainId,$account]);
    return ['verified'=>true,'tls_active'=>false,
        'reason'=>'dns_verified_tls_pending','name'=>$name];
}


/** The domain must point at this platform, never an arbitrary LAN/third-party IP.
 * A valid TXT record alone does NOT make it safe to send a server-side request.
 */
function domain_points_to_platform(string $hostname,?callable $resolve=null):bool {
    $lookup=$resolve??static function(string $host):array {
        $cnames=@dns_get_record($host,DNS_CNAME);
        $ips=@gethostbynamel($host);
        return ['cname'=>is_array($cnames)?$cnames:[],
            'addresses'=>is_array($ips)?$ips:[]];
    };
    $domain=$lookup($hostname);
    $platform=$lookup(WHITE_LABEL_PLATFORM_HOST);
    if(!is_array($domain)||!is_array($platform))return false;
    $target=false;
    foreach(($domain['cname']??[]) as $entry) {
        $value=is_array($entry)?($entry['target']??''):'';
        if(strtolower(rtrim((string)$value,'.'))===WHITE_LABEL_PLATFORM_HOST)$target=true;
    }
    $safeIp=static fn(string $ip):bool=>(bool)filter_var($ip,FILTER_VALIDATE_IP,
        FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE);
    $ips=array_values(array_filter($platform['addresses']??[],static fn($ip)=>is_string($ip)&&$safeIp($ip)));
    if(!$ips)return false;
    $intersection=array_intersect($ips,$domain['addresses']??[]);
    return $target || (bool)$intersection;
}
/** HTTPS probes are pinned to a known platform IP; no redirects, no private IP,
 * and the certificate must validate for the reseller's exact hostname.
 */
function domain_https_probe(string $hostname,string $token,?callable $probe=null):bool {
    $expected='portal-ready:'.hash_hmac('sha256',$hostname,$token);
    if($probe!==null)return $probe($hostname,$expected)===true;
    if(!function_exists('curl_init'))return false;
    $ips=@gethostbynamel(WHITE_LABEL_PLATFORM_HOST);
    if(!is_array($ips))return false;
    foreach($ips as $ip){
        if(!filter_var($ip,FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))continue;
        $ch=curl_init('https://'.$hostname.'/portal-health');
        if($ch===false)continue;
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>7,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_MAXREDIRS=>0,CURLOPT_PROXY=>'',
            CURLOPT_RESOLVE=>[$hostname.':443:'.$ip],
            CURLOPT_USERAGENT=>'OnTrack-Portal-Domain-Verify/1.0'
        ]);
        $body=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($status===200 && is_string($body)&&hash_equals($expected,trim($body)))return true;
    }
    return false;
}
/** Reseller-owned activation is allowed only after DNS and real HTTPS virtual-host
 * routing are proven. It does NOT provision the virtual host or certificate.
 */
function domain_https_activate(int $account,int $domainId,?callable $lookup=null,
    ?callable $pointsTo=null,?callable $probe=null):array {
    $row=one("SELECT d.*,a.kind,a.status account_status FROM domains d
        JOIN accounts a ON a.id=d.account_id
        WHERE d.id=? AND d.account_id=?",[$domainId,$account]);
    if(!$row||$row['kind']!=='reseller'||$row['account_status']!=='active')
        throw new InvalidArgumentException('domain_not_owned');
    // A duplicate hostname must be reconciled by the platform, rather than
    // activating a second tenant or allowing SELECT ... LIMIT 1 to pick one.
    if(one('SELECT id FROM domains WHERE hostname=? AND id<>? LIMIT 1',
        [$row['hostname'],$domainId]))
        return ['active'=>false,'reason'=>'hostname_conflict'];
    if($row['status']==='active')return ['active'=>true,'reason'=>'already_active'];
    $verified=domain_dns_verify($account,$domainId,$lookup);
    if(!$verified['verified'])return ['active'=>false,'reason'=>'dns_txt_unverified'];
    $host=(string)$row['hostname'];
    $targetOk=$pointsTo!==null?$pointsTo($host):domain_points_to_platform($host);
    if($targetOk!==true)return ['active'=>false,'reason'=>'dns_target_unverified'];
    if(!domain_https_probe($host,(string)$row['verification_token'],$probe))
        return ['active'=>false,'reason'=>'https_virtualhost_not_ready'];
    $updated=execute("UPDATE domains SET status='active'
        WHERE id=? AND account_id=? AND status='dns_verified'",[$domainId,$account]);
    if($updated!==1)throw new RuntimeException('domain_activation_race');
    return ['active'=>true,'reason'=>'https_verified'];
}
