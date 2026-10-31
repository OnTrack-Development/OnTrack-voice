<?php
declare(strict_types=1);

/** Isolated single-device demo store. No campaign is ever auto-dialed here. */
function pilot_store_root(): string { return MOBILE_STORE.'/pilot'; }
function pilot_read(string $kind): array {
    if (!in_array($kind,['contacts','campaigns','attempts'],true))throw new InvalidArgumentException('invalid_kind');
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
function pilot_campaign_create(string $name,string $starts,string $ends,string $scenario,array $contactIds=[]): array {
    $name=trim($name);$scenario=trim($scenario);
    if(count($contactIds)>2000)throw new InvalidArgumentException('too_many_targets');
    foreach($contactIds as $id)if(!is_string($id)||!preg_match('/^[0-9a-f]{24}$/D',$id))throw new InvalidArgumentException('target_id');
    $contactIds=array_values(array_unique($contactIds));
    if($name===''||strlen($name)>160||strlen($scenario)>8000)throw new InvalidArgumentException('invalid_campaign');
    $start=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$starts,new DateTimeZone('Africa/Cairo'));
    $end=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$ends,new DateTimeZone('Africa/Cairo'));
    if(!$start||!$end||$end<=$start||$end->getTimestamp()-$start->getTimestamp()>31*86400)
        throw new InvalidArgumentException('invalid_schedule');
    return pilot_transaction(static function()use($name,$start,$end,$scenario,$contactIds){
        $items=pilot_read('campaigns');
        $available=pilot_read('contacts');
        $known=array_column($available,'id');
        foreach($contactIds as $id)if(!in_array($id,$known,true))throw new InvalidArgumentException('unknown_target');
        $eligible=count(array_filter($available,static fn($c)=>!empty($c['consented'])&&empty($c['do_not_call'])&&(!$contactIds||in_array($c['id'],$contactIds,true))));
        $row=['id'=>bin2hex(random_bytes(12)),'name'=>$name,'scenario'=>$scenario,'status'=>'draft',
            'timezone'=>'Africa/Cairo','starts_at'=>$start->format('c'),'ends_at'=>$end->format('c'),
            'eligible_contacts_snapshot'=>$eligible,'contact_ids'=>$contactIds,'created_at'=>gmdate('c'),
            'note'=>'No device dial job dispatched; requires reviewed device executor'];
        $items[]=$row;pilot_atomic('campaigns',$items);
        return $row;
    });
}

/**
 * Strictly opt-in campaign queue. Leasing is reserved for the authenticated
 * Android device, and a lease alone must never cause a phone call.
 */
function pilot_campaign_transition(string $id,string $action): array {
    if(!in_array($action,['pause','cancel','resume'],true))throw new InvalidArgumentException('action');
    return pilot_transaction(static function() use($id,$action):array {
        $campaigns=pilot_read('campaigns');
        foreach($campaigns as &$campaign) {
            if(!hash_equals((string)$campaign['id'],$id))continue;
            $old=(string)$campaign['status'];
            $allowed=['pause'=>['draft','ready'],'cancel'=>['draft','paused','ready'],'resume'=>['paused']];
            if(!in_array($old,$allowed[$action],true))throw new InvalidArgumentException('transition');
            $campaign['status']=['pause'=>'paused','cancel'=>'cancelled','resume'=>'draft'][$action];
            $campaign['updated_at']=gmdate('c');
            $updated=$campaign;unset($campaign);
            pilot_atomic('campaigns',$campaigns);
            return $updated;
        }
        throw new InvalidArgumentException('campaign_not_found');
    });
}
function pilot_campaign_preview(string $id): array {
    $found=null;
    foreach(pilot_read('campaigns') as $campaign)if(hash_equals((string)$campaign['id'],$id)){$found=$campaign;break;}
    if($found===null)throw new InvalidArgumentException('campaign_not_found');
    $eligible=[];$blocked=0;
    foreach(pilot_read('contacts') as $contact){
        if(!empty($found['contact_ids'])&&!in_array($contact['id'],$found['contact_ids'],true))continue;
        if(empty($contact['consented'])||!empty($contact['do_not_call'])){$blocked++;continue;}
        $eligible[]=['id'=>$contact['id'],'name'=>$contact['name'],'phone'=>$contact['phone']];
    }
    return ['campaign'=>$found,'eligible'=>$eligible,'excluded'=>$blocked,'auto_dial_enabled'=>false];
}

