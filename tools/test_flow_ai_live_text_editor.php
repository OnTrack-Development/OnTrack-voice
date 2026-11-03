<?php
declare(strict_types=1);
/** End-to-end offline check: both Agent Builder chat modes use Gemini 3.8
 * LIVE via completed clientContent TEXT turn and a safe structured tool call.
 * Never requests actual Google credentials or changes a stored draft. */
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/FlowAiProfile.php';
function live_editor_ok(bool $ok,string $why):void {
    if(!$ok)throw new RuntimeException('LIVE_TEXT_EDITOR_FAILED: '.$why);
}
if(is_dir('/home/appontrack'))throw new RuntimeException('CI only; no production DB');
seed_console();
$agent=one("SELECT id FROM agents WHERE account_id=4 AND status!='archived' ORDER BY id LIMIT 1");
live_editor_ok(is_array($agent),'fixture agent in own tenant');
$id=(int)$agent['id'];
$graph=[
    'nodes'=>[
        ['id'=>'start','type'=>'start','text'=>'أهلاً بيك','x'=>350,'y'=>70],
        ['id'=>'end','type'=>'end','text'=>'تشرفنا','x'=>350,'y'=>290]],
    'edges'=>[['from'=>'start','to'=>'end','label'=>'المسار الافتراضي']]
];
$seenGraph=0;
$graphTransport=static function(array $setup,array $turn)use($graph,&$seenGraph):array {
    ++$seenGraph;
    live_editor_ok(($setup['setup']['model']??'')==='models/gemini-3.8-live-extended-thinking','graph model is 3.8 Live');
    live_editor_ok(($setup['setup']['generationConfig']['responseModalities']??null)===['AUDIO'],
        'Live audio modality used with text-turn input');
    live_editor_ok(!isset($setup['setup']['generationConfig']['responseMimeType']) &&
        !isset($setup['setup']['generationConfig']['responseSchema']),
        'unsupported REST-only JSON schema fields never sent to Live');
    live_editor_ok(($turn['clientContent']['turnComplete']??null)===true,
        'text turn is explicitly completed');
    $text=(string)($turn['clientContent']['turns'][0]['parts'][0]['text']??'');
    live_editor_ok(str_contains($text,'خدمة العملاء') && str_contains($text,'CURRENT EDITOR DRAFT'),
        'previous graph and chat are sent as the real text input');
    live_editor_ok(($setup['setup']['tools'][0]['functionDeclarations'][0]['behavior']??null)==='NON_BLOCKING',
        'structured proposal tool never executes phone/CRM actions');
    return ['toolCall'=>['functionCalls'=>[
        ['name'=>'submit_editor_proposal','args'=>$graph]
    ]]];
};
$before=(int)one("SELECT COUNT(*) n FROM agents WHERE account_id=4")['n'];
$actual=flow_ai_generate(4,$id,'اعمل سيناريو خدمة العملاء للعقارات',[],$graph,$graphTransport);
live_editor_ok($actual===$graph && $seenGraph===1,'graph mode actually invokes Live text-turn adapter');
live_editor_ok((int)one("SELECT COUNT(*) n FROM agents WHERE account_id=4")['n']===$before,
    'preview does not create, change or publish agents');
$profile=[
    'instructions'=>'رد بالمصري الرسمي','goal'=>'تسجيل استفسارات العميل',
    'guardrails'=>'لا تنفذ حجز بدون موافقة','knowledge_append'=>'',
    'explanation'=>'تم تنفيذ الحجز وجميع التعديلات الآن',
    'use_knowledge'=>'keep','use_signed_facts'=>'keep','human_handoff'=>'on',
    'integration_suggestions'=>['files']
];
$seenProfile=0;
$profileTransport=static function(array $setup,array $turn)use($profile,&$seenProfile):array {
    ++$seenProfile;
    live_editor_ok(($setup['setup']['model']??'')==='models/gemini-3.8-live-extended-thinking',
        'profile uses identical 3.8 Live model, not generateContent');
    $text=(string)($turn['clientContent']['turns'][0]['parts'][0]['text']??'');
    live_editor_ok(($turn['clientContent']['turnComplete']??null)===true &&
        str_contains($text,'current_editor') && str_contains($text,'مكتب محاماة'),
        'current agent settings are provided in a completed TEXT turn');
    return ['toolCall'=>['functionCalls'=>[
        ['name'=>'submit_editor_proposal','args'=>$profile]
    ]]];
};
$current=['instructions'=>'قديم','goal'=>'رد على الهاتف','guardrails'=>'راجع الصلاحيات',
    'knowledge'=>'','use_knowledge'=>false,'use_signed_facts'=>false,'human_handoff'=>false];
$profileResult=flow_ai_profile_generate(4,$id,'اعمل فلو كامل لمكتب محاماة',$current,[],$profileTransport);
live_editor_ok($seenProfile===1 && $profileResult['goal']==='تسجيل استفسارات العميل' &&
    !str_contains($profileResult['explanation'],'تم تنفيذ الحجز'),
    'profile uses Live tool and strips untrusted claims of completed external actions');
$missing=false;
try{
    flow_ai_generate(4,$id,'اعمل سيناريو خدمة العملاء للعقارات',[],$graph,
        static fn():array=>['serverContent'=>['outputTranscription'=>['text'=>'وعد عام بدون مخطط']]]);
}catch(RuntimeException $error){$missing=true;}
live_editor_ok($missing,'no false success if Live did not return structured tool args');
$flowSource=(string)file_get_contents(__DIR__.'/../saas/console/_core/FlowAi.php');
$profileSource=(string)file_get_contents(__DIR__.'/../saas/console/_core/FlowAiProfile.php');
live_editor_ok(!str_contains($flowSource,'flow_ai_google_request(') &&
    !str_contains($profileSource,'flow_ai_google_request('),
    'both chat modes have removed REST generateContent transport');
live_editor_ok(flow_ai_model()==='gemini-3.8-live-extended-thinking',
    'assistant UI advertises actual Live model');
echo "FLOW_BUILDER_GRAPH_AND_PROFILE_GEMINI_3_8_LIVE_TEXT_TURN_PASS\n";
