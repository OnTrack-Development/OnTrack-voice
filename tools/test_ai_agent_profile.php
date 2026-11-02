<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/FlowAiChat.php';
require_once __DIR__.'/../saas/console/_core/FlowAiProfile.php';
function profile_test(bool $yes,string $description):void{
    if(!$yes)throw new RuntimeException('AI_AGENT_PROFILE_FAIL: '.$description);
}
seed_console();
$agent=one("SELECT id FROM agents WHERE account_id=4 AND status!='archived' LIMIT 1");
profile_test($agent!==null,'fixture');
$user=995712;$id=(int)$agent['id'];
$input=[
    'instructions'=>'رد بالمصري الهادئ ومن غير وعود وهمية',
    'goal'=>'استوعب مشاكل المواقع وقدّم تشخيص آمن',
    'guardrails'=>'ما تكشفش بيانات الفواتير غير بعد التحقق',
    'knowledge_append'=>'',
    'explanation'=>'ضيفت هدف دعم العملاء وقدرة المعرفة؛ لسه محتاج ربط WHMCS موثق.',
    'use_knowledge'=>'on','use_signed_facts'=>'keep','human_handoff'=>'on',
    'integration_suggestions'=>['whmcs','files']
];
$profile=flow_ai_profile_validate($input);
profile_test($profile['goal']===$input['goal'],'valid goal');
profile_test($profile['use_signed_facts']==='keep','no unrequested integration escalation');
$failed=false;
try{flow_ai_profile_validate(array_replace($input,['integration_suggestions'=>['execute_sql']]));}
catch(InvalidArgumentException){$failed=true;}
profile_test($failed,'cannot create arbitrary execution tool');
$failed=false;
try{flow_ai_profile_validate(array_replace($input,['use_knowledge'=>'superadmin']));}
catch(InvalidArgumentException){$failed=true;}
profile_test($failed,'capability strictly limited to preview toggle');
$bad=false;
try{flow_ai_profile_validate(array_replace($input,['knowledge_append'=>str_repeat('x',1501)]));}
catch(InvalidArgumentException){$bad=true;}
profile_test($bad,'bounded knowledge suggestion');
$mock=json_encode(['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[
    ['thought'=>true,'text'=>'not available to application'],
    ['text'=>json_encode($profile,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]
]]]]],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
profile_test(flow_ai_profile_extract($mock)===$profile,'Gemini profile response validated');
try{
    $graph=flow_ai_validate_proposal(['nodes'=>[
       ['id'=>'start','type'=>'start','text'=>'بداية','x'=>600,'y'=>80],
       ['id'=>'end','type'=>'end','text'=>'ختام','x'=>600,'y'=>280]
    ],'edges'=>[['from'=>'start','to'=>'end','label'=>'']]]);
    flow_ai_chat_write(4,$user,$id,[
        ['role'=>'user','text'=>'ضيف معرفة وهدف للدعم'],
        ['role'=>'assistant','text'=>$profile['explanation']]],$graph,$profile);
    $restored=flow_ai_chat_read(4,$user,$id);
    profile_test($restored['pending_graph']===$graph,'graph and settings coexist');
    profile_test($restored['pending_profile']===$profile,'AI property draft survives reload');
    profile_test(count($restored['messages'])===2,'history survives builder save');
    profile_test(flow_ai_chat_read(5,$user,$id)['pending_profile']===null,'tenant isolation');
    profile_test(flow_ai_chat_read(4,$user+1,$id)['pending_profile']===null,'user isolation');
    profile_test(flow_ai_chat_read(4,$user,$id+1)['pending_profile']===null,'agent isolation');
    flow_ai_chat_write(4,$user,$id,$restored['messages'],$graph,null);
    profile_test(flow_ai_chat_read(4,$user,$id)['pending_profile']===null,'apply clears only profile preview');
    profile_test(flow_ai_chat_read(4,$user,$id)['pending_graph']===$graph,'applying properties retains graph');
    echo "VISUAL_BUILDER_AI_PROPERTIES_AND_MEMORY_PASS\n";
}finally{
    execute('DELETE FROM flow_ai_chats WHERE account_id=? AND user_id=? AND agent_id=?',[4,$user,$id]);
}
