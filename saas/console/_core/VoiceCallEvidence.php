<?php
declare(strict_types=1);
/** Report only device-supplied utterances. Not proof of a recording or ASR accuracy. */
function voice_call_timeline_init():void {
    $marker=private_path().'/.schema.voice-call-timeline-v1';
    if(is_file($marker))return;
    $mysql=driver()==='mysql';
    $pk=$mysql?'BIGINT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $int=$mysql?'BIGINT':'INTEGER';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS voice_call_timeline (
        id $pk,account_id $int NOT NULL,site_id $int NOT NULL,
        device_binding_id $int NOT NULL,sim_slot INTEGER NOT NULL,
        call_id VARCHAR(64) NOT NULL,event_id VARCHAR(64) NOT NULL,
        speaker VARCHAR(16) NOT NULL,event_type VARCHAR(20) NOT NULL,
        content TEXT NOT NULL,origin VARCHAR(24) NOT NULL DEFAULT 'device_reported',
        occurred_at VARCHAR(19) NOT NULL,received_at VARCHAR(19) NOT NULL,
        UNIQUE(device_binding_id,call_id,event_id))".$engine);
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}
function voice_call_timeline_store(array $binding,array $payload):array {
    voice_call_timeline_init();
    $callId=(string)($payload['call_id']??'');
    $eventId=(string)($payload['event_id']??'');
    $speaker=(string)($payload['speaker']??'');
    $event=(string)($payload['event_type']??'');
    $content=$payload['text']??null;
    if(!preg_match('/^[a-f0-9-]{36}$/D',$callId)||
       !preg_match('/^[A-Za-z0-9_-]{8,64}$/D',$eventId)||
       !in_array($speaker,['caller','agent','unknown'],true)||
       !in_array($event,['transcript','spoken_text','notice'],true)||
       !is_string($content)||trim($content)===''||mb_strlen($content)>1200||
       !mb_check_encoding($content,'UTF-8'))
        throw new InvalidArgumentException('timeline_event_invalid');
    if($event==='spoken_text'&&$speaker!=='agent')
        throw new InvalidArgumentException('timeline_spoken_text_must_be_agent');
    $scope=[(int)$binding['account_id'],(int)$binding['site_id'],
       (int)$binding['id'],(int)$binding['sim_slot'],$callId];
    $call=one("SELECT id FROM calls WHERE account_id=? AND site_id=?
      AND device_binding_id=? AND sim_slot=? AND external_call_id=?",$scope);
    if(!$call)throw new InvalidArgumentException('timeline_call_not_owned');
    $prev=one('SELECT id,content FROM voice_call_timeline
      WHERE device_binding_id=? AND call_id=? AND event_id=?',
      [(int)$binding['id'],$callId,$eventId]);
    if($prev){
        if($prev['content']!==trim($content))throw new RuntimeException('timeline_replay_changed');
        return ['saved'=>true,'duplicate'=>true];
    }
    $at=(string)($payload['occurred_at']??'');
    $ts=strtotime($at.' UTC');
    if(!preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D',$at)||
        $ts===false||abs(time()-$ts)>24*3600)
        throw new InvalidArgumentException('timeline_timestamp_invalid');
    insert("INSERT INTO voice_call_timeline
      (account_id,site_id,device_binding_id,sim_slot,call_id,event_id,speaker,event_type,
       content,origin,occurred_at,received_at) VALUES (?,?,?,?,?,?,?,?,?,'device_reported',?,?)",
      [...$scope,$eventId,$speaker,$event,trim($content),$at,now()]);
    return ['saved'=>true,'duplicate'=>false];
}
function voice_call_evidence(int $accountId,int $siteId,int $bindingId,int $slot,string $callId):array {
    voice_call_timeline_init();
    $call=one('SELECT id FROM calls WHERE account_id=? AND site_id=? AND device_binding_id=?
       AND sim_slot=? AND external_call_id=?',[$accountId,$siteId,$bindingId,$slot,$callId]);
    if(!$call)throw new InvalidArgumentException('timeline_call_not_owned');
    $messages=all('SELECT speaker,event_type,content,origin,occurred_at,received_at
       FROM voice_call_timeline WHERE account_id=? AND site_id=?
       AND device_binding_id=? AND sim_slot=? AND call_id=?
       ORDER BY occurred_at,id LIMIT 300',[$accountId,$siteId,$bindingId,$slot,$callId]);
    $tools=[];
    if(function_exists('voice_tools_init')){
        voice_tools_init();
        $tools=all('SELECT operation,status,detail,created_at FROM voice_tool_traces
          WHERE account_id=? AND site_id=? AND call_id=? ORDER BY id LIMIT 100',
          [$accountId,$siteId,$callId]);
    }
    return ['transcript_events'=>$messages,'tool_traces'=>$tools];
}
