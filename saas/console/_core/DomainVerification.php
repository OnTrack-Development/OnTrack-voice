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
