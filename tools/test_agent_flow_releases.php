<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/_core/FlowReleases.php';

function test_agent_flow_releases():void {
    $graph=['nodes'=>[
        ['id'=>'s','type'=>'start','text'=>'أهلاً'],
        ['id'=>'q','type'=>'ask','text'=>'هل تريد المتابعة؟'],
        ['id'=>'y','type'=>'end','text'=>'شكرًا']
    ],'edges'=>[['from'=>'s','to'=>'q'],['from'=>'q','to'=>'y','label'=>'نعم']]];
    $json=json_encode($graph,JSON_UNESCAPED_UNICODE);
    $original=one('SELECT flow_json,instructions,voice,status FROM agents WHERE id=1 AND account_id=4');
    ok((bool)$original,'release fixture belongs to direct tenant');
    execute('UPDATE agents SET flow_json=?,instructions=? WHERE id=1 AND account_id=4',
        [$json,'تعليمات النسخة الأولى']);
    $first=flow_release_publish(4,1,1,hash('sha256',$json));
    ok($first['revision']===1,'publish creates first immutable revision');
    $runningFirst=flow_release_begin(4,1,1);
    ok($runningFirst['revision']===1&&$runningFirst['waiting']===true,
        'published Flow begins at the tenant agent and pauses for customer reply');
    $pinned=flow_release_for_bound_agent(4,1,1);
    ok($pinned!==null&&$pinned['flow_json']===$json&&$pinned['instructions']==='تعليمات النسخة الأولى',
        'bound tenant and site resolve the published immutable snapshot');
    ok(flow_release_for_bound_agent(5,1,1)===null&&flow_release_for_bound_agent(4,2,1)===null,
        'other tenant and other site cannot retrieve a published agent');
    $duplicate=false;
    try{flow_release_publish(4,1,1,hash('sha256',$json));}catch(InvalidArgumentException $e){$duplicate=$e->getMessage()==='flow_publish_unchanged';}
    ok($duplicate,'unchanged draft cannot generate duplicate releases');
    $graph['nodes'][2]['text']='نسخة ثانية';
    $newJson=json_encode($graph,JSON_UNESCAPED_UNICODE);
    execute('UPDATE agents SET flow_json=?,instructions=? WHERE id=1 AND account_id=4',
        [$newJson,'تعليمات النسخة الثانية']);
    ok(flow_release_for_bound_agent(4,1,1)['flow_json']===$json,
        'editing draft never changes an in-flight pinned revision');
    $untested=false;
    try{flow_release_publish(4,1,1,hash('sha256',$json));}
    catch(InvalidArgumentException $e){$untested=$e->getMessage()==='flow_publish_test_required';}
    ok($untested,'publishing a changed draft without matching test is denied');
    $second=flow_release_publish(4,1,1,hash('sha256',$newJson));
    ok($second['revision']===2&&flow_release_current(4,1)['flow_json']===$newJson,
        'publishing a new draft activates the second revision');
    $runningSecond=flow_release_begin(4,1,1);
    $firstAnswer=flow_release_advance(4,1,1,$runningFirst['revision_id'],$runningFirst['state'],'نعم');
    $secondAnswer=flow_release_advance(4,1,1,$runningSecond['revision_id'],$runningSecond['state'],'نعم');
    ok($firstAnswer['done']&&$firstAnswer['events'][0]['text']==='شكرًا',
        'in-flight conversation remains pinned to original published revision');
    ok($secondAnswer['done']&&$secondAnswer['events'][0]['text']==='نسخة ثانية',
        'new conversation uses newly published revision');
    $wrongRuntime=false;
    try{flow_release_advance(5,1,1,$runningFirst['revision_id'],$runningFirst['state'],'نعم');}
    catch(InvalidArgumentException $e){$wrongRuntime=true;}
    ok($wrongRuntime,'published runtime rejects cross-tenant conversation continuation');
    $old=one('SELECT flow_json,instructions FROM agent_flow_revisions WHERE account_id=4 AND agent_id=1 AND revision=1');
    ok($old['flow_json']===$json&&$old['instructions']==='تعليمات النسخة الأولى',
        'published history is immutable after editing the draft');
    $restored=flow_release_rollback(4,1,1,1);
    $secondAfterRollback=flow_release_advance(4,1,1,$runningSecond['revision_id'],$runningSecond['state'],'نعم');
    ok($secondAfterRollback['done']&&$secondAfterRollback['events'][0]['text']==='نسخة ثانية',
        'rollback does not change an already-started revision-pinned conversation');
    ok((int)$restored['revision']===1&&flow_release_current(4,1)['flow_json']===$json,
        'rollback switches active pointer without overwriting history');
    ok((int)one('SELECT COUNT(*) n FROM agent_flow_revisions WHERE account_id=4 AND agent_id=1')['n']===2,
        'rollback does not create or delete a revision');
    $foreign=false;
    try{flow_release_rollback(5,1,2,1);}catch(InvalidArgumentException $e){$foreign=true;}
    ok($foreign,'cross-account rollback rejected');
    $foreignPublish=false;
    try{flow_release_publish(5,1,1,hash('sha256',$json));}catch(InvalidArgumentException $e){$foreignPublish=true;}
    ok($foreignPublish,'cross-account publish rejected');
    foreach([
        ['nodes'=>[['id'=>'s','type'=>'start'],['id'=>'x','type'=>'crm_write'],['id'=>'e','type'=>'end']],
         'edges'=>[['from'=>'s','to'=>'x'],['from'=>'x','to'=>'e']]],
        ['nodes'=>[['id'=>'s','type'=>'start'],['id'=>'e','type'=>'end']],
         'edges'=>[['from'=>'s','to'=>'s']]],
        ['nodes'=>[['id'=>'s','type'=>'start'],['id'=>'e','type'=>'end'],['id'=>'orphan','type'=>'say']],
         'edges'=>[['from'=>'s','to'=>'e']]]
    ] as $invalid){
        $denied=false;
        try{flow_release_validate($invalid);}catch(InvalidArgumentException $e){$denied=true;}
        ok($denied,'unsafe, cyclic or unreachable draft cannot be published');
    }
    execute("UPDATE agents SET status='archived' WHERE id=1 AND account_id=4");
    ok(flow_release_for_bound_agent(4,1,1)===null,'archived agent has no active published runtime');
    execute('UPDATE agents SET status=?,flow_json=?,instructions=?,voice=? WHERE id=1 AND account_id=4',
        [$original['status'],$original['flow_json'],$original['instructions'],$original['voice']]);
    echo "AGENT_FLOW_RELEASES_PASS\n";
}
