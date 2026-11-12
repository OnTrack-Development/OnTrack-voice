<?php
declare(strict_types=1);

/**
 * Transitional boundary between the Console account model and Laravel tenants.
 * Console remains authoritative for DNC and device bindings until an explicit,
 * verified data migration exists. Numeric IDs are never assumed equivalent.
 */
final class TenantAccountBridge {
    /** @return array<int,int> Explicit operator-provisioned account => tenant IDs. */
    private static function links():array {
        static $links=null;
        if($links!==null)return $links;
        $links=[];
        $path=private_path().'/tenant-account-bridge.json';
        if(!is_file($path)||is_link($path))return $links;
        $mode=@fileperms($path);
        if(!is_int($mode)||(PHP_OS_FAMILY!=='Windows' && ($mode & 0077)!==0))return $links;
        $data=json_decode((string)@file_get_contents($path,false,null,0,65537),true);
        if(!is_array($data)||!is_array($data['account_to_tenant']??null))return $links;
        $reverse=[];
        foreach($data['account_to_tenant'] as $account=>$tenant){
            if(!ctype_digit((string)$account)||filter_var($tenant,FILTER_VALIDATE_INT)===false) {
                $links=[];return $links;
            }
            $accountId=(int)$account;$tenantId=(int)$tenant;
            if($accountId<=0||$tenantId<=0||isset($reverse[$tenantId])) {
                $links=[];return $links;
            }
            $links[$accountId]=$tenantId;$reverse[$tenantId]=true;
        }
        return $links;
    }

    public static function tenantId(int $accountId):?int {
        return self::links()[$accountId]??null;
    }

    public static function accountId(int $tenantId):?int {
        if($tenantId<=0)return null;
        $account=array_search($tenantId,self::links(),true);
        return $account===false?null:$account;
    }

    /** Preserve the existing Console DNC ledger and legacy contact fallback. */
    public static function isSuppressed(int $accountId,string $phone):bool {
        $key=dnc_phone_key($phone);
        if($accountId<=0||strlen($key)<8||strlen($key)>40)
            throw new InvalidArgumentException('invalid_dnc_lookup');
        if(dnc_ledger_contains($accountId,$key))return true;
        $blocked=all('SELECT phone FROM contacts WHERE account_id=? AND dnc=1',[$accountId]);
        foreach($blocked as $row){
            if(dnc_phone_key((string)$row['phone'])===$key){
                dnc_record_suppression($accountId,$phone,'legacy_contact');
                return true;
            }
        }
        return self::laravelSuppressed($accountId,$key);
    }

