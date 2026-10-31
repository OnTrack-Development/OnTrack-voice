<?php
declare(strict_types=1);

/** Isolated single-device demo store. No campaign is ever auto-dialed here. */
function pilot_store_root(): string { return MOBILE_STORE.'/pilot'; }
function pilot_read(string $kind): array {
    if (!in_array($kind,['contacts','campaigns'],true))throw new InvalidArgumentException('invalid_kind');
    $path=pilot_store_root().'/'.$kind.'.json';
    if (!is_file($path) || filesize($path)>3000000)return [];
    $value=json_decode((string)file_get_contents($path),true);
    return is_array($value)?$value:[];
}
function pilot_atomic(string $kind,array $items): void {
    $path=pilot_store_root().'/'.$kind.'.json';
    $temp=tempnam(pilot_store_root(),'.pilot-');
    if($temp===false)throw new RuntimeException('private_temp');
    try{
        $bytes=json_encode($items,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if(file_put_contents($temp,$bytes)===false)throw new RuntimeException('private_write');
        chmod($temp,0600);
        if(!rename($temp,$path))throw new RuntimeException('private_commit');
    } finally {if(is_file($temp))unlink($temp);}
}
function pilot_transaction(callable $handler): mixed {
    $root=pilot_store_root();
    if(!is_dir($root) && !mkdir($root,0700,true) && !is_dir($root))throw new RuntimeException('private_store');
    if(is_link($root))throw new RuntimeException('private_symlink');
    $lock=fopen($root.'/.lock','c');
    if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('private_lock');
    try{return $handler();}finally{flock($lock,LOCK_UN);fclose($lock);}
}
function pilot_number(string $raw): ?string {
    $str=trim($raw);
    if(!preg_match('/^[+0-9 ()\-]{8,28}$/D',$str))return null;
    $digits=preg_replace('/[\s()\-]/','',$str);
    if(str_starts_with($digits,'00'))$digits='+'.substr($digits,2);
    if(preg_match('/^01[0125]\d{8}$/D',$digits))$digits='+2'.$digits;
    if(preg_match('/^201[0125]\d{8}$/D',$digits))$digits='+'.$digits;
    return preg_match('/^\+[1-9]\d{7,14}$/D',$digits)?$digits:null;
}
function pilot_import_contacts(array $rows): array {
    if(count($rows)>2000)throw new InvalidArgumentException('too_many_contacts');
    return pilot_transaction(static function()use($rows){
        $items=pilot_read('contacts');
        $seen=[];foreach($items as $i=>$row)$seen[(string)($row['phone']??'')]=$i;
        $count=['added'=>0,'duplicates'=>0,'invalid'=>0];
        foreach($rows as $row) {
            if(!is_array($row)){$count['invalid']++;continue;}
            $phone=pilot_number((string)($row['phone']??''));
            if($phone===null){$count['invalid']++;continue;}
            if(array_key_exists($phone,$seen)){$count['duplicates']++;continue;}
            $name=trim((string)($row['name']??''));
            $name=function_exists('mb_substr')?mb_substr($name,0,100):substr($name,0,100);
            $items[]=['id'=>bin2hex(random_bytes(12)),'name'=>$name,'phone'=>$phone,
                'consented'=>true,'do_not_call'=>false,'source'=>'owner_approved_import',
                'created_at'=>gmdate('c')];
            $seen[$phone]=count($items)-1;$count['added']++;
        }
        pilot_atomic('contacts',$items);
        return $count;
    });
}
function pilot_dnc(string $id,bool $blocked): bool {
    return pilot_transaction(static function()use($id,$blocked){
        $items=pilot_read('contacts');$found=false;
        foreach($items as &$item)if(hash_equals((string)$item['id'],$id)){
            $item['do_not_call']=$blocked;$found=true;break;
        }
        unset($item);
        if($found)pilot_atomic('contacts',$items);
        return $found;
    });
}
function pilot_campaign_create(string $name,string $starts,string $ends,string $scenario): array {
    $name=trim($name);$scenario=trim($scenario);
    if($name===''||strlen($name)>160||strlen($scenario)>8000)throw new InvalidArgumentException('invalid_campaign');
    $start=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$starts,new DateTimeZone('Africa/Cairo'));
    $end=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$ends,new DateTimeZone('Africa/Cairo'));
    if(!$start||!$end||$end<=$start||$end->getTimestamp()-$start->getTimestamp()>31*86400)
        throw new InvalidArgumentException('invalid_schedule');
    return pilot_transaction(static function()use($name,$start,$end,$scenario){
        $items=pilot_read('campaigns');
        $eligible=count(array_filter(pilot_read('contacts'),static fn($c)=>!empty($c['consented'])&&empty($c['do_not_call'])));
        $row=['id'=>bin2hex(random_bytes(12)),'name'=>$name,'scenario'=>$scenario,'status'=>'draft',
            'timezone'=>'Africa/Cairo','starts_at'=>$start->format('c'),'ends_at'=>$end->format('c'),
            'eligible_contacts_snapshot'=>$eligible,'created_at'=>gmdate('c'),
            'note'=>'No device dial job dispatched; requires reviewed device executor'];
        $items[]=$row;pilot_atomic('campaigns',$items);
        return $row;
    });
}
