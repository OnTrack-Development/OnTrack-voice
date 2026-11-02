<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/FlowAiChat.php';
function chat_check(bool $condition,string $message):void {
    if(!$condition)throw new RuntimeException('FLOW_AI_CHAT_FAIL '.$message);
}
if(is_dir('/home/appontrack'))throw new RuntimeException('CI only');
seed_console();
$agent=one("SELECT id FROM agents WHERE account_id=4 AND status!='archived' ORDER BY id LIMIT 1");
chat_check($agent!==null,'demo agent');
$agentId=(int)$agent['id'];
$userId=990001;
$graph=flow_ai_validate_proposal(['nodes'=>[
    ['id'=>'start','type'=>'start','text'=>'أهلاً بيك في الاستضافة','x'=>500,'y'=>80],
    ['id'=>'ask','type'=>'ask','text'=>'حضرتك محتاج استضافة ولا دومين؟','x'=>500,'y'=>280],
    ['id'=>'hosting','type'=>'say','text'=>'عندنا استضافة للمواقع','x'=>370,'y'=>480],
    ['id'=>'domain','type'=>'say','text'=>'عندنا دومينات','x'=>710,'y'=>480],
    ['id'=>'end','type'=>'end','text'=>'شكرًا ليك','x'=>500,'y'=>680],
], 'edges'=>[
    ['from'=>'start','to'=>'ask','label'=>''],
    ['from'=>'ask','to'=>'hosting','label'=>'استضافة'],
    ['from'=>'ask','to'=>'domain','label'=>'دومين'],
    ['from'=>'hosting','to'=>'end','label'=>''],
    ['from'=>'domain','to'=>'end','label'=>''],
]]);
try{
    $initial=flow_ai_chat_read(4,$userId,$agentId);
    chat_check(!$initial['messages']&&$initial['pending_graph']===null,'new chat empty');
    $description='عايز سيناريو دعم الاستضافة';
    $explanation=flow_ai_explain_graph($graph);
    chat_check(str_contains($explanation,'البداية'),'explains start');
    chat_check(str_contains($explanation,'استضافة')&&str_contains($explanation,'دومين'),'explains both branches');
    chat_check(str_contains($explanation,'المسار الافتراضي'),'explains fallback route');
    $extended=$graph;
    foreach($extended['nodes'] as &$node){
        if($node['id']==='hosting'){$node['type']='knowledge_lookup';break;}
    }
    unset($node);
    $expandedExplanation=flow_ai_explain_graph($extended);
    chat_check(str_contains($expandedExplanation,'بحث في المعرفة'),'explains actual knowledge_lookup node');
    chat_check(count(flow_ai_validate_proposal($extended)['nodes'])===5,
        'AI edit keeps existing knowledge lookup node type');
    $originalModel=getenv('ONTRACK_GEMINI_FLOW_MODEL');
    putenv('ONTRACK_GEMINI_FLOW_MODEL=gemini-3.5-flash-lite');
    chat_check(flow_ai_model()==='gemini-3.5-flash-lite','effective model shown accurately');
    if($originalModel===false)putenv('ONTRACK_GEMINI_FLOW_MODEL');
    else putenv('ONTRACK_GEMINI_FLOW_MODEL='.$originalModel);
    chat_check(str_contains($explanation,'الحجز')||str_contains($explanation,'تكامل'),'safe side-effect disclaimer');
    flow_ai_chat_write(4,$userId,$agentId,[['role'=>'user','text'=>$description],
        ['role'=>'assistant','text'=>$explanation]],$graph);
    $restored=flow_ai_chat_read(4,$userId,$agentId);
    chat_check(count($restored['messages'])===2,'chat restored after reload');
    chat_check($restored['pending_graph']===$graph,'pending preview restored');
    chat_check(flow_ai_chat_read(5,$userId,$agentId)['messages']===[],'tenant isolation');
    chat_check(flow_ai_chat_read(4,$userId+1,$agentId)['messages']===[],'editor isolation');
    chat_check(flow_ai_chat_read(4,$userId,$agentId+1)['messages']===[],'agent isolation');
    $restored['messages'][]=['role'=>'assistant','text'=>'تم اختيار المقترح للحفظ كمسودة'];
    flow_ai_chat_write(4,$userId,$agentId,$restored['messages'],null);
    $saved=flow_ai_chat_read(4,$userId,$agentId);
    chat_check(count($saved['messages'])===3 && $saved['pending_graph']===null,'history retained after draft apply');
    $many=[];
    for($i=0;$i<40;$i++)$many[]=['role'=>$i%2?'assistant':'user','text'=>'ملاحظة '.(string)$i];
    flow_ai_chat_write(4,$userId,$agentId,$many,$graph);
    chat_check(count(flow_ai_chat_read(4,$userId,$agentId)['messages'])===18,'bounded memory');
    echo "FLOW_AI_CHAT_MEMORY_AND_EXPLANATION_PASS\n";
}finally{
    execute('DELETE FROM flow_ai_chats WHERE account_id=? AND user_id=? AND agent_id=?',[4,$userId,$agentId]);
}
