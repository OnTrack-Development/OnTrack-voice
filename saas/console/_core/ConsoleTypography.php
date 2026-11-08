<?php
declare(strict_types=1);

/**
 * OnTrack tenant + personal Arabic typography preferences.
 * user_id=0: tenant-wide default. user_id>0: personal override for that tenant.
 * No custom font family/URL/HTML is accepted, avoiding CSS injection.
 */
function console_font_options():array {
    return [
        'plex'=>'IBM Plex Sans Arabic',
        'tajawal'=>'Tajawal',
        'cairo'=>'Cairo',
    ];
}
function console_font_init():void {
    static $initialized=false;
    if($initialized)return;
    $engine=driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS console_font_preferences(
        account_id BIGINT NOT NULL,
        user_id BIGINT NOT NULL,
        font_code VARCHAR(20) NOT NULL,
        updated_at VARCHAR(19) NOT NULL,
        PRIMARY KEY(account_id,user_id)
    )".$engine);
    $initialized=true;
}
function console_font_valid(string $font):bool {
    return array_key_exists($font,console_font_options());
}
function console_font_tenant(int $accountId):string {
    console_font_init();
    $a=account($accountId);
    if(!$a)return 'plex';
    $row=one('SELECT font_code FROM console_font_preferences WHERE account_id=? AND user_id=0',[$accountId]);
    if($row&&console_font_valid((string)$row['font_code']))return (string)$row['font_code'];
    // A customer's portal inherits the reseller's default, unless the
    // customer has explicitly chosen its own company-wide font.
    $parent=(int)($a['parent_id']??0);
    if($parent>0){
        $reseller=account($parent);
        if($reseller&&$reseller['kind']==='reseller'){
            $fromReseller=one('SELECT font_code FROM console_font_preferences WHERE account_id=? AND user_id=0',[$parent]);
            if($fromReseller&&console_font_valid((string)$fromReseller['font_code']))
                return (string)$fromReseller['font_code'];
        }
    }
    return 'plex';
}
function console_font_personal(int $accountId,int $userId):?string {
    if($accountId<1||$userId<1)return null;
    console_font_init();
    $row=one('SELECT font_code FROM console_font_preferences WHERE account_id=? AND user_id=?',[$accountId,$userId]);
    if(!$row)return null;
    return console_font_valid((string)$row['font_code'])?(string)$row['font_code']:null;
}
function console_font_effective(array $context):string {
    $id=(int)($context['account_id']??$context['id']??0);
    if($id<1)return 'plex';
    // Support impersonation never inherits the support agent's unrelated
    // personal preferences or silently edits the impersonated tenant.
    if(!empty($context['support_origin']))return console_font_tenant($id);
    $principal=me();
    $uid=(int)($principal['id']??0);
    if($uid>0){
        $personal=console_font_personal($id,$uid);
        if($personal!==null)return $personal;
    }
    return console_font_tenant($id);
}
function console_font_save(array $context,string $font,string $scope):void {
    if(active_support())throw new InvalidArgumentException('لا يمكن تعديل الخط خلال جلسة الدعم.');
    $id=(int)($context['account_id']??0);
    $user=me();
    $uid=(int)($user['id']??0);
    if($id<1||$uid<1)throw new InvalidArgumentException('تسجيل الدخول مطلوب.');
    if(!in_array($scope,['tenant','personal'],true))
        throw new InvalidArgumentException('نطاق تغيير الخط غير صالح.');
    if($font!=='inherit'&&!console_font_valid($font))
        throw new InvalidArgumentException('اختر خطًا عربيًا معتمدًا.');
    $membership=one('SELECT role FROM memberships WHERE user_id=? AND account_id=?',[$uid,$id]);
    if(!$membership)throw new InvalidArgumentException('الحساب خارج صلاحياتك.');
    if($scope==='tenant'&&!in_array((string)$membership['role'],['owner','admin'],true))
        throw new InvalidArgumentException('تغيير خط الشركة متاح للمالك والإدارة فقط.');
    $targetUser=$scope==='tenant'?0:$uid;
    console_font_init();
    if($font==='inherit'){
        execute('DELETE FROM console_font_preferences WHERE account_id=? AND user_id=?',[$id,$targetUser]);
        return;
    }
    if(one('SELECT font_code FROM console_font_preferences WHERE account_id=? AND user_id=?',
        [$id,$targetUser])){
        execute('UPDATE console_font_preferences SET font_code=?,updated_at=? WHERE account_id=? AND user_id=?',
            [$font,now(),$id,$targetUser]);
    }else{
        insert('INSERT INTO console_font_preferences(account_id,user_id,font_code,updated_at)
            VALUES (?,?,?,?)',[$id,$targetUser,$font,now()]);
    }
}
