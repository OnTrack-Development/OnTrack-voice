<?php

namespace App\Support;

use App\Models\VoiceDncEntry;
use PDO;
use Throwable;

final class VoiceDncBridge
{
    public static function normalize(string $phone): ?string
    {
        $digits=preg_replace('/[^0-9]/','',$phone);
        if(!is_string($digits)||$digits==='')return null;
        if(str_starts_with($digits,'00'))$digits=substr($digits,2);
        if(preg_match('/^01[0-9]{9}$/D',$digits))$digits='20'.substr($digits,1);
        return preg_match('/^[0-9]{8,40}$/D',$digits)?$digits:null;
    }

    /** Laravel mirror first, then the optional authoritative Console ledger. */
    public static function isSuppressed(int $tenantId,string $phone):bool
    {
        $key=self::normalize($phone);
        if($tenantId<=0||$key===null)return true;
        if(VoiceDncEntry::query()->forTenant($tenantId)->where('phone_normalized',$key)->exists())return true;
        $accountId=self::accountId($tenantId);
        $pdo=self::consolePdo();
        if($accountId===null||$pdo===null)return false;
        try {
            $query=$pdo->prepare('SELECT phone_key FROM dnc_suppressions WHERE account_id=? AND phone_key=?');
            $query->execute([$accountId,$key]);
            if($query->fetchColumn()!==false)return true;
            // Keep the Console legacy-contact fallback for accounts not yet backfilled.
            $query=$pdo->prepare('SELECT phone FROM contacts WHERE account_id=? AND dnc=1');
            $query->execute([$accountId]);
            while(($phone=$query->fetchColumn())!==false) {
                if(self::normalize((string)$phone)===$key)return true;
            }
        } catch(Throwable $e) {
            // A standalone Laravel fixture or unavailable Console must not crash imports.
        }
        return false;
    }

    /** Optional best-effort mirror; the Eloquent row remains authoritative locally. */
    public static function mirrorToConsole(VoiceDncEntry $entry):void
    {
        $accountId=self::accountId((int)$entry->tenant_id);
        if($accountId===null||($entry->account_id!==null && (int)$entry->account_id!==$accountId))return;
        $key=self::normalize((string)$entry->phone_normalized);
        $pdo=self::consolePdo();
        if($key===null||$pdo===null)return;
        try {
            $sql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
                ?'INSERT IGNORE INTO dnc_suppressions(account_id,phone_key,source,created_at) VALUES (?,?,?,?)'
                :'INSERT OR IGNORE INTO dnc_suppressions(account_id,phone_key,source,created_at) VALUES (?,?,?,?)';
            $source=in_array($entry->source,['contact_optout','booking_no_consent','legacy_contact'],true)
                ?$entry->source:'contact_optout';
            $pdo->prepare($sql)->execute([$accountId,$key,$source,gmdate('Y-m-d H:i:s')]);
        } catch(Throwable $e) {
            // The mirror never changes the success of a committed Laravel opt-out.
        }
    }

    private static function accountId(int $tenantId):?int
    {
        if($tenantId<=0)return null;
        $path=(string)(getenv('ONTRACK_TENANT_ACCOUNT_BRIDGE_FILE')?:'/home/appontrack/.ontrack-saas/tenant-account-bridge.json');
        if(!is_file($path)||is_link($path))return null;
        $mode=@fileperms($path);
        if(!is_int($mode)||(PHP_OS_FAMILY!=='Windows' && ($mode & 0077)!==0))return null;
        $data=json_decode((string)@file_get_contents($path,false,null,0,65537),true);
        $links=$data['account_to_tenant']??null;
        if(!is_array($links))return null;
        $found=null;
        foreach($links as $account=>$tenant) {
            if(!ctype_digit((string)$account)||filter_var($tenant,FILTER_VALIDATE_INT)===false)return null;
            if((int)$account<=0||(int)$tenant<=0)return null;
            if((int)$tenant===$tenantId) {
                if($found!==null)return null;
                $found=(int)$account;
            }
        }
        return $found;
    }

    private static function consolePdo():?PDO
    {
        $dsn=getenv('ONTRACK_CONSOLE_DNC_DSN');
        if(!is_string($dsn)||!preg_match('/^(mysql|sqlite):/D',$dsn))return null;
        try {
            return new PDO($dsn,(string)(getenv('ONTRACK_CONSOLE_DNC_USER')?:''),
                (string)(getenv('ONTRACK_CONSOLE_DNC_PASSWORD')?:''),
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                 PDO::ATTR_TIMEOUT=>2]);
        } catch(Throwable $e) {
            return null;
        }
    }
}