/** The only state that permits SIM dispatch is explicitly armed by the owner. */
function pilot_campaign_arm(string $id): array {
    return pilot_transaction(static function()use($id):array{
        $campaigns=pilot_read('campaigns');
        $now=new DateTimeImmutable('now',new DateTimeZone('Africa/Cairo'));
        foreach($campaigns as &$campaign) {
            if(!hash_equals((string)$campaign['id'],$id))continue;
            if(($campaign['status']??'')!=='draft')throw new InvalidArgumentException('not_draft');
            if(new DateTimeImmutable($campaign['ends_at'])<=$now)throw new InvalidArgumentException('expired_window');
            $targets=$campaign['contact_ids']??[];
            $eligible=array_filter(pilot_read('contacts'),static fn($r)=>!empty($r['consented'])&&empty($r['do_not_call'])&&(!$targets||in_array($r['id'],$targets,true)));
            if(!$eligible)throw new InvalidArgumentException('no_consented_contacts');
            $campaign['status']='ready';$campaign['armed_at']=gmdate('c');
            $updated=$campaign;unset($campaign);
            pilot_atomic('campaigns',$campaigns);
            return $updated;
        }
        throw new InvalidArgumentException('campaign_not_found');
    });
}
/**
 * Single-device exclusive claim. A claimed number is NEVER automatically retried
 * even if the device crashes, loses its network, or fails to acknowledge the call.
 */
function pilot_campaign_claim(): ?array {
    return pilot_transaction(static function():?array{
        $campaigns=pilot_read('campaigns');$contacts=pilot_read('contacts');
        $attempts=pilot_read('attempts');
        $now=new DateTimeImmutable('now',new DateTimeZone('Africa/Cairo'));
        foreach($attempts as $attempt)if(($attempt['status']??'')==='leased')return null;
        foreach($campaigns as $campaign){
            if(($campaign['status']??'')!=='ready')continue;
            if($now<new DateTimeImmutable($campaign['starts_at'])||$now>=new DateTimeImmutable($campaign['ends_at']))continue;
            $latest=0;
            foreach($attempts as $attempt)if(($attempt['campaign_id']??'')===$campaign['id']) {
                $latest=max($latest,(int)($attempt['leased_epoch']??0));
            }
            if($latest>0 && time()-$latest<60)return null;
            foreach($contacts as $contact){
                if(!empty($campaign['contact_ids'])&&!in_array($contact['id'],$campaign['contact_ids'],true))continue;
                if(empty($contact['consented'])||!empty($contact['do_not_call']))continue;
                $already=false;
                foreach($attempts as $attempt)if(($attempt['campaign_id']??'')===$campaign['id']&&($attempt['contact_id']??'')===$contact['id']){$already=true;break;}
                if($already)continue;
                $claim=['id'=>bin2hex(random_bytes(16)),'campaign_id'=>$campaign['id'],
                    'contact_id'=>$contact['id'],'phone'=>$contact['phone'],
                    'name'=>$contact['name'],'scenario'=>$campaign['scenario'],'status'=>'leased','leased_epoch'=>time(),
                    'created_at'=>gmdate('c')];
                $attempts[]=$claim;
                pilot_atomic('attempts',$attempts);
                return $claim;
            }
        }
        return null;
    });
}
function pilot_campaign_finish(string $id,string $result): array {
    if(!in_array($result,['completed','failed','unknown'],true))throw new InvalidArgumentException('result');
    return pilot_transaction(static function()use($id,$result):array{
        $attempts=pilot_read('attempts');
        foreach($attempts as &$attempt){
            if(!hash_equals((string)$attempt['id'],$id))continue;
            if(($attempt['status']??'')!=='leased') {
                if(($attempt['status']??'')===$result)return $attempt;
                throw new InvalidArgumentException('attempt_already_finished');
            }
            $attempt['status']=$result;$attempt['finished_at']=gmdate('c');
            $out=$attempt;unset($attempt);pilot_atomic('attempts',$attempts);
            return $out;
        }
        throw new InvalidArgumentException('attempt_not_found');
    });
}

/** Re-check Do Not Call immediately before any leased SIM placement. */
function pilot_campaign_verify_claim(string $id): array {
    $attempt=null;
    foreach(pilot_read('attempts') as $row)
        if(hash_equals((string)$row['id'],$id)){$attempt=$row;break;}
    if($attempt===null || ($attempt['status']??'')!=='leased')return ['allowed'=>false,'reason'=>'not_active'];
    $ready=false;
    foreach(pilot_read('campaigns') as $row)
        if(($row['id']??'')===$attempt['campaign_id'] && ($row['status']??'')==='ready'){
            $now=new DateTimeImmutable('now',new DateTimeZone('Africa/Cairo'));
            $ready=$now>=new DateTimeImmutable($row['starts_at'])&&$now<new DateTimeImmutable($row['ends_at']);
            break;
        }
    if(!$ready)return ['allowed'=>false,'reason'=>'campaign_not_ready'];
    foreach(pilot_read('contacts') as $row)if(($row['id']??'')===$attempt['contact_id']){
        if(!empty($row['consented'])&&empty($row['do_not_call'])&&($row['phone']??'')===$attempt['phone'])
            return ['allowed'=>true,'reason'=>'ok'];
        return ['allowed'=>false,'reason'=>'contact_blocked'];
    }
    return ['allowed'=>false,'reason'=>'contact_deleted'];
}
