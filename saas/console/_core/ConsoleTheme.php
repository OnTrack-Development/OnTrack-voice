<?php
declare(strict_types=1);
/**
 * Strict tenant-owned visual palette. All CSS values are validated #RRGGBB,
 * never arbitrary CSS/URLs. The customer may override the parent reseller's
 * default without affecting siblings, other resellers or platform settings.
 */
function console_theme_init():void {
    static $done=false;
    if($done)return;
    $engine=driver()==='mysql'
        ?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS console_theme_palettes(
        account_id BIGINT PRIMARY KEY,
        palette_json TEXT NOT NULL,
        updated_at VARCHAR(19) NOT NULL
    )".$engine);
    $done=true;
}
function console_theme_defaults():array {
    return [
        'layout'=>'split',
        'dark_sidebar'=>'#171c24',
        'dark_content'=>'#0d1015',
        'dark_accent'=>'#cb2925',
        'light_sidebar'=>'#ffffff',
        'light_content'=>'#f5f7fa',
        'light_accent'=>'#bd2421',
    ];
}
function console_theme_valid_hex(mixed $value):bool {
    return is_string($value)&&preg_match('/^#[0-9a-fA-F]{6}$/D',$value)===1;
}
function console_theme_clean(array $incoming):array {
    $default=console_theme_defaults();
    $layout=(string)($incoming['layout']??'');
    if(!in_array($layout,['split','single'],true))
        throw new InvalidArgumentException('اختار لون موحّد أو ألوان منفصلة.');
    $output=['layout'=>$layout];
    foreach(array_keys($default) as $key){
        if($key==='layout')continue;
        $value=$incoming[$key]??null;
        if(!console_theme_valid_hex($value))
            throw new InvalidArgumentException('صيغة اللون غير صالحة: '.$key);
        $output[$key]=strtolower((string)$value);
    }
    // "One color" mode is genuine, not merely a visual switch in the editor.
    if($layout==='single'){
        $output['dark_sidebar']=$output['dark_content'];
        $output['light_sidebar']=$output['light_content'];
    }
    return $output;
}
function console_theme_stored(int $accountId):?array {
    if($accountId<1)return null;
    console_theme_init();
    $row=one('SELECT palette_json FROM console_theme_palettes WHERE account_id=?',[$accountId]);
    if(!$row)return null;
    $json=json_decode((string)$row['palette_json'],true);
    if(!is_array($json))return null;
    try{return console_theme_clean($json);}
    catch(InvalidArgumentException){return null;}
}
function console_theme_effective(int $accountId):array {
    $preset=console_theme_defaults();
    if($accountId<1)return $preset;
    $stored=console_theme_stored($accountId);
    if($stored)return $stored;
    $account=account($accountId);
    if(!$account)return $preset;
    $parent=(int)($account['parent_id']??0);
    if($account['kind']==='customer' && $parent>0){
        $reseller=account($parent);
        if($reseller&&$reseller['kind']==='reseller'){
            $parentStored=console_theme_stored($parent);
            if($parentStored)return $parentStored;
        }
    }
    return $preset;
}
function console_theme_write(array $actor,array $palette,bool $reset=false):void {
    if(active_support())
        throw new InvalidArgumentException('لا يمكن تغيير ألوان الحساب داخل جلسة الدعم.');
    $accountId=(int)($actor['account_id']??0);
    if($accountId<1||!in_array((string)($actor['role']??''),['owner','admin'],true))
        throw new InvalidArgumentException('تعديل ألوان الحساب متاح للمالك والإدارة فقط.');
    $me=me();
    $member=$me?one('SELECT role FROM memberships WHERE user_id=? AND account_id=?',
        [(int)$me['id'],$accountId]):null;
    if(!$member||!in_array((string)$member['role'],['owner','admin'],true))
        throw new InvalidArgumentException('الحساب خارج صلاحياتك.');
    console_theme_init();
    if($reset){
        execute('DELETE FROM console_theme_palettes WHERE account_id=?',[$accountId]);
        return;
    }
    $clean=console_theme_clean($palette);
    $payload=json_encode($clean,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if(one('SELECT account_id FROM console_theme_palettes WHERE account_id=?',[$accountId])){
        execute('UPDATE console_theme_palettes SET palette_json=?,updated_at=? WHERE account_id=?',
            [$payload,now(),$accountId]);
    }else{
        insert('INSERT INTO console_theme_palettes(account_id,palette_json,updated_at) VALUES (?,?,?)',
            [$accountId,$payload,now()]);
    }
}
/** WCAG text token calculated on the server, not guessed by CSS filters. */
function console_theme_luminance(string $hex):float {
    $values=[];
    foreach([1,3,5] as $position){
        $v=hexdec(substr($hex,$position,2))/255.0;
        $values[]=$v<=0.04045?$v/12.92:pow(($v+0.055)/1.055,2.4);
    }
    return 0.2126*$values[0]+0.7152*$values[1]+0.0722*$values[2];
}
function console_theme_on(string $hex):string {
    $lum=console_theme_luminance($hex);
    return (($lum+0.05)/0.05)>=((1.05)/($lum+0.05))?'#10131a':'#ffffff';
}
function console_theme_vars(array $palette):string {
    $p=console_theme_clean($palette);
    $names=[
      'dark_sidebar'=>'--ot-dark-sidebar','dark_content'=>'--ot-dark-content',
      'dark_accent'=>'--ot-dark-accent',
      'light_sidebar'=>'--ot-light-sidebar','light_content'=>'--ot-light-content',
      'light_accent'=>'--ot-light-accent',
    ];
    $out=[];
    foreach($names as $field=>$cssName)$out[]=$cssName.':'.$p[$field];
    foreach(['dark','light'] as $mode)
        $out[]='--ot-'.$mode.'-on-accent:'.console_theme_on($p[$mode.'_accent']);
    return implode(';',$out).';';
}
function console_theme_attribute(int $accountId):string {
    $p=console_theme_effective($accountId);
    return ' data-ot-surface="'.htmlspecialchars($p['layout'],ENT_QUOTES,'UTF-8').
        '" style="'.htmlspecialchars(console_theme_vars($p),ENT_QUOTES,'UTF-8').'"';
}
