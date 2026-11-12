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
    // Real DB binding fixture so pre-dial checks cannot pass merely because
    // there was no physical SIM row to authorize the attempt.
    $b['id']=insert("INSERT INTO device_bindings
       (account_id,site_id,agent_id,installation_id,sim_slot,slot_claim,
        device_name,token_hash,status,created_at)
       VALUES (?,?,?,?,?,?,?,?,'active',?)",[
       4,1,1,'tcq-fixture-install-00001',0,
       hash('sha256','tcq-fixture-install-00001|0'),
       'Fixture SIM',hash('sha256','tcq-device-token'),now()
    ]);
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
    $phoneCall=one('SELECT is_demo,device_binding_id,external_call_id FROM calls WHERE device_binding_id=? AND external_call_id=?',[$b['id'],$call]);
    tw_check((int)$phoneCall['is_demo']===0 && (int)$phoneCall['device_binding_id']===$b['id'],
        'genuine device call must never be tagged as demo');
    tw_check($phoneCall['external_call_id']===$call,'device call ID survives write path');

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
    tw_check($armed,'unconsented target cannot be armed');
    // A second, owner-controlled consented number must never bypass server gating.
    $consentRejected=false;
    try{tcq_consent($b,$match[0]['id'],'owner_test','owner fixture consent');}
    catch(InvalidArgumentException){$consentRejected=true;}
    tw_check($consentRejected,'existing DNC cannot be overridden by consent');
    $approved='01077778888';
    tenant_workspace_import($b,[['name'=>'رقم اختبار بموافقة','phone'=>$approved]]);
    $approvedRows=tenant_workspace_contacts($b);
    $approvedContact=array_values(array_filter($approvedRows['contacts'],
        fn($c)=>$c['phone']===$approved))[0];
    $approvedId=tenant_workspace_parse_id($approvedContact['id']);
    $stored=tcq_consent($b,$approvedContact['id'],'owner_test','explicit consent owner fixture');
    tw_check($stored['recorded']===true,'explicit marketing consent persisted');
    $localNow=new DateTimeImmutable('now',new DateTimeZone('Africa/Cairo'));
    $start=$localNow->modify('-5 minutes')->format('Y-m-d\TH:i');
    $end=$localNow->modify('+65 minutes')->format('Y-m-d\TH:i');
    $ready=tenant_workspace_create_campaign($b,['confirmed'=>true,
        'name'=>'حملة ذات موافقة','starts'=>$start,'ends'=>$end,
        'scenario'=>'اتصال بموافقة مسبقة','contact_ids'=>[$approvedContact['id']]]);
    $campaign2=$ready['campaign']['id'];
    // An empty selection intentionally covers all (and ONLY) this site's
    // consented contacts, not the empty list or other tenant's contacts.
    $whole=tenant_workspace_create_campaign($b,['confirmed'=>true,
        'name'=>'حملة كل جهات الاتصال','starts'=>$start,'ends'=>$end,
        'scenario'=>'اتصال بعد موافقة موثقة','contact_ids'=>[]]);
    $allCampaign=$whole['campaign']['id'];
    $wholePreview=tenant_workspace_campaign_action($b,['confirmed'=>true,
        'id'=>$allCampaign,'action'=>'preview']);
    tw_check(count($wholePreview['preview']['eligible'])===1 &&
        $wholePreview['preview']['excluded']>=1,
        'empty target selection includes only explicitly consented site contacts');
    $allArmed=tenant_workspace_campaign_action($b,['confirmed'=>true,
        'id'=>$allCampaign,'action'=>'arm']);
    tw_check($allArmed['campaign']['status']==='ready','whole-site campaign can arm safely');
    $armedResult=tenant_workspace_campaign_action($b,['confirmed'=>true,
        'id'=>$campaign2,'action'=>'arm']);
    tw_check($armedResult['campaign']['status']==='ready','explicit consented campaign armed');
    putenv('ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED');
    $disabled=false;
    try{tenant_workspace_campaign_claim($b);}catch(RuntimeException){$disabled=true;}
    tw_check($disabled,'real SIM dispatch must remain disabled by default');
    putenv('ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED=1');
    try{
        $lease=tenant_workspace_campaign_claim($b);
        tw_check(is_array($lease['claim']) && $lease['claim']['phone']===$approved,
            'scoped single campaign lease created');
        tw_check(tenant_workspace_campaign_claim($b)['claim']===null,
            'active SIM lease cannot overlap');
        $unverifiedResult=false;
        try{tenant_workspace_campaign_finish($b,['attempt_id'=>$lease['claim']['id'],'result'=>'completed']);}
        catch(InvalidArgumentException){$unverifiedResult=true;}
        tw_check($unverifiedResult,'leased, not verified: cannot claim a completed call');
        $attemptId=$lease['claim']['id'];
        $forbidden=tenant_workspace_campaign_verify($other,['attempt_id'=>$attemptId]);
        tw_check($forbidden['verification']['allowed']===false,'cross tenant cannot verify');
        $authorized=tenant_workspace_campaign_verify($b,['attempt_id'=>$attemptId]);
        tw_check($authorized['verification']['allowed']===true &&
            $authorized['verification']['must_dial_within_seconds']===10,
            'valid authenticated physical SIM and explicit consent allow only short pre-dial window');
        $finished=tenant_workspace_campaign_finish($b,['attempt_id'=>$attemptId,'result'=>'completed']);
        tw_check($finished['attempt']['saved']===true,
            'completed result only after real binding pre-dial validation');
        $repeatFinish=tenant_workspace_campaign_finish($b,['attempt_id'=>$attemptId,'result'=>'completed']);
        tw_check($repeatFinish['attempt']['duplicate']===true,'terminal callback is idempotent');
        $second=tenant_workspace_campaign_claim($b);
        tw_check(is_array($second['claim']) && $second['claim']['id']!==$attemptId,
            'second reviewed campaign has unique attempt');
        $nextId=$second['claim']['id'];
        tenant_workspace_dnc($b,$approvedContact['id'],true);
        $verify=tenant_workspace_campaign_verify($b,['attempt_id'=>$nextId]);
        tw_check($verify['verification']['allowed']===false &&
            $verify['verification']['reason']==='consent_or_dnc_changed',
            'DNC update after lease really blocks the pre-dial check on a valid SIM');
        $endAttempt=tenant_workspace_campaign_finish($b,['attempt_id'=>$nextId,'result'=>'unknown']);
        tw_check($endAttempt['attempt']['saved']===true,
            'uncertain result persisted without fake completion');
        $blocked=tenant_workspace_campaign_claim($b);
        tw_check($blocked['claim']===null &&
            $blocked['reason']==='uncertain_call_requires_review',
            'uncertain SIM attempt blocks automatic continuation or repeat');
    }finally{putenv('ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED');}
    $cross=false;
    try{tcq_consent($other,$approvedContact['id'],'owner_test','cross account cannot authorize');}
    catch(InvalidArgumentException){$cross=true;}
    tw_check($cross,'other tenant cannot create campaign consent');
    $cross=false;
    try{tenant_workspace_campaign_action($other,['confirmed'=>true,'id'=>$campaign,'action'=>'preview']);}
    catch(InvalidArgumentException){$cross=true;}
    tw_check($cross,'other tenant cannot access campaign');
    echo "TENANT_WORKSPACE_PASS\n";
} finally {
    putenv('ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED');
    if(isset($allCampaign)){
        execute('DELETE FROM tenant_campaign_attempts WHERE campaign_id=?',[$allCampaign]);
        execute('DELETE FROM tenant_campaigns WHERE id=? AND account_id=?',[$allCampaign,$b['account_id']]);
    }
    if(isset($campaign2))execute('DELETE FROM tenant_campaign_attempts WHERE campaign_id=?',[$campaign2]);
    if(isset($campaign2))execute('DELETE FROM tenant_campaigns WHERE id=? AND account_id=?',[$campaign2,$b['account_id']]);
    if($campaign!==null)execute('DELETE FROM tenant_campaigns WHERE id=? AND account_id=?',[$campaign,$b['account_id']]);
    if(isset($approvedId)){
        execute('DELETE FROM tenant_campaign_consents WHERE contact_id=? AND account_id=?',[$approvedId,$b['account_id']]);
        execute('DELETE FROM contacts WHERE id=? AND account_id=?',[$approvedId,$b['account_id']]);
    }
    execute('DELETE FROM tenant_call_events WHERE device_binding_id=? AND call_id=?',[$b['id'],$call]);
    execute('DELETE FROM calls WHERE device_binding_id=? AND external_call_id=?',[$b['id'],$call]);
    if($contact!==null)execute('DELETE FROM contacts WHERE id=? AND account_id=?',[$contact,$b['account_id']]);
    // If the fixture insert succeeded, release only its own active binding.
    if($b['id']!==90001)execute('DELETE FROM device_bindings WHERE id=? AND account_id=?',[$b['id'],$b['account_id']]);
}
