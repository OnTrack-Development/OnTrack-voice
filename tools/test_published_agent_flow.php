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
    $policy=answer_policy_normalize(['auto_answer'=>true,'answer_delay_seconds'=>8,
        'response_style'=>'formal_ar','max_call_minutes'=>12,'fallback'=>'human']);
    $beforePolicy=published_agent_flow($account,$site,$id);
    save_answer_policy($account,$id,$policy);
    $stillPublished=published_agent_flow($account,$site,$id);
    ok(($stillPublished['answer_policy_json']??'')===($beforePolicy['answer_policy_json']??''),
        'draft answer policy never changes published SIM snapshot');
    $newRevision=publish_agent_flow($account,$id,1);
    ok($newRevision['revision']===3,'answering policy change triggers immutable new revision');
    $live=published_agent_flow($account,$site,$id);
    $actual=answer_policy_normalize(json_decode((string)$live['answer_policy_json'],true));
    ok($actual['auto_answer']===true && $actual['answer_delay_seconds']===8,
        'published SIM receives current per-agent answer policy');
    require_once dirname(__DIR__).'/saas/console/_core/TenantVoiceSession.php';
    $definition=tenant_voice_definition($binding);
    ok($definition['revision']===3 &&
       $definition['account_id']===$account &&
       $definition['site_id']===$site &&
       $definition['policy']['answer_delay_seconds']===8,
       'new voice session uses published tenant and immutable answer policy');
    $otherDenied=false;
    try{tenant_voice_definition(['account_id'=>5,'site_id'=>$site,'agent_id'=>$id,'sim_slot'=>1]);}
    catch(InvalidArgumentException $e){$otherDenied=true;}
    ok($otherDenied,'new voice engine cannot use a foreign tenant');
    // Never mint a live provider token in an offline test. A missing provider
    // must fail clearly rather than falling back to the legacy pilot bearer.
    echo "TENANT_VOICE_SESSION_CONTRACT_PASS\n";
    echo "AGENT_PUBLISHED_FLOW_PASS\n";
}
