<?php
declare(strict_types=1);
$root=sys_get_temp_dir().'/ontrack-pilot-test-'.bin2hex(random_bytes(5));
define('MOBILE_STORE',$root);
require dirname(__DIR__).'/saas/mobile/server/pilot_workspace.php';
require dirname(__DIR__).'/saas/mobile/server/pilot_import_file.php';
function expect(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
try {
    expect(pilot_number('01012345678')==='+201012345678','egypt_normalize');
    expect(pilot_number('+201012345678')==='+201012345678','international_normalize');
    expect(pilot_number('abc')===null,'reject_non_phone');
    $r=pilot_import_contacts([
        ['name'=>'Test Customer','phone'=>'01012345678'],
        ['name'=>'Duplicate','phone'=>'+201012345678'],
        ['name'=>'Invalid','phone'=>'not-a-number'],
        ['name'=>'Another','phone'=>'01112345678']
    ]);
    expect($r['added']===2 && $r['duplicates']===1 && $r['invalid']===1,'contact_import_counts');
    $items=pilot_read('contacts');expect(count($items)===2,'contact_store');
    expect(pilot_dnc($items[0]['id'],true),'dnc_update');
    $item=pilot_read('contacts')[0];expect($item['do_not_call']===true,'dnc_persisted');
    $row=pilot_campaign_create('Pilot','2026-11-01T10:00','2026-11-01T12:00','Introduction only');
    expect($row['status']==='draft' && $row['eligible_contacts_snapshot']===1,'campaign_guard');
    $preview=pilot_campaign_preview($row['id']);expect(count($preview['eligible'])===1 && $preview['excluded']===1 && $preview['auto_dial_enabled']===false,'dnc_preview');
    $paused=pilot_campaign_transition($row['id'],'pause');expect($paused['status']==='paused','pause');
    $resumed=pilot_campaign_transition($row['id'],'resume');expect($resumed['status']==='draft','resume');
    $cancelled=pilot_campaign_transition($row['id'],'cancel');expect($cancelled['status']==='cancelled','cancel');
    try{pilot_campaign_transition($row['id'],'resume');throw new RuntimeException('accepted_cancelled_resume');}catch(InvalidArgumentException $e){}
    try{pilot_campaign_create('Wrong','2026-11-02T12:00','2026-11-01T10:00','x');throw new RuntimeException('accepted_invalid_schedule');}
    catch(InvalidArgumentException $e){}
    $subset=pilot_campaign_create('Excluded Only','2026-11-01T10:00','2026-11-01T12:00',
        'Do not call',[$items[0]['id']]);
    expect($subset['eligible_contacts_snapshot']===0,'selected_contact_DNC_excluded');
    $subsetPreview=pilot_campaign_preview($subset['id']);
    expect(count($subsetPreview['eligible'])===0,'selected_only_exclusion');
    $now=new DateTimeImmutable('now',new DateTimeZone('Africa/Cairo'));
    $armed=pilot_campaign_create('E2E Lease',$now->modify('-2 minutes')->format('Y-m-d\\TH:i'),
        $now->modify('+120 minutes')->format('Y-m-d\\TH:i'),'Owner approved script');
    $ready=pilot_campaign_arm($armed['id']);expect($ready['status']==='ready','arm_explicit');
    $lease=pilot_campaign_claim();expect(is_array($lease)&&$lease['phone']==='+201112345678','one_dnc_filtered_lease');
    expect(pilot_campaign_verify_claim($lease['id'])['allowed']===true,'verified_consent_and_dnc');
    expect(pilot_campaign_claim()===null,'exclusive_claim_no_duplicate');
    $finished=pilot_campaign_finish($lease['id'],'completed');expect($finished['status']==='completed','finish_once');
    expect(pilot_campaign_finish($lease['id'],'completed')['status']==='completed','idempotent_finish');
    expect(pilot_campaign_claim()===null,'no_redial');
    try{pilot_campaign_finish($lease['id'],'failed');throw new RuntimeException('changed_terminal_result');}
    catch(InvalidArgumentException $e){}
    // Explicit opt-out must survive contact deletion, re-import and an
    // Android attempt already leased but not yet placed.
    $live=pilot_read('contacts')[1];
    $raceAttempt=['id'=>bin2hex(random_bytes(16)),'campaign_id'=>$armed['id'],
        'contact_id'=>$live['id'],'phone'=>$live['phone'],'status'=>'leased',
        'leased_epoch'=>time(),'created_at'=>gmdate('c')];
    pilot_transaction(static function()use($raceAttempt):void {
        $attempts=pilot_read('attempts');$attempts[]=$raceAttempt;
        pilot_atomic('attempts',$attempts);
    });
    expect(pilot_campaign_verify_claim($raceAttempt['id'])['allowed']===true,'leased_pre_dial_initially_allowed');
    expect(pilot_dnc($live['id'],true),'opt_out_while_leased');
    expect(pilot_campaign_verify_claim($raceAttempt['id'])['allowed']===false,'opt_out_revokes_pre_dial_permission');
    expect(pilot_campaign_finish($raceAttempt['id'],'failed')['status']==='failed','blocked_attempt_terminal');
    $unblocked=false;
    try{pilot_dnc($live['id'],false);}
    catch(InvalidArgumentException $e){$unblocked=$e->getMessage()==='verified_reconsent_required';}
    expect($unblocked,'unverified_opt_out_reversal_rejected');
    $suppressed=pilot_read('dnc');
    expect(in_array('+201012345678',$suppressed,true)&&in_array('+201112345678',$suppressed,true),
        'durable_ledger_contains_both_opt_outs');
    pilot_transaction(static function():void {pilot_atomic('contacts',[]);});
    $reimport=pilot_import_contacts([['name'=>'Reimport after deletion','phone'=>'00201112345678']]);
    expect($reimport['added']===1 && $reimport['blocked']===1,'suppressed_number_cannot_be_reimported_as_callable');
    $recreated=pilot_read('contacts')[0];
    expect($recreated['do_not_call']===true && $recreated['consented']===false,
        'reimport_preserves_suppression_and_no_consent');
    $blockedCampaign=pilot_campaign_create('Reimport blocked',$now->modify('-1 minute')->format('Y-m-d\\TH:i'),
        $now->modify('+1 hour')->format('Y-m-d\\TH:i'),'Synthetic no dial',[$recreated['id']]);
    expect($blockedCampaign['eligible_contacts_snapshot']===0,'durable_dnc_excluded_at_campaign_creation');
    $blockedPreview=pilot_campaign_preview($blockedCampaign['id']);
    expect(count($blockedPreview['eligible'])===0 && $blockedPreview['excluded']===1,
        'durable_dnc_excluded_at_preview');
    $armDenied=false;
    try{pilot_campaign_arm($blockedCampaign['id']);}
    catch(InvalidArgumentException $e){$armDenied=$e->getMessage()==='no_consented_contacts';}
    expect($armDenied,'durable_dnc_prevents_arming_campaign');
    expect(pilot_campaign_claim()===null,'no_lease_for_durable_suppression');
    $corruptPath=$root.'/pilot/dnc.json';
    file_put_contents($corruptPath,'{corrupt');
    $failClosed=false;
    try{pilot_import_contacts([['name'=>'Unsafe','phone'=>'+201255566677']]);}
    catch(RuntimeException $e){$failClosed=true;}
    expect($failClosed,'corrupt_dnc_store_fails_closed');
    expect(count(pilot_read('contacts'))===1,'corrupt_dnc_did_not_write_new_contacts');
    file_put_contents($corruptPath,'{}');
    $objectDenied=false;
    try{pilot_suppression_keys();}
    catch(RuntimeException $e){$objectDenied=$e->getMessage()==='dnc_store_invalid';}
    expect($objectDenied,'empty_json_object_cannot_erase_dnc_ledger');
    pilot_transaction(static function()use($suppressed):void {pilot_atomic('dnc',$suppressed);});
    $temp=tempnam(sys_get_temp_dir(),'pilot-csv-');file_put_contents($temp,"Ahmed,+201012345678\nMona,+201112345678\n");
    $parsed=pilot_file_rows($temp,'numbers.csv');expect(count($parsed)===2 && $parsed[1]['name']==='Mona','csv_parse');
    unlink($temp);
    echo "Pilot workspace tests PASS\n";
} finally {
    if(is_dir($root.'/pilot'))foreach(glob($root.'/pilot/*')?:[] as $f)@unlink($f);
    @rmdir($root.'/pilot');@rmdir($root);
}
