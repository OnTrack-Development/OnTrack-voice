<?php
declare(strict_types=1);

/** Distinguish Fish billing failure from temporary concurrency (429) and
 * ordinary bad input. Never forward upstream error bodies or credentials.
 * Circuit breaker is scoped to the encrypted *key revision*, and expires:
 * adding balance to the same Fish account gets a fresh retry after 15 min.
 */
final class FishTtsFallbackPolicy {
    public static function classify(int $status,string $body):string {
        $message=strtolower(substr($body,0,4096));
        $credit=preg_match('~(insufficient|no\s+(?:api\s+)?credits?|out\s+of\s+(?:credits?|balance)|balance\s+(?:too\s+low|exhausted)|quota\s+(?:exceeded|exhausted|insufficient)|credit\s+limit\s+exceeded|not\s+enough\s+balance|payment\s+required)~i',$message)===1;
        if($status===402||($credit&&in_array($status,[400,403,429],true)))
            return 'premium_tts_credit_exhausted';
        if($status===429)return 'premium_tts_rate_limited';
        if(in_array($status,[401,403,500,502,503,504],true))
            return 'premium_tts_provider_unavailable';
        return 'premium_tts_generation_failed';
    }
    public static function clientHttp(string $code):int {
        return match($code){
            'premium_tts_credit_exhausted'=>402,
            'premium_tts_rate_limited'=>429,
            'premium_tts_provider_unavailable'=>503,
            default=>502,
        };
    }
    public static function ttl(string $code):int {
        return match($code){
            'premium_tts_credit_exhausted'=>900,
            'premium_tts_rate_limited'=>45,
            default=>0,
        };
    }
    private static function file():string {
        return private_path().'/voice-lab/fish-tts-circuit.json';
    }
    /** Hash of encrypted secret, not the raw Fish key or token. */
    public static function fingerprint(string $encryptedKey):string {
        return hash('sha256',$encryptedKey);
    }
    public static function active(string $fingerprint):?string {
        $path=self::file();
        if(is_link($path)||!is_file($path))return null;
        $value=json_decode((string)file_get_contents($path),true);
        if(!is_array($value)||!hash_equals((string)($value['fingerprint']??''),$fingerprint))return null;
        if((int)($value['until']??0)<=time())return null;
        $code=(string)($value['code']??'');
        return in_array($code,['premium_tts_credit_exhausted','premium_tts_rate_limited'],true)?$code:null;
    }
    public static function mark(string $fingerprint,string $code):void {
        $ttl=self::ttl($code);
        if($ttl<=0)return;
        $path=self::file();
        $dir=dirname($path);
        if(is_link($dir)||is_link($path))return;
        if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))return;
        $data=json_encode(['fingerprint'=>$fingerprint,'code'=>$code,
                           'until'=>time()+$ttl],JSON_THROW_ON_ERROR);
        $tmp=$path.'.'.bin2hex(random_bytes(6)).'.tmp';
        try{
            if(file_put_contents($tmp,$data,LOCK_EX)!==strlen($data))return;
            @chmod($tmp,0600);
            @rename($tmp,$path);
        }finally{if(is_file($tmp))@unlink($tmp);}
    }
}
