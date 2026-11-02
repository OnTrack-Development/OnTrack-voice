<?php
declare(strict_types=1);
require_once __DIR__.'/DncGuard.php';
/** No pilot bearer, no user-provided account/site identifiers. */
function tenant_workspace_id(int $id):string{return str_pad(dechex($id),24,'0',STR_PAD_LEFT);}
function tenant_workspace_parse_id(mixed $id):int{
    if(!is_string($id)||!preg_match('/^[a-f0-9]{24}$/D',$id))throw new InvalidArgumentException('invalid_id');
    $value=hexdec(ltrim($id,'0')?:'0');
    if(!is_int($value)||$value<1)throw new InvalidArgumentException('invalid_id');
    return $value;
}
function tenant_workspace_contacts(array $b):array{
    $rows=all('SELECT id,name,phone,dnc FROM contacts WHERE account_id=? AND site_id=? ORDER BY id DESC LIMIT 250',[$b['account_id'],$b['site_id']]);
    $contacts=[];
    foreach($rows as $r)$contacts[]=['id'=>tenant_workspace_id((int)$r['id']),'name'=>$r['name'],'phone'=>$r['phone'],'do_not_call'=>(int)$r['dnc']===1];
    return ['contacts'=>$contacts,'total'=>(int)one('SELECT COUNT(*) n FROM contacts WHERE account_id=? AND site_id=?',[$b['account_id'],$b['site_id']])['n']];
}
function tenant_workspace_import(array $b,array $rows):array{
    if(count($rows)>2000)throw new InvalidArgumentException('too_many_contacts');
    $a=(int)$b['account_id'];$s=(int)$b['site_id'];
    $known=[];
    foreach(all('SELECT phone FROM contacts WHERE account_id=? AND site_id=?',[$a,$s]) as $r)
        $known[dnc_phone_key((string)$r['phone'])]=true;
    $counts=['added'=>0,'duplicates'=>0,'invalid'=>0];
    foreach($rows as $r){
        if(!is_array($r)||!is_string($r['phone']??null)||!is_string($r['name']??null)){++$counts['invalid'];continue;}
        $phone=trim($r['phone']);$name=trim($r['name']);
        if(mb_strlen($name)>120||!preg_match('/^\\+?[0-9 ()-]{8,30}$/D',$phone)){++$counts['invalid'];continue;}
        $key=dnc_phone_key($phone);
        if(strlen($key)<8){++$counts['invalid'];continue;}
        if(isset($known[$key])){++$counts['duplicates'];continue;}
        $dnc=dnc_effective_flag($a,$phone,false);
        insert('INSERT INTO contacts(account_id,site_id,name,phone,dnc,created_at) VALUES (?,?,?,?,?,?)',[$a,$s,$name,$phone,$dnc,now()]);
        $known[$key]=true;++$counts['added'];
    }
    return $counts;
}
function tenant_workspace_dnc(array $b,mixed $id,bool $blocked):array{
    $row=one('SELECT id,phone FROM contacts WHERE id=? AND account_id=? AND site_id=?',[
        tenant_workspace_parse_id($id),$b['account_id'],$b['site_id']]);
    if(!$row)throw new InvalidArgumentException('contact_unavailable');
    if(!$blocked)throw new InvalidArgumentException('verified_reconsent_required');
    dnc_effective_flag((int)$b['account_id'],(string)$row['phone'],true);
    execute('UPDATE contacts SET dnc=1 WHERE id=? AND account_id=? AND site_id=?',[$row['id'],$b['account_id'],$b['site_id']]);
    return ['updated'=>true,'do_not_call'=>true];
}
function tenant_workspace_calls(array $b):array{
    $rows=all('SELECT external_call_id,phone,name,status,duration_seconds,created_at FROM calls WHERE account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=? ORDER BY id DESC LIMIT 100',
        [$b['account_id'],$b['site_id'],$b['id'],$b['sim_slot']]);
    $calls=[];foreach($rows as $r)$calls[]=[
        'call_id'=>$r['external_call_id'],'phone'=>$r['phone'],'name'=>$r['name'],
        'status'=>$r['status'],'duration_seconds'=>(int)$r['duration_seconds'],'started_at'=>$r['created_at']];
    return ['calls'=>$calls,'total'=>count($calls)];
}
function tenant_workspace_call_event(array $b,array $p):array{
    $id=$p['call_id']??null;$seq=$p['sequence']??null;$phase=$p['phase']??null;$duration=$p['duration_seconds']??0;
    if(!is_string($id)||!preg_match('/^[a-f0-9-]{36}$/D',$id)||!is_int($seq)||$seq<1||$seq>100||
       !in_array($phase,['ringing','active','missed','ended','identified'],true)||
       !is_int($duration)||$duration<0||$duration>172800)
        throw new InvalidArgumentException('invalid_call_event');
    db()->beginTransaction();
    try{
        $exists=one('SELECT sequence FROM tenant_call_events WHERE device_binding_id=? AND call_id=? AND sequence=?',[$b['id'],$id,$seq]);
        if(!$exists){
            if(!one('SELECT id FROM calls WHERE device_binding_id=? AND external_call_id=?',[$b['id'],$id]))
                insert('INSERT INTO calls(account_id,site_id,phone,name,duration_seconds,status,is_demo,created_at,external_call_id,device_binding_id,sim_slot) VALUES (?,?,?,?,?,?,1,?,?,?,?)',[
                    $b['account_id'],$b['site_id'],'','',0,'ringing',now(),$id,$b['id'],$b['sim_slot']]);
            if($phase==='identified'){
                $phone=(string)($p['phone_number']??'');$name=trim((string)($p['contact_name']??''));
                if($phone!==''&&mb_strlen($name)<=100&&preg_match('/^\\+?[0-9 ()-]{8,30}$/D',$phone))
                    execute('UPDATE calls SET phone=?,name=? WHERE device_binding_id=? AND external_call_id=?',[$phone,$name,$b['id'],$id]);
            }else{
                execute('UPDATE calls SET status=?,duration_seconds=CASE WHEN duration_seconds>? THEN duration_seconds ELSE ? END WHERE device_binding_id=? AND external_call_id=?',[
                    $phase,$duration,$duration,$b['id'],$id]);
            }
            insert('INSERT INTO tenant_call_events(device_binding_id,call_id,sequence,created_at) VALUES (?,?,?,?)',[$b['id'],$id,$seq,now()]);
        }
        db()->commit();return ['accepted'=>true,'duplicate'=>(bool)$exists];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}

function tenant_workspace_campaigns(array $b):array{
    return ['campaigns'=>all('SELECT id,name,starts_at,ends_at,status FROM tenant_campaigns WHERE account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=? ORDER BY created_at DESC LIMIT 60',
        [$b['account_id'],$b['site_id'],$b['id'],$b['sim_slot']]),'attempts'=>[]];
}
function tenant_workspace_create_campaign(array $b,array $p):array{
    if(($p['confirmed']??false)!==true)throw new InvalidArgumentException('confirmation_required');
    $name=trim((string)($p['name']??''));$starts=(string)($p['starts']??'');$ends=(string)($p['ends']??'');
    $scenario=trim((string)($p['scenario']??''));$ids=$p['contact_ids']??[];
    if($name===''||mb_strlen($name)>160||$scenario===''||mb_strlen($scenario)>8000||!is_array($ids)||count($ids)>60)
        throw new InvalidArgumentException('invalid_campaign');
    if(!preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$/D',$starts)||
       !preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$/D',$ends)||
       $ends<=$starts)throw new InvalidArgumentException('invalid_campaign_schedule');
    foreach($ids as $id){
        $uid=tenant_workspace_parse_id($id);
        if(!one('SELECT id FROM contacts WHERE id=? AND account_id=? AND site_id=?',
            [$uid,$b['account_id'],$b['site_id']]))throw new InvalidArgumentException('foreign_contact');
    }
    $id=bin2hex(random_bytes(12));
    insert('INSERT INTO tenant_campaigns(id,account_id,site_id,device_binding_id,sim_slot,name,starts_at,ends_at,scenario,contact_ids_json,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',[
        $id,$b['account_id'],$b['site_id'],$b['id'],$b['sim_slot'],$name,$starts,$ends,$scenario,json_encode($ids),'draft',now()]);
    return ['campaign'=>['id'=>$id,'name'=>$name,'status'=>'draft'],'calls_started'=>0];
}
function tenant_workspace_campaign_action(array $b,array $p):array{
    if(($p['confirmed']??false)!==true)throw new InvalidArgumentException('confirmation_required');
    $id=(string)($p['id']??'');$action=(string)($p['action']??'');
    if(!preg_match('/^[a-f0-9]{24}$/D',$id))throw new InvalidArgumentException('invalid_campaign');
    $params=[$id,$b['account_id'],$b['site_id'],$b['id'],$b['sim_slot']];
    $row=one('SELECT * FROM tenant_campaigns WHERE id=? AND account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=?',$params);
    if(!$row)throw new InvalidArgumentException('campaign_unavailable');
    if($action==='preview'){
        $ids=json_decode((string)$row['contact_ids_json'],true)?:[];
        $eligible=[];$excluded=0;
        foreach($ids as $opaque){
            $contact=one('SELECT phone,dnc FROM contacts WHERE id=? AND account_id=? AND site_id=?',
                [tenant_workspace_parse_id($opaque),$b['account_id'],$b['site_id']]);
            if(!$contact||(int)$contact['dnc']===1||
               dnc_is_suppressed((int)$b['account_id'],(string)$contact['phone'])){++$excluded;continue;}
            $eligible[]=['id'=>$opaque];
        }
        return ['preview'=>['eligible'=>$eligible,'excluded'=>$excluded,'dialed'=>false]];
    }
    // No new campaign may dial until SIM-aware consent/lease enforcement is tested.
    if(!in_array($action,['pause','cancel'],true))throw new InvalidArgumentException('campaign_dialing_not_ready');
    $status=$action==='pause'?'paused':'cancelled';
    execute('UPDATE tenant_campaigns SET status=? WHERE id=? AND account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=?',
       array_merge([$status],$params));
    return ['campaign'=>['id'=>$id,'status'=>$status],'calls_started'=>0];
}
