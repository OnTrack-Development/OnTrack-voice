<?php
declare(strict_types=1);
require __DIR__.'/../saas/console/database/Seed.php';
require __DIR__.'/../saas/console/_core/TenantWorkspace.php';
function tw_check(bool $test,string $text):void {if(!$test)throw new RuntimeException('TENANT_WORKSPACE_FAIL '.$text);}
seed_console();
$b=['id'=>90001,'account_id'=>4,'site_id'=>1,'agent_id'=>1,'sim_slot'=>0];
$other=['id'=>90002,'account_id'=>5,'site_id'=>3,'agent_id'=>3,'sim_slot'=>1];
$phone='01098765432';
$contact=null;
$call='12345678-1234-4234-9234-123456789012';
$campaign=null;
try{
    $result=tenant_workspace_import($b,[['name'=>'اختبار مساحة العمل','phone'=>$phone]]);
    tw_check($result['added']===1,'contact import');
    $again=tenant_workspace_import($b,[['name'=>'duplicate','phone'=>$phone]]);
    tw_check($again['duplicates']===1,'duplicate contact protected');
    $items=tenant_workspace_contacts($b);
    $match=array_values(array_filter($items['contacts'],fn($c)=>$c['phone']===$phone));
    tw_check(count($match)===1,'contact belongs to SIM site');
    $contact=tenant_workspace_parse_id($match[0]['id']);
    $foreign=tenant_workspace_contacts($other);
    tw_check(!in_array($phone,array_column($foreign['contacts'],'phone'),true),'other tenant cannot see contact');
    $denied=false;
    try{tenant_workspace_dnc($other,$match[0]['id'],true);}catch(InvalidArgumentException){$denied=true;}
    tw_check($denied,'other tenant cannot modify opt-out');
    $blocked=tenant_workspace_dnc($b,$match[0]['id'],true);
    tw_check($blocked['do_not_call']===true,'account-scoped DNC recorded');
    $optInDenied=false;
    try{tenant_workspace_dnc($b,$match[0]['id'],false);}catch(InvalidArgumentException){$optInDenied=true;}
    tw_check($optInDenied,'cannot silently remove DNC');
    $event=['call_id'=>$call,'sequence'=>1,'phase'=>'ringing','duration_seconds'=>0];
    tw_check(tenant_workspace_call_event($b,$event)['accepted'],'call event inserted');
    tw_check(tenant_workspace_call_event($b,$event)['duplicate'],'same event deduplicated');
    $history=tenant_workspace_calls($b);
    tw_check(count(array_filter($history['calls'],fn($c)=>$c['call_id']===$call))===1,'SIM call appears once');
    $otherHistory=tenant_workspace_calls($other);
    tw_check(!in_array($call,array_column($otherHistory['calls'],'call_id'),true),'SIM2 cannot see SIM1 call');
    $campaignData=['confirmed'=>true,'name'=>'اختبار حملة SaaS','starts'=>'2026-11-03T10:00','ends'=>'2026-11-03T11:00','scenario'=>'اختبار فقط','contact_ids'=>[$match[0]['id']]];
    $created=tenant_workspace_create_campaign($b,$campaignData);$campaign=$created['campaign']['id'];
    tw_check($created['calls_started']===0,'saving draft never dials');
    $preview=tenant_workspace_campaign_action($b,['confirmed'=>true,'id'=>$campaign,'action'=>'preview']);
    tw_check($preview['preview']['excluded']===1,'blocked contact excluded');
    $armed=false;
    try{tenant_workspace_campaign_action($b,['confirmed'=>true,'id'=>$campaign,'action'=>'arm']);}
    catch(InvalidArgumentException){$armed=true;}
    tw_check($armed,'unverified auto dial denied');
    $cross=false;
    try{tenant_workspace_campaign_action($other,['confirmed'=>true,'id'=>$campaign,'action'=>'preview']);}
    catch(InvalidArgumentException){$cross=true;}
    tw_check($cross,'other tenant cannot access campaign');
    echo "TENANT_WORKSPACE_PASS\n";
} finally {
    if($campaign!==null)execute('DELETE FROM tenant_campaigns WHERE id=? AND account_id=?',[$campaign,$b['account_id']]);
    execute('DELETE FROM tenant_call_events WHERE device_binding_id=? AND call_id=?',[$b['id'],$call]);
    execute('DELETE FROM calls WHERE device_binding_id=? AND external_call_id=?',[$b['id'],$call]);
    if($contact!==null)execute('DELETE FROM contacts WHERE id=? AND account_id=?',[$contact,$b['account_id']]);
}
