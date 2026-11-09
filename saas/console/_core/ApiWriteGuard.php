<?php
declare(strict_types=1);

/**
 * Bounded account-scoped API write quotas. All state stays under SaaS_PRIVATE
 * (never public_html). LOCK_EX makes concurrent PHP workers share the counter.
 * No IP, bearer, phone or other PII is stored in readable form.
 */
function saas_api_write_quota(string $directory,string $identity,int $now,int $window,int $limit):bool {
    if($identity==='' || $now<0 || $window<1 || $limit<1 || $limit>100000)return false;
    if(!is_dir($directory) && !@mkdir($directory,0700,true) && !is_dir($directory))return false;
    if(is_link($directory) || !is_writable($directory))return false;
    $bucket=intdiv($now,$window);
    $key=hash('sha256',"ontrack-api-v1\n".$identity."\n".$window."\n".$bucket);
    $path=$directory.'/'.$key.'.count';
    if(is_link($path))return false;
    $fp=@fopen($path,'c+');
    if(!is_resource($fp))return false;
    try{
        @chmod($path,0600);
        if(!flock($fp,LOCK_EX))return false;
        try{
            rewind($fp);
            $data=stream_get_contents($fp,64);
            if(!is_string($data) || ($data!=='' && !preg_match('/\A[0-9]{1,7}\z/D',$data)))return false;
            $count=$data===''?0:(int)$data;
            if($count >= $limit)return false;
            $next=(string)($count+1);
            rewind($fp);
            if(!ftruncate($fp,0) || fwrite($fp,$next)!==strlen($next) || !fflush($fp))return false;
            return true;
        }finally{flock($fp,LOCK_UN);}
    }finally{fclose($fp);}
}
function saas_api_write_allowed(int $accountId,int $keyId,string $operation):bool {
    if($accountId<1 || $keyId<1 ||
       !preg_match('/\A[a-z][a-z0-9.]{1,70}\z/D',$operation))return false;
    $identity=$accountId.'|'.$keyId.'|'.$operation;
    $dir=private_path().'/api-rate-v1';
    $now=time();
    // Normal integrations and bulk imports use their dedicated operations;
    // this deliberately bounds only repeated API write requests.
    return saas_api_write_quota($dir,$identity,$now,60,30)
        && saas_api_write_quota($dir,$identity,$now,86400,500);
}
