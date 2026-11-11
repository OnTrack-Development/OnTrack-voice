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
        if(!is_int($mode)||($mode & 0077)!==0)return $links;
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
        return false;
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
}
