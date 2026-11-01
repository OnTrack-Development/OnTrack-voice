<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/_core/PublishedFlow.php';
function test_published_agent_flow():void {
    $agent=one("SELECT id,account_id,site_id,flow_json,status,instructions FROM agents WHERE account_id=4 AND site_id=1 ORDER BY id LIMIT 1");
    ok($agent!==null,'seed agent exists for flow tests');
    $id=(int)$agent['id'];$account=(int)$agent['account_id'];$site=(int)$agent['site_id'];
    $first=publish_agent_flow($account,$id,1);
    ok($first['revision']===1&&!$first['duplicate'],'first immutable publication');
    $record=published_agent_flow($account,$site,$id);
    ok($record!==null && $record['revision']==1 && $record['flow_hash']===$first['hash'],'published version scoped');
    // The Android device profile must reflect the published version, never a later draft.
    $binding=one("SELECT * FROM device_bindings WHERE account_id=? AND agent_id=? AND status='active' ORDER BY id DESC LIMIT 1",[$account,$id]);
    ok($binding!==null,'paired SIM for published-flow regression');
    $profile=device_binding_profile($binding);
    ok($profile['flow_ready']===true && $profile['flow_revision']===1 &&
       $profile['instruction']===$record['instructions'] && $profile['voice']===$record['voice'],
       'SIM 1 receives the published voice and instruction snapshot');

    $again=publish_agent_flow($account,$id,1);
    ok($again['duplicate']===true && $again['revision']===1,'idempotent unchanged publish');
    ok(published_agent_flow(5,$site,$id)===null,'foreign tenant cannot read published agent');
    $forbidden=false;
    try{publish_agent_flow(5,$id,1);}catch(InvalidArgumentException $e){$forbidden=true;}
    ok($forbidden,'foreign tenant cannot publish agent');
    $old=(string)$agent['flow_json'];
    $new=json_decode($old,true);$new['nodes'][0]['text']='Published change';
    execute('UPDATE agents SET flow_json=?,status=? WHERE id=?',[
        json_encode($new,JSON_UNESCAPED_UNICODE),'draft',$id]);
    try {
        $before=published_agent_flow($account,$site,$id);
        ok($before['flow_json']!==json_encode($new,JSON_UNESCAPED_UNICODE),
            'draft edits never mutate published version');
        execute("UPDATE agents SET instructions=? WHERE id=?",['MALICIOUS_DRAFT_INSTRUCTIONS',$id]);
        $stale=device_binding_profile($binding);
        ok($stale['instruction']===$before['instructions'] &&
           $stale['instruction']!=='MALICIOUS_DRAFT_INSTRUCTIONS',
           'draft instructions do not leak through live SIM heartbeat');

        $second=publish_agent_flow($account,$id,1);
        ok($second['revision']===2&&!$second['duplicate'],'new revision on content change');
    }finally{
        execute('UPDATE agents SET flow_json=?,status=?,instructions=? WHERE id=?',[$old,$agent['status'],$agent['instructions'],$id]);
    }
    $second=published_agent_flow($account,$site,$id);
    ok((int)$second['revision']===2 && $second['flow_hash']!==$first['hash'],'versions isolated');
    echo "AGENT_PUBLISHED_FLOW_PASS\n";
}
