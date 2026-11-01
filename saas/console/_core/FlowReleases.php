<?php
declare(strict_types=1);
require_once __DIR__.'/FlowPreview.php';

/**
 * Immutable, tenant-scoped Flow releases. These snapshots are control-plane
 * data only: publishing never starts a phone call or enables a SIM bridge.
 */
function flow_release_validate(array $graph):void {
    $compiled=flow_preview_compile($graph);
    $nodes=$compiled['nodes'];$edges=$compiled['edges'];
    $safe=['start','say','listen','ask','condition','collect_field','end'];
    $visiting=[];$visited=[];$ends=0;
    $walk=function(string $id) use (&$walk,&$visiting,&$visited,&$ends,$nodes,$edges,$safe):void {
        if(isset($visiting[$id]))throw new InvalidArgumentException('flow_publish_cycle');
        if(isset($visited[$id]))return;
        $node=$nodes[$id];
        if(!in_array($node['type'],$safe,true))
            throw new InvalidArgumentException('flow_publish_external_action_unavailable');
        $out=$edges[$id]??[];
        if($node['type']==='end'){
            if($out)throw new InvalidArgumentException('flow_publish_end_has_edges');
            $ends++;$visited[$id]=true;return;
        }
        if(!$out)throw new InvalidArgumentException('flow_publish_dead_end');
        $visiting[$id]=true;
        foreach($out as $edge)$walk($edge['to']);
        unset($visiting[$id]);$visited[$id]=true;
    };
    $walk($compiled['start']);
    if($ends<1||count($visited)!==count($nodes))
        throw new InvalidArgumentException('flow_publish_unreachable_node');
}
function flow_release_agent(int $accountId,int $agentId,bool $lock=false):?array {
    $query="SELECT ag.* FROM agents ag
      JOIN accounts a ON a.id=ag.account_id AND a.status='active'
      JOIN sites s ON s.id=ag.site_id AND s.account_id=ag.account_id AND s.status='active'
      WHERE ag.id=? AND ag.account_id=? AND ag.status!='archived'";
    if($lock&&driver()==='mysql')$query.=' FOR UPDATE';
    return one($query,[$agentId,$accountId]);
}
/** Persist a new immutable snapshot of the SAVED draft and atomically activate it. */
function flow_release_publish(int $accountId,int $agentId,int $actorId,string $testedHash):array {
    db()->beginTransaction();
    try {
        $agent=flow_release_agent($accountId,$agentId,true);
        if(!$agent)throw new InvalidArgumentException('flow_agent_not_available');
        $json=(string)$agent['flow_json'];
        if(strlen($json)>16000)throw new InvalidArgumentException('flow_publish_too_large');
        $graph=json_decode($json,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($graph))throw new InvalidArgumentException('flow_invalid_graph');
        flow_release_validate($graph);
        $hash=hash('sha256',$json);
        if(!preg_match('/^[a-f0-9]{64}$/D',$testedHash)||!hash_equals($hash,$testedHash))
            throw new InvalidArgumentException('flow_publish_test_required');
        $current=flow_release_current($accountId,$agentId);
        if($current&&hash_equals($current['graph_hash'],$hash)
            &&$current['instructions']===(string)$agent['instructions']
            &&$current['voice']===(string)$agent['voice'])
            throw new InvalidArgumentException('flow_publish_unchanged');
        $next=(int)(one('SELECT MAX(revision) AS n FROM agent_flow_revisions WHERE account_id=? AND agent_id=?',
            [$accountId,$agentId])['n']??0)+1;
        $revisionId=insert('INSERT INTO agent_flow_revisions
            (account_id,site_id,agent_id,revision,graph_hash,flow_json,voice,instructions,created_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$accountId,$agent['site_id'],$agentId,$next,$hash,$json,
             (string)$agent['voice'],(string)$agent['instructions'],$actorId,now()]);
        if($current){
            execute('UPDATE agent_flow_publications SET revision_id=?,updated_at=? WHERE account_id=? AND agent_id=?',
                [$revisionId,now(),$accountId,$agentId]);
        }else{
            insert('INSERT INTO agent_flow_publications(account_id,agent_id,revision_id,updated_at) VALUES (?,?,?,?)',
                [$accountId,$agentId,$revisionId,now()]);
        }
        db()->commit();
        return ['id'=>$revisionId,'revision'=>$next,'graph_hash'=>$hash];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
/** Change only the active pointer; history and original snapshots stay immutable. */
function flow_release_rollback(int $accountId,int $agentId,int $revision,int $actorId):array {
    db()->beginTransaction();
    try{
        if(!flow_release_agent($accountId,$agentId,true))
            throw new InvalidArgumentException('flow_agent_not_available');
        $target=one('SELECT id,revision,graph_hash FROM agent_flow_revisions WHERE account_id=? AND agent_id=? AND revision=?',
            [$accountId,$agentId,$revision]);
        if(!$target)throw new InvalidArgumentException('flow_revision_not_found');
        $current=flow_release_current($accountId,$agentId);
        if(!$current||$current['id']===$target['id'])
            throw new InvalidArgumentException('flow_revision_already_active');
        execute('UPDATE agent_flow_publications SET revision_id=?,updated_at=? WHERE account_id=? AND agent_id=?',
            [$target['id'],now(),$accountId,$agentId]);
        db()->commit();
        return $target;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
/** A call runtime can pin this snapshot at call start; never use a mutable draft. */
function flow_release_current(int $accountId,int $agentId):?array {
    return one('SELECT r.* FROM agent_flow_publications p
        JOIN agent_flow_revisions r ON r.id=p.revision_id AND r.account_id=p.account_id AND r.agent_id=p.agent_id
        WHERE p.account_id=? AND p.agent_id=?',[$accountId,$agentId]);
}
function flow_release_for_bound_agent(int $accountId,int $siteId,int $agentId):?array {
    $agent=flow_release_agent($accountId,$agentId);
    if(!$agent||(int)$agent['site_id']!==$siteId)return null;
    $current=flow_release_current($accountId,$agentId);
    if(!$current||(int)$current['site_id']!==$siteId)return null;
    return $current;
}