    /** The original account/site/agent-bound bearer query, unchanged. */
    public static function authorizeBinding(string $token):?array {
        if(!preg_match('/^otdev_[a-f0-9]{64}$/D',$token))return null;
        return one("SELECT d.* FROM device_bindings d
            JOIN accounts a ON a.id=d.account_id AND a.status='active'
            JOIN sites s ON s.id=d.site_id AND s.account_id=d.account_id AND s.status='active'
            JOIN agents ag ON ag.id=d.agent_id AND ag.site_id=d.site_id AND ag.account_id=d.account_id AND ag.status!='archived'
            WHERE d.token_hash=? AND d.status='active'",[hash('sha256',$token)]);
    }
    /** Optional mirror reads never replace a valid Console DNC decision. */
    private static function laravelSuppressed(int $accountId,string $key):bool {
        $tenantId=self::tenantId($accountId);
        $pdo=self::laravelPdo();
        if($tenantId===null||$pdo===null)return false;
        try {
            $query=$pdo->prepare('SELECT id FROM voice_dnc_entries WHERE tenant_id=? AND phone_normalized=? LIMIT 1');
            $query->execute([$tenantId,$key]);
            return $query->fetchColumn()!==false;
        }catch(Throwable $e){return false;}
    }

    /** Console opt-out is committed first; the optional mirror is best effort. */
    public static function mirrorDncToLaravel(int $accountId,string $key,string $source):void {
        $tenantId=self::tenantId($accountId);
        $pdo=self::laravelPdo();
        if($tenantId===null||$pdo===null)return;
        try {
            $sql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
                ?'INSERT IGNORE INTO voice_dnc_entries(tenant_id,account_id,phone_normalized,reason,source,created_by,created_at,updated_at) VALUES (?,?,?,?,?,NULL,?,?)'
                :'INSERT OR IGNORE INTO voice_dnc_entries(tenant_id,account_id,phone_normalized,reason,source,created_by,created_at,updated_at) VALUES (?,?,?,?,?,NULL,?,?)';
            $time=gmdate('Y-m-d H:i:s');
            $pdo->prepare($sql)->execute([$tenantId,$accountId,$key,'opt_out',$source,$time,$time]);
        }catch(Throwable $e){/* Console opt-out remains authoritative. */}
    }

    /** Mirror only a committed Console binding. Never store the raw device bearer. */
    public static function mirrorDeviceToLaravel(int $accountId,int $deviceId,string $codeHash):void {
        $tenantId=self::tenantId($accountId);
        $pdo=self::laravelPdo();
        if($tenantId===null||$pdo===null||!preg_match('/^[a-f0-9]{64}$/D',$codeHash))return;
        try {
            $id='console:'.$deviceId;
            $query=$pdo->prepare('SELECT tenant_id,account_id FROM voice_device_pairings WHERE device_id=?');
            $query->execute([$id]);
            $row=$query->fetch(PDO::FETCH_ASSOC);
            $time=gmdate('Y-m-d H:i:s');
            if($row!==false){
                if((int)$row['tenant_id']!==$tenantId||(int)$row['account_id']!==$accountId)return;
                $pdo->prepare("UPDATE voice_device_pairings SET status='active',updated_at=? WHERE device_id=? AND tenant_id=? AND account_id=?")
                    ->execute([$time,$id,$tenantId,$accountId]);
            }else{
                $pdo->prepare('INSERT INTO voice_device_pairings(tenant_id,account_id,device_id,pairing_token_hash,status,last_seen_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$tenantId,$accountId,$id,$codeHash,'active',null,$time,$time]);
            }
        }catch(Throwable $e){/* Console bearer and binding remain authoritative. */}
    }

    public static function revokeMirroredDevice(int $accountId,int $deviceId):void {
        $tenantId=self::tenantId($accountId);
        $pdo=self::laravelPdo();
        if($tenantId===null||$pdo===null)return;
        try {
            $pdo->prepare("UPDATE voice_device_pairings SET status='revoked',updated_at=? WHERE device_id=? AND tenant_id=? AND account_id=?")
                ->execute([gmdate('Y-m-d H:i:s'),'console:'.$deviceId,$tenantId,$accountId]);
        }catch(Throwable $e){/* Never undo a Console revocation. */}
    }

    /** Explicit private configuration; missing Laravel never interrupts Console. */
    private static function laravelPdo():?PDO {
        $dsn=getenv('ONTRACK_LARAVEL_DNC_DSN');
        $user=getenv('ONTRACK_LARAVEL_DNC_USER')?:'';
        $password=getenv('ONTRACK_LARAVEL_DNC_PASSWORD')?:'';
        if($dsn===false||$dsn===''){
            $path=private_path().'/laravel-mirror-db.json';
            if(!is_file($path)||is_link($path))return null;
            $mode=@fileperms($path);
            if(!is_int($mode)||(PHP_OS_FAMILY!=='Windows' && ($mode & 0077)!==0))return null;
            $settings=json_decode((string)@file_get_contents($path,false,null,0,4097),true);
            if(!is_array($settings))return null;
            $dsn=$settings['dsn']??'';
            $user=$settings['user']??'';
            $password=$settings['password']??'';
        }
        if(!is_string($dsn)||!preg_match('/^(mysql|sqlite):/D',$dsn))return null;
        try {
            return new PDO($dsn,(string)$user,(string)$password,[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT=>2,
            ]);
        }catch(Throwable $e){return null;}
    }

}

