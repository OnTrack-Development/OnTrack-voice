<?php
declare(strict_types=1);

/** Pilot call ledger; private files only, never put caller records in Git. */
function ontrack_call_event(array $event, string $store): array {
    $id=(string)($event['call_id']??'');
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$id)) {
        throw new InvalidArgumentException('invalid_call_id');
    }
    $sequence=filter_var($event['sequence']??null,FILTER_VALIDATE_INT);
    $phase=(string)($event['phase']??'');
    if ($sequence===false || $sequence<1 || $sequence>100000 || !in_array($phase,['ringing','active','ended','missed'],true)) {
        throw new InvalidArgumentException('invalid_call_event');
    }
    $duration=filter_var($event['duration_seconds']??0,FILTER_VALIDATE_INT);
    if ($duration===false || $duration<0 || $duration>172800) throw new InvalidArgumentException('invalid_duration');
    if (!is_dir($store) && !mkdir($store,0700,true) && !is_dir($store)) throw new RuntimeException('store_unavailable');
    $lock=fopen($store.'/.ledger.lock','c');
    if ($lock===false || !flock($lock,LOCK_EX)) throw new RuntimeException('store_locked');
    try {
        $path=$store.'/'.$id.'.json';
        $existing=is_file($path)?json_decode((string)file_get_contents($path),true):null;
        $record=is_array($existing)?$existing:null;
        if ($record!==null && $sequence<=(int)($record['last_sequence']??0)) {
            return ['ok'=>true,'duplicate'=>true,'call_id'=>$id,'phase'=>$record['phase']];
        }
        if ($record!==null && in_array($record['phase']??'', ['ended','missed'],true)) {
            throw new InvalidArgumentException('call_already_ended');
        }
        $now=gmdate('Y-m-d\TH:i:s\Z');
        $record ??=[
            'call_id'=>$id,
            'direction'=>'unknown',
            'contact_name'=>null,
            'phone_number'=>null,
            'agent_name'=>null,
            'campaign_id'=>null,
            'started_at_utc'=>$now,
            'answered_at_utc'=>null,
            'ended_at_utc'=>null,
            'duration_seconds'=>null,
            'summary_status'=>'not_generated',
            'last_sequence'=>0,
            'phase'=>'unknown'
        ];
        if ($phase==='active' && $record['answered_at_utc']===null) $record['answered_at_utc']=$now;
        if ($phase==='ended' || $phase==='missed') {
            $record['ended_at_utc']=$now;
            $record['duration_seconds']=$duration;
        }
        $record['phase']=$phase;
        $record['last_sequence']=$sequence;
        $record['updated_at_utc']=$now;
        $json=json_encode($record,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $tmp=tempnam($store,'.call-');
        if ($tmp===false) throw new RuntimeException('store_temp');
        try {
            if (file_put_contents($tmp,$json,LOCK_EX)===false) throw new RuntimeException('store_write');
            chmod($tmp,0600);
            if (!rename($tmp,$path)) throw new RuntimeException('store_commit');
        }finally{if(is_file($tmp))unlink($tmp);}
        return ['ok'=>true,'duplicate'=>false,'call_id'=>$id,'phase'=>$phase];
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}

function ontrack_call_list(string $store,int $limit=100): array {
    if (!is_dir($store)) return [];
    $items=[];
    foreach (glob($store.'/*.json')?:[] as $path) {
        $data=json_decode((string)@file_get_contents($path),true);
        if (!is_array($data) || !isset($data['call_id'],$data['started_at_utc']))continue;
        $items[]=$data;
    }
    usort($items,static fn(array $a,array $b):int=>
        strcmp((string)($b['started_at_utc']??''),(string)($a['started_at_utc']??'')));
    return array_slice($items,0,max(1,min($limit,200)));
}
