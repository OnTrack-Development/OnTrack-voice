<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/_core/FlowPreview.php';

function test_agent_flow_preview():void {
    $graph=['nodes'=>[
        ['id'=>'start','type'=>'start','text'=>'أهلًا'],
        ['id'=>'say','type'=>'say','text'=>'من OnTrack'],
        ['id'=>'ask','type'=>'ask','text'=>'هل ترغب في الحجز؟'],
        ['id'=>'yes','type'=>'say','text'=>'تم اختيار مسار الحجز التجريبي'],
        ['id'=>'no','type'=>'end','text'=>'شكرًا'],
        ['id'=>'end','type'=>'end','text'=>'انتهى']
    ],'edges'=>[
        ['from'=>'start','to'=>'say'],['from'=>'say','to'=>'ask'],
        ['from'=>'ask','to'=>'yes','label'=>'نعم'],
        ['from'=>'ask','to'=>'no','label'=>'لا'],
        ['from'=>'yes','to'=>'end']
    ]];
    $compiled=flow_preview_compile($graph);
    $started=flow_preview_step($compiled,flow_preview_initial($compiled));
    ok($started['waiting']===true&&!$started['done']&&count($started['events'])===3,
        'text preview traverses start, say and pauses on customer question');
    ok($started['events'][2]['text']==='هل ترغب في الحجز؟','preview emits real graph question');
    $unknown=flow_preview_step($compiled,$started['state'],'مش عارف');
    ok($unknown['waiting']===true&&$unknown['state']['cursor']==='ask',
        'unmatched answer cannot guess a customer branch');
    $yes=flow_preview_step($compiled,$unknown['state'],'نعم');
    ok($yes['done']===true&&!$yes['waiting']&&$yes['events'][0]['text']==='تم اختيار مسار الحجز التجريبي',
        'yes branch traverses to the correct end');
    $no=flow_preview_step($compiled,$started['state'],'لا');
    ok($no['done']===true&&$no['events'][0]['text']==='شكرًا',
        'no branch never leaks the yes path');
    $done=flow_preview_step($compiled,$no['state']);
    ok($done['events']===[]&&$done['done'],'completed preview cannot execute twice');
    $empty=flow_preview_step($compiled,$started['state'],' ');
    ok($empty['waiting']&&$empty['state']['turns']===0,'blank answer does not advance graph');
    $invalid=false;
    try{flow_preview_step($compiled,$started['state'],str_repeat('x',501));}
    catch(InvalidArgumentException $e){$invalid=true;}
    ok($invalid,'oversized customer input rejected');
    $conditional=['nodes'=>[
        ['id'=>'s','type'=>'start','text'=>''],
        ['id'=>'a','type'=>'ask','text'=>'اختر نعم أو لا'],
        ['id'=>'c','type'=>'condition','text'=>''],
        ['id'=>'y','type'=>'end','text'=>'نعم'],
        ['id'=>'n','type'=>'end','text'=>'لا']
    ],'edges'=>[
        ['from'=>'s','to'=>'a'],['from'=>'a','to'=>'c'],
        ['from'=>'c','to'=>'y','label'=>'نعم'],
        ['from'=>'c','to'=>'n','label'=>'لا']
    ]];
    $flow=flow_preview_compile($conditional);
    $start=flow_preview_step($flow,flow_preview_initial($flow));
    $branch=flow_preview_step($flow,$start['state'],'نعم');
    ok($branch['done']&&$branch['events'][0]['text']==='نعم',
        'condition node uses last customer reply without AI guessing');
    $collect=['nodes'=>[
        ['id'=>'s','type'=>'start'],['id'=>'field','type'=>'collect_field','text'=>'الاسم؟'],
        ['id'=>'e','type'=>'end','text'=>'تم']
    ],'edges'=>[['from'=>'s','to'=>'field'],['from'=>'field','to'=>'e']]];
    $flow=flow_preview_compile($collect);
    $first=flow_preview_step($flow,flow_preview_initial($flow));
    $next=flow_preview_step($flow,$first['state'],'عميل تجريبي');
    ok($next['state']['fields']['field']==='عميل تجريبي'&&$next['done'],
        'collect_field keeps preview-only answer in ephemeral state');
    $writesBefore=(int)one('SELECT COUNT(*) n FROM contacts')['n'];
    $unsafe=['nodes'=>[['id'=>'s','type'=>'start'],['id'=>'x','type'=>'crm_write','text'=>'غير مسموح']],
        'edges'=>[['from'=>'s','to'=>'x']]];
    $flow=flow_preview_compile($unsafe);
    $blocked=flow_preview_step($flow,flow_preview_initial($flow));
    ok($blocked['done']&&str_contains($blocked['events'][0]['text'],'لم تُنفّذ'),
        'CRM action explicitly blocked in preview');
    ok((int)one('SELECT COUNT(*) n FROM contacts')['n']===$writesBefore,
        'preview does not create CRM leads');
    $loop=['nodes'=>[['id'=>'s','type'=>'start']], 'edges'=>[['from'=>'s','to'=>'s']]];
    $loopResult=flow_preview_step(flow_preview_compile($loop),flow_preview_initial(flow_preview_compile($loop)));
    ok($loopResult['done']&&$loopResult['state']['steps']<=40,'cyclic graph bounded');
    foreach([
        ['nodes'=>[],'edges'=>[]],
        ['nodes'=>[['id'=>'s','type'=>'start'],['id'=>'s','type'=>'say']],'edges'=>[]],
        ['nodes'=>[['id'=>'s','type'=>'start']],'edges'=>[['from'=>'s','to'=>'missing']]],
        ['nodes'=>[['id'=>'s','type'=>'start'],['id'=>'a','type'=>'ask']],'edges'=>[
            ['from'=>'s','to'=>'a','label'=>''],['from'=>'s','to'=>'a','label'=>'']]],
        ['nodes'=>[['id'=>'s','type'=>'crm_write']],'edges'=>[]]
    ] as $bad){
        $rejected=false;
        try{flow_preview_compile($bad);}catch(InvalidArgumentException $e){$rejected=true;}
        ok($rejected,'invalid or ambiguous flow rejected');
    }
    ok(flow_preview_agent_for_account(4,1)!==null,'owner can preview own active site agent');
    ok(flow_preview_agent_for_account(5,1)===null,'tenant cannot preview another account agent');
    execute("UPDATE sites SET status='archived' WHERE id=1 AND account_id=4");
    try{ok(flow_preview_agent_for_account(4,1)===null,'archived site denies preview');}
    finally{execute("UPDATE sites SET status='active' WHERE id=1 AND account_id=4");}
    echo "AGENT_FLOW_TEXT_PREVIEW_PASS\n";
}
