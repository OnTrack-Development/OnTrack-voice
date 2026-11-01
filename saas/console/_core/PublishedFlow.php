<?php
declare(strict_types=1);
require_once __DIR__.'/FlowPreview.php';
require_once __DIR__.'/AgentAnswerPolicy.php';
/** Immutable, tenant-scoped agent versions. Publishing does not itself start SIM audio. */
function published_agent_flow(int $accountId,int $siteId,int $agentId):?array {
    return one("SELECT v.* FROM agent_flow_versions v
        JOIN agents ag ON ag.id=v.agent_id AND ag.account_id=v.account_id
           AND ag.site_id=v.site_id AND ag.status!='archived'
        JOIN sites s ON s.id=v.site_id AND s.account_id=v.account_id AND s.status='active'
        JOIN accounts a ON a.id=v.account_id AND a.status='active'
        WHERE v.account_id=? AND v.site_id=? AND v.agent_id=?
        ORDER BY v.revision DESC LIMIT 1",[$accountId,$siteId,$agentId]);
}
function publish_agent_flow(int $accountId,int $agentId,int $actorId):array {
    $agent=one("SELECT ag.* FROM agents ag
        JOIN sites s ON s.id=ag.site_id AND s.account_id=ag.account_id AND s.status='active'
        JOIN accounts a ON a.id=ag.account_id AND a.status='active'
        WHERE ag.id=? AND ag.account_id=? AND ag.status!='archived'",[$agentId,$accountId]);
    if(!$agent)throw new InvalidArgumentException('agent_not_available');
    $raw=(string)$agent['flow_json'];
    if(strlen($raw)>16000)throw new InvalidArgumentException('flow_too_large');
    try{$graph=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}
    catch(JsonException $e){throw new InvalidArgumentException('invalid_flow');}
    if(!is_array($graph))throw new InvalidArgumentException('invalid_flow');
    flow_preview_compile($graph);
    $normalized=json_encode($graph,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $hash=hash('sha256',$normalized);
    $own=!db()->inTransaction();
    if($own)db()->beginTransaction();
    try{
        $prior=published_agent_flow($accountId,(int)$agent['site_id'],$agentId);
        $policy=json_encode(answer_policy_for_agent($accountId,$agentId),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $snapshot=hash('sha256',$normalized."\n".(string)$agent['voice']."\n".(string)$agent['instructions']."\n".(string)$agent['knowledge']."\n".$policy);
        if($prior && hash_equals($prior['snapshot_hash'],$snapshot)){
            execute("UPDATE agents SET status='published' WHERE id=? AND account_id=? AND status!='archived'",[$agentId,$accountId]);
            if($own)db()->commit();
            return ['revision'=>(int)$prior['revision'],'duplicate'=>true,'hash'=>$hash];
        }
        $revision=$prior?(int)$prior['revision']+1:1;
        insert('INSERT INTO agent_flow_versions(account_id,site_id,agent_id,revision,flow_hash,snapshot_hash,flow_json,voice,instructions,knowledge,published_by,published_at,answer_policy_json)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[$accountId,$agent['site_id'],$agentId,$revision,
            $hash,$snapshot,$normalized,(string)$agent['voice'],(string)$agent['instructions'],
            (string)$agent['knowledge'],$actorId,now(),$policy]);
        execute("UPDATE agents SET status='published' WHERE id=? AND account_id=? AND status!='archived'",
            [$agentId,$accountId]);
        if($own)db()->commit();
        return ['revision'=>$revision,'duplicate'=>false,'hash'=>$hash];
    }catch(Throwable $e){if($own&&db()->inTransaction())db()->rollBack();throw $e;}
}
