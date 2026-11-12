<?php
declare(strict_types=1);
if(is_dir('/home/appontrack'))throw new RuntimeException('CI_TEST_ONLY');
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/VoiceConnectors.php';
require_once __DIR__.'/../saas/console/_core/TenantVoiceSession.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
function scenario_ok(bool $check,string $why):void {
    if(!$check)throw new RuntimeException('SCENARIO_MODE_REGRESSION: '.$why);
}
// Ensure a scripted ending never gets overridden by the generic
// customer-service goodbye and that the caller finishes their thought.
$stylePolicy=answer_policy_normalize([
    'scenario_mode'=>'scenario','scenario_text'=>'مشهد تمثيلي',
    'response_style'=>'egyptian_short'
]);
$styleInstruction=tenant_dynamic_instruction('Demo',
    ['instructions'=>'','knowledge'=>''],$stylePolicy,'','',
    tenant_voice_scenario_instruction($stylePolicy['scenario_text']));
scenario_ok(str_contains($styleInstruction,'سكتة تفكير قصيرة'),
    'customer can finish a sentence before AI replies');
scenario_ok(str_contains($styleInstruction,'إيقاع الكلام طبيعي'),
    'natural Egyptian speech pace');
scenario_ok(str_contains($styleInstruction,'بسرعة متوسطة هادية') &&
    str_contains($styleInstruction,'افصل بين الجمل'),
    'Egyptian voice instructs an unhurried intelligible pace');
scenario_ok(str_contains($styleInstruction,'بعد جملة الختام توقف عن الكلام'),
    'text scenario finale is not restarted');
seed_console();
$account=4;$site=1;
$graph=['nodes'=>[
    ['id'=>'start','type'=>'start','text'=>'ابدأ من البيلدر'],
    ['id'=>'ask','type'=>'ask','text'=>'اسأل عن نوع الخدمة من البيلدر'],
    ['id'=>'end','type'=>'end','text'=>'نهاية البيلدر']
], 'edges'=>[['from'=>'start','to'=>'ask','label'=>''],['from'=>'ask','to'=>'end','label'=>'*']]];
$agent=insert("INSERT INTO agents(account_id,site_id,name,voice,instructions,knowledge,flow_json,status,created_at) VALUES (?,?,?,?,?,?,?,'draft',?)",
    [$account,$site,'Scenario regression agent','google.aoede','تكلم بشكل مهني','معلومات خدمة',
     json_encode($graph,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),now()]);
