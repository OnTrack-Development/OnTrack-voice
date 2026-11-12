<?php
declare(strict_types=1);
function voice_usage_init():void{
  $mark=private_path().'/.schema.voice-call-answered.v1';
  if(is_file($mark))return;
  $int=driver()==='mysql'?'BIGINT':'INTEGER';
  $engine=driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
  db()->exec("CREATE TABLE IF NOT EXISTS voice_call_answer_evidence(
    account_id $int NOT NULL,site_id $int NOT NULL,device_binding_id $int NOT NULL,
    sim_slot INTEGER NOT NULL,call_id VARCHAR(36) NOT NULL,observed_at VARCHAR(19) NOT NULL,
    PRIMARY KEY(device_binding_id,call_id))".$engine);
  file_put_contents($mark,now(),LOCK_EX);@chmod($mark,0600);
}
function voice_usage_update(array $b,string $callId,string $phase):bool{
  if(!in_array($phase,['active','ended'],true))return false;
  voice_usage_init();
  $a=(int)$b['account_id'];$s=(int)$b['site_id'];$d=(int)$b['id'];$slot=(int)$b['sim_slot'];
  if($phase==='active'){
    $evidence=one('SELECT account_id,site_id,sim_slot FROM voice_call_answer_evidence
      WHERE device_binding_id=? AND call_id=?',[$d,$callId]);
    if(!$evidence){
      try{
        insert('INSERT INTO voice_call_answer_evidence(account_id,site_id,device_binding_id,sim_slot,call_id,observed_at)
          VALUES (?,?,?,?,?,?)',[$a,$s,$d,$slot,$callId,now()]);
      }catch(PDOException $ex){
        // Concurrent Android callbacks may report the same active call.
        // Only an identical already-inserted scope is an idempotent retry.
        $evidence=one('SELECT account_id,site_id,sim_slot FROM voice_call_answer_evidence
          WHERE device_binding_id=? AND call_id=?',[$d,$callId]);
        if(!$evidence)throw $ex;
      }
    }
    if($evidence && ((int)$evidence['account_id']!==$a||
       (int)$evidence['site_id']!==$s||(int)$evidence['sim_slot']!==$slot))
      throw new RuntimeException('voice_usage_evidence_scope_mismatch');
  }
  $row=one('SELECT status,duration_seconds,is_demo FROM calls
    WHERE account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=? AND external_call_id=?',
    [$a,$s,$d,$slot,$callId]);
  if(!$row||$row['status']!=='ended'||(int)$row['duration_seconds']<1||(int)$row['is_demo']===1)return false;
  if(!one('SELECT call_id FROM voice_call_answer_evidence
    WHERE account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=? AND call_id=?',
    [$a,$s,$d,$slot,$callId]))return false;
  $ref='voice-'.hash('sha256',$a.':'.$s.':'.$d.':'.$slot.':'.$callId);
  if(one('SELECT id FROM usage_ledger WHERE reference=?',[$ref]))return false;
  try{insert('INSERT INTO usage_ledger(account_id,kind,units,reference,created_at)
       VALUES(?,?,?,?,?)',[$a,'connected_call',1,$ref,now()]);return true;}
  catch(PDOException $ex){
    if(one('SELECT id FROM usage_ledger WHERE reference=?',[$ref]))return false;
    throw $ex;
  }
}
function voice_usage_summary(int $account):array{
  $sub=one("SELECT s.starts_at,s.ends_at,p.call_quota,p.overage_minor
    FROM subscriptions s JOIN plans p ON p.id=s.plan_id
    WHERE s.account_id=? AND s.status='active' AND s.starts_at<=? AND s.ends_at>?
    ORDER BY s.id DESC LIMIT 1",[$account,now(),now()]);
  if(!$sub)return ['active'=>false,'used'=>0,'quota'=>0,'remaining'=>0,'overage'=>0,'estimate_minor'=>0];
  $used=(int)(one("SELECT COALESCE(SUM(units),0) n FROM usage_ledger
    WHERE account_id=? AND kind='connected_call' AND created_at>=? AND created_at<?",
    [$account,$sub['starts_at'],$sub['ends_at']])['n']??0);
  $quota=max(0,(int)$sub['call_quota']);$over=max(0,$used-$quota);
  return ['active'=>true,'used'=>$used,'quota'=>$quota,'remaining'=>max(0,$quota-$used),
    'overage'=>$over,'estimate_minor'=>$over*max(0,(int)$sub['overage_minor'])];
}
