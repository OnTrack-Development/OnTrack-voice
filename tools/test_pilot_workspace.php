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
    $temp=tempnam(sys_get_temp_dir(),'pilot-csv-');file_put_contents($temp,"Ahmed,+201012345678\nMona,+201112345678\n");
    $parsed=pilot_file_rows($temp,'numbers.csv');expect(count($parsed)===2 && $parsed[1]['name']==='Mona','csv_parse');
    unlink($temp);
    echo "Pilot workspace tests PASS\n";
} finally {
    if(is_dir($root.'/pilot'))foreach(glob($root.'/pilot/*')?:[] as $f)@unlink($f);
    @rmdir($root.'/pilot');@rmdir($root);
}