$connector=null;$owner=one("SELECT user_id FROM memberships WHERE account_id=4 AND role='owner' LIMIT 1");
scenario_ok($owner!==null,'seeded owner exists');
$ownerId=(int)$owner['user_id'];
$binding=['account_id'=>$account,'site_id'=>$site,'agent_id'=>$agent,'sim_slot'=>0];
try {
    $first=publish_agent_flow($account,$agent,$ownerId);
    scenario_ok($first['revision']===1,'builder first publish');
    $built=tenant_voice_definition($binding);
    scenario_ok($built['scenario_mode']==='builder'&&
        $built['routing_contract']==='published_graph_prompt_guidance'&&
        str_contains($built['instruction'],'اسأل عن نوع الخدمة من البيلدر'),'builder live runtime');
    $text="سيناريو بيع كلب (ديمو): رحّب مرة واحدة. اسأل عن السلالة والعمر والميزانية.\n".
        "Only give verified prices, then offer a human follow-up, never claim a live transfer.";
    $policy=answer_policy_defaults();
    $policy['scenario_mode']='scenario';$policy['scenario_text']=$text;
    $policy['response_style']='multilingual';$policy['api_language']='multilingual';
    save_answer_policy($account,$agent,$policy);
    $stale=tenant_voice_definition($binding);
    scenario_ok($stale['scenario_mode']==='builder' &&
        !str_contains($stale['instruction'],$text),'unpublished scenario never reaches SIM');

    $second=publish_agent_flow($account,$agent,$ownerId);
    scenario_ok($second['revision']===2,'mode switch increments immutable revision');
    $live=tenant_voice_definition($binding);
    scenario_ok($live['scenario_mode']==='scenario'&&
        $live['routing_contract']==='published_text_scenario_prompt_guidance'&&
        str_contains($live['instruction'],$text)&&
        !str_contains($live['instruction'],'اسأل عن نوع الخدمة من البيلدر'),
        'published text scenario is exclusive active guide');
    scenario_ok(str_contains($live['instruction'],'لغة العميل') &&
        str_contains($live['instruction'],'أي لغة'),'supports multilingual voice and API');
    $snapshot=published_agent_flow($account,$site,$agent);
    $decoded=json_decode((string)$snapshot['answer_policy_json'],true);
    scenario_ok($decoded['scenario_text']===$text,'Unicode scenario preserved in snapshot');
    $again=publish_agent_flow($account,$agent,$ownerId);
    scenario_ok($again['duplicate']===true && $again['revision']===2,'same scenario publish idempotent');

    $policy['scenario_text']='نسخة مسودة مختلفة';
    save_answer_policy($account,$agent,$policy);
    scenario_ok(str_contains(tenant_voice_definition($binding)['instruction'],$text),
        'editing draft does not overwrite published scenario');
    $policy['scenario_mode']='builder';
    save_answer_policy($account,$agent,$policy);
    $third=publish_agent_flow($account,$agent,$ownerId);
    scenario_ok($third['revision']===3,'switching back to builder publishes new revision');
    $restored=restore_published_agent_draft($account,$agent,2);
    scenario_ok($restored['published_unchanged']===true &&
        (string)one('SELECT status FROM agents WHERE id=?',[$agent])['status']==='draft',
        'restoring a historical revision changes draft only');
    scenario_ok(answer_policy_for_agent($account,$agent)['scenario_mode']==='scenario' &&
        answer_policy_for_agent($account,$agent)['scenario_text']===$text,
        'historical text scenario restored alongside graph');
    scenario_ok((int)published_agent_flow($account,$site,$agent)['revision']===3,
        'restoring draft leaves immutable published revision untouched');
    $missing=false;try{restore_published_agent_draft($account,$agent,99999);}
    catch(InvalidArgumentException){$missing=true;}
    scenario_ok($missing,'unknown revision restore denied');
    $foreign=false;try{restore_published_agent_draft(5,$agent,1);}
    catch(InvalidArgumentException){$foreign=true;}
    scenario_ok($foreign,'cross-tenant restore denied');
    restore_published_agent_draft($account,$agent,3);
    $back=tenant_voice_definition($binding);
    scenario_ok($back['scenario_mode']==='builder'&&
        !str_contains($back['instruction'],'نسخة مسودة مختلفة')&&
        str_contains($back['instruction'],'اسأل عن نوع الخدمة من البيلدر'),
        'graph restored and scenario text retained without guiding speech');
    scenario_ok(answer_policy_for_agent($account,$agent)['scenario_text']==='نسخة مسودة مختلفة',
        'switch retains the other editor unchanged');

    $policy['scenario_mode']='scenario';$policy['scenario_text']='';
    save_answer_policy($account,$agent,$policy);
    $reject=false;
    try{publish_agent_flow($account,$agent,$ownerId);}catch(InvalidArgumentException){$reject=true;}
    scenario_ok($reject,'cannot publish active empty scenario');
    scenario_ok(tenant_voice_definition($binding)['scenario_mode']==='builder',
        'rejected scenario does not change published SIM profile');

    $connector=voice_connector_add($account,$site,'custom','Demo verified Arabic API',
        'https://example.org','/catalogue','bearer','DEMO_SECRET_VALUE_123');
    execute("UPDATE voice_data_connectors SET status='verified' WHERE id=? AND account_id=?",[$connector,$account]);
    $policy['scenario_mode']='scenario';$policy['scenario_text']=$text;
    $policy['api_connector_id']=$connector;$policy['api_language']='ar';
    save_answer_policy($account,$agent,$policy);
    publish_agent_flow($account,$agent,$ownerId);
    $api=tenant_voice_definition($binding)['instruction'];
    scenario_ok(str_contains($api,'Demo verified Arabic API') &&
        str_contains($api,'العربية') &&
        !str_contains($api,'DEMO_SECRET_VALUE_123'),'verified tenant API name and Arabic locale without credentials');

    $foreign=voice_connector_add($account,$site,'custom','Unverified API',
        'https://example.net','/catalogue','none');
    $policy['api_connector_id']=$foreign;
    save_answer_policy($account,$agent,$policy);
    $rejected=false;
    try{publish_agent_flow($account,$agent,$ownerId);}catch(InvalidArgumentException){$rejected=true;}
    scenario_ok($rejected,'unverified connector cannot be published');
    execute('DELETE FROM voice_data_connectors WHERE id=?',[$foreign]);
    $policy['api_connector_id']=0;
    save_answer_policy($account,$agent,$policy);

    $_SESSION=['uid'=>$ownerId,'account_id'=>$account];$_GET=['tab'=>'agents','site'=>(string)$site];
    $a=one('SELECT * FROM accounts WHERE id=?',[$account]);
    ob_start();render_agents(array_merge($a,['account_id'=>$account,'role'=>'owner']));
    $html=(string)ob_get_clean();
    foreach(['name="scenario_mode"','name="scenario_text"','data-builder-tab="scenario"',
         'data-builder-tab="visual"','data-scenario-example="dog-sale"',
         'name="api_connector_id"','name="api_language"'] as $needle)
        scenario_ok(str_contains($html,$needle),'missing UI control '.$needle);
    scenario_ok(str_contains($html,'Demo verified Arabic API'),'verified API selectable in studio');
    echo "AGENT_SCENARIO_TEXT_BUILDER_SWITCH_API_LOCALE_PASS\n";
}finally{
    execute('DELETE FROM agent_flow_versions WHERE agent_id=? AND account_id=?',[$agent,$account]);
    execute('DELETE FROM agent_answer_policies WHERE agent_id=? AND account_id=?',[$agent,$account]);
    execute('DELETE FROM agents WHERE id=? AND account_id=?',[$agent,$account]);
    if($connector!==null)execute('DELETE FROM voice_data_connectors WHERE id=? AND account_id=?',[$connector,$account]);
}
