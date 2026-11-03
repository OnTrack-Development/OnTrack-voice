<?php
declare(strict_types=1);

/**
 * Private, append-only report entries keyed to a real observed pilot call.
 * Never fabricate a caller, a transcript or a satisfaction result.
 */
function ontrack_report_read(string $callId, string $store): array {
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $callId)) {
        throw new InvalidArgumentException('invalid_call_id');
    }
    $path=$store.'/reports/'.$callId.'.json';
    $data=is_file($path)?json_decode((string)file_get_contents($path),true):null;
    return is_array($data)&&is_array($data['entries']??null)?$data:['call_id'=>$callId,'entries'=>[]];
}

function ontrack_report_append(array $event,string $store,string $actor): array {
    $id=(string)($event['call_id']??'');
    if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$id))throw new InvalidArgumentException('invalid_call_id');
    $entryId=(string)($event['entry_id']??'');
    if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$entryId))throw new InvalidArgumentException('invalid_entry_id');
    $kind=(string)($event['kind']??'');
    $allowed=$actor==='device'?['transcript','handoff','finalize']:($actor==='ai'?['summary']:['customer_note','handoff','summary','disposition']);
    if(!in_array($kind,$allowed,true))throw new InvalidArgumentException('invalid_report_kind');
    $role=(string)($event['role']??'system');
    if(!in_array($role,['caller','agent','system','employee'],true))throw new InvalidArgumentException('invalid_role');
    if($kind==='transcript'&&!in_array($role,['caller','agent'],true))throw new InvalidArgumentException('invalid_transcript_role');
    if($kind==='finalize'&&($actor!=='device'||$role!=='system'))throw new InvalidArgumentException('invalid_finalization');
    if($actor==='ai'&&$role!=='agent')throw new InvalidArgumentException('invalid_ai_role');
    $value=trim((string)($event['text']??''));
    if($value===''||mb_strlen($value,'UTF-8')>1500||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$value))throw new InvalidArgumentException('invalid_report_text');
    // A device may only append model-observed text or a factual handoff event.
    if($actor==='device' && $kind==='handoff' && $role!=='system')throw new InvalidArgumentException('invalid_handoff_role');
    $call=$store.'/'.$id.'.json';
    if(!is_file($call))throw new InvalidArgumentException('call_not_found');
    $directory=$store.'/reports';
    if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('report_store_unavailable');
    $lock=fopen($directory.'/.reports.lock','c');
    if($lock===false||!flock($lock,LOCK_EX))throw new RuntimeException('report_store_locked');
    try{
        // Recheck while holding the lock, because other requests may arrive together.
        if(!is_file($call))throw new InvalidArgumentException('call_not_found');
        $data=ontrack_report_read($id,$store);
        foreach($data['entries'] as $old){
            if(($old['entry_id']??null)===$entryId)return ['ok'=>true,'duplicate'=>true,'call_id'=>$id];
        }
        if(count($data['entries'])>=350)throw new InvalidArgumentException('report_entry_limit');
        $data['entries'][]=[
            'entry_id'=>$entryId,'kind'=>$kind,'role'=>$role,'text'=>$value,
            'created_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
            'source'=>$actor
        ];
        $target=$directory.'/'.$id.'.json';
        $temp=tempnam($directory,'.report-');
        if($temp===false)throw new RuntimeException('report_temp_unavailable');
        try{
            $encoded=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            if(file_put_contents($temp,$encoded,LOCK_EX)===false)throw new RuntimeException('report_write_failed');
            chmod($temp,0600);
            if(!rename($temp,$target))throw new RuntimeException('report_commit_failed');
        }finally{if(is_file($temp))unlink($temp);}
        return ['ok'=>true,'duplicate'=>false,'call_id'=>$id];
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
