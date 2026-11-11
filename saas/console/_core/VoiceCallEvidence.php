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
/** Reject changed evidence under the same event ID, including concurrent retries. */
function voice_call_timeline_assert_same_event(array $prev,array $scope,string $speaker,
    string $event,string $content,string $at):void {
    if((int)$prev['account_id']!==$scope[0]||
       (int)$prev['site_id']!==$scope[1]||
       (int)$prev['sim_slot']!==$scope[3]||
       $prev['speaker']!==$speaker||
       $prev['event_type']!==$event||
       $prev['content']!==$content||
       $prev['occurred_at']!==$at)
        throw new RuntimeException('timeline_replay_changed');
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
    $at=(string)($payload['occurred_at']??'');
    if(!preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D',$at))
        throw new InvalidArgumentException('timeline_timestamp_invalid');
    $prev=one('SELECT account_id,site_id,sim_slot,speaker,event_type,content,occurred_at
      FROM voice_call_timeline WHERE device_binding_id=? AND call_id=? AND event_id=?',
      [(int)$binding['id'],$callId,$eventId]);
    if($prev){
        // An event_id is an immutable evidence record, including when clients
        // retry the same update after a network timeout.
        voice_call_timeline_assert_same_event($prev,$scope,$speaker,$event,trim($content),$at);
        return ['saved'=>true,'duplicate'=>true];
    }
    // Reject impossible UTC dates and 24:00 rollovers rather than silently
    // normalizing device-reported evidence to another moment.
    $stamp=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$at,new DateTimeZone('UTC'));
    if(!$stamp||$stamp->format('Y-m-d H:i:s')!==$at||
       abs(time()-$stamp->getTimestamp())>24*3600)
        throw new InvalidArgumentException('timeline_timestamp_invalid');
    try {
        insert("INSERT INTO voice_call_timeline
          (account_id,site_id,device_binding_id,sim_slot,call_id,event_id,speaker,event_type,
           content,origin,occurred_at,received_at) VALUES (?,?,?,?,?,?,?,?,?,'device_reported',?,?)",
          [...$scope,$eventId,$speaker,$event,trim($content),$at,now()]);
    } catch (PDOException $error) {
        // A second authenticated report can race between SELECT and INSERT.
        // Only the same unique (device,call,event) with identical content is
        // idempotent; a changed replay remains an integrity error.
        if(!in_array((string)$error->getCode(),['23000','19'],true)) throw $error;
        $duplicate=one('SELECT account_id,site_id,sim_slot,speaker,event_type,content,occurred_at
          FROM voice_call_timeline WHERE device_binding_id=? AND call_id=? AND event_id=?',
          [(int)$binding['id'],$callId,$eventId]);
        if(!$duplicate) throw $error;
        voice_call_timeline_assert_same_event($duplicate,$scope,$speaker,$event,trim($content),$at);
        return ['saved'=>true,'duplicate'=>true];
    }
    return ['saved'=>true,'duplicate'=>false];
}

/**
 * Fold adjacent utterance chunks only for presentation, never mutate the
 * append-only device event ledger. Bound each group and preserve the event IDs
 * and timestamps for an operator to audit the original evidence.
 */
function voice_call_group_transcript_events(array $messages):array {
    $grouped=[];
    foreach($messages as $event){
        if(!is_array($event))continue;
        $speaker=(string)($event['speaker']??'unknown');
        $kind=(string)($event['event_type']??'notice');
        $origin=(string)($event['origin']??'device_reported');
        $content=trim((string)($event['content']??''));
        $when=(string)($event['occurred_at']??'');
        if($content==='')continue;
        $eventId=(string)($event['event_id']??'');
        $last=count($grouped)-1;
        $prior=$last>=0?$grouped[$last]:null;
        $previousAt=$prior?strtotime($prior['last_at'].' UTC'):false;
        $nowAt=strtotime($when.' UTC');
        $same=$prior&&$kind==='transcript'&&$prior['event_type']==='transcript'&&
            $prior['speaker']===$speaker&&$prior['origin']===$origin&&
            $nowAt!==false&&$previousAt!==false&&$nowAt>=$previousAt&&
            $nowAt-$previousAt<=20&&$prior['event_count']<8&&
            mb_strlen($prior['content'])+mb_strlen($content)<4000;
        if($same){
            $grouped[$last]['content'].="\n".$content;
            $grouped[$last]['last_at']=$when;
            $grouped[$last]['event_count']++;
            $grouped[$last]['event_ids'][]=$eventId;
        }else{
            $grouped[]=['speaker'=>$speaker,'event_type'=>$kind,'origin'=>$origin,
                'content'=>$content,'first_at'=>$when,'last_at'=>$when,
                'event_count'=>1,'event_ids'=>[$eventId]];
        }
    }
    return $grouped;
}

function voice_call_evidence(int $accountId,int $siteId,int $bindingId,int $slot,string $callId):array {
    voice_call_timeline_init();
    $call=one('SELECT id FROM calls WHERE account_id=? AND site_id=? AND device_binding_id=?
       AND sim_slot=? AND external_call_id=?',[$accountId,$siteId,$bindingId,$slot,$callId]);
    if(!$call)throw new InvalidArgumentException('timeline_call_not_owned');
    $messages=all('SELECT event_id,speaker,event_type,content,origin,occurred_at,received_at
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
    // Matched, verified provider references are distinct from transient tool
    // traces and never inferred from the spoken transcript.
    $references=[];
    if(function_exists('voice_tools_init')){
        $references=all("SELECT operation,reference_id,status,completed_at
           FROM voice_tool_requests
           WHERE account_id=? AND site_id=? AND call_id=? AND status='succeeded'
             AND reference_id IS NOT NULL AND reference_id!=''
           ORDER BY id DESC LIMIT 30",[$accountId,$siteId,$callId]);
    }
    return ['transcript_events'=>$messages,
        'grouped_transcript'=>voice_call_group_transcript_events($messages),
        'tool_traces'=>$tools,'verified_references'=>$references];
}
