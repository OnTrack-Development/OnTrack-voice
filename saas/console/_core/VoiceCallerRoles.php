<?php
declare(strict_types=1);
/** Number identifies a probable caller; never treat caller ID as sufficient authentication. */
function voice_caller_phone(string $phone):string {
    $phone=trim($phone);
    if(!preg_match('/^\\+[1-9][0-9]{7,14}$/D',$phone))
        throw new InvalidArgumentException('caller_phone_requires_e164');
    return $phone;
}
function voice_caller_permissions(string $role):array {
    return match($role){
        'owner','manager'=>['summary.read','bookings.read','tickets.read','billing.read','services.read','website.read'],
        'accountant'=>['summary.read','billing.read'],
        'reception'=>['summary.read','bookings.read','tickets.read'],
        'support'=>['summary.read','tickets.read','services.read'],
        'viewer'=>['summary.read'],
        default=>throw new InvalidArgumentException('caller_role_unknown')
    };
}
function voice_caller_add(int $accountId,int $siteId,string $name,string $phone,string $role,string $pin):int {
    $phone=voice_caller_phone($phone);$grants=voice_caller_permissions($role);
    if(trim($name)===''||mb_strlen($name)>120||!preg_match('/^[0-9]{6,10}$/D',$pin))
        throw new InvalidArgumentException('caller_name_or_pin_invalid');
    if(!one("SELECT id FROM sites WHERE id=? AND account_id=? AND status='active'",[$siteId,$accountId]))
        throw new InvalidArgumentException('site_unavailable');
    return insert("INSERT INTO voice_caller_roles
        (account_id,site_id,phone_e164,name,role,permissions_json,pin_hash,status,created_at)
        VALUES (?,?,?,?,?,?,?,'active',?)",[
        $accountId,$siteId,$phone,trim($name),$role,
        json_encode($grants,JSON_THROW_ON_ERROR),password_hash($pin,PASSWORD_DEFAULT),now()]);
}
function voice_caller_lookup(int $accountId,int $siteId,string $phone):?array {
    $phone=voice_caller_phone($phone);
    $row=one("SELECT id,name,role,permissions_json,status FROM voice_caller_roles
        WHERE account_id=? AND site_id=? AND phone_e164=?",[$accountId,$siteId,$phone]);
    if(!$row||$row['status']!=='active')return null;
    return ['id'=>(int)$row['id'],'name'=>$row['name'],'role'=>$row['role'],
        'verified'=>false,'permissions'=>[]]; // Never return grants based on spoofable ANI.
}
function voice_caller_verify(int $accountId,int $siteId,string $phone,string $pin):?array {
    $phone=voice_caller_phone($phone);
    if(!preg_match('/^[0-9]{6,10}$/D',$pin))return null;
    $row=one("SELECT * FROM voice_caller_roles WHERE account_id=? AND site_id=?
        AND phone_e164=? AND status='active'",[$accountId,$siteId,$phone]);
    if(!$row||!password_verify($pin,(string)$row['pin_hash']))return null;
    $allowed=voice_caller_permissions((string)$row['role']);
    $stored=json_decode((string)$row['permissions_json'],true)?:[];
    return ['id'=>(int)$row['id'],'name'=>$row['name'],'role'=>$row['role'],
        'verified'=>true,'permissions'=>array_values(array_intersect($allowed,(array)$stored))];
}
function voice_caller_can(?array $principal,string $scope):bool {
    return $principal!==null&&($principal['verified']??false)===true
        &&in_array($scope,$principal['permissions']??[],true);
}
