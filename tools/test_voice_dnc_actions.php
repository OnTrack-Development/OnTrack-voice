<?php
declare(strict_types=1);
function dnc_run_action_fixture(string $action,int $contactId,string $phone):void {
    $cmd=[PHP_BINARY,__DIR__.'/dnc_action_fixture.php',$action,(string)$contactId,$phone];
    $proc=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($proc))throw new RuntimeException('dnc_action_proc');
    fclose($pipes[0]);
    $stdout=stream_get_contents($pipes[1]);fclose($pipes[1]);
    $stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
    $exit=proc_close($proc);
    if($exit!==0)throw new RuntimeException('dnc_action_exit: '.$exit.' '.substr($stderr,0,300).' '.substr($stdout,0,100));
}
function test_voice_dnc_actions():void {
    $original=insert("INSERT INTO contacts(account_id,site_id,name,phone,dnc,created_at)
        VALUES (4,2,'Opted out',?,1,?)",['+201299900001',now()]);
    dnc_run_action_fixture('add_contact',0,'01299900001');
    $copy=one("SELECT id,dnc FROM contacts WHERE account_id=4 AND site_id=1 AND phone='01299900001' ORDER BY id DESC LIMIT 1");
    api_http_assert((int)($copy['dnc']??0)===1,
        'real add_contact action preserves DNC from another site');

    dnc_run_action_fixture('edit_contact',$original,'01299900001');
    $after=one('SELECT phone,dnc FROM contacts WHERE id=?',[$original]);
    api_http_assert((int)$after['dnc']===1,
        'real edit_contact action cannot silently uncheck an opted-out record');

    dnc_run_action_fixture('edit_contact',$original,'01299900002');
    $after=one('SELECT phone,dnc FROM contacts WHERE id=?',[$original]);
    api_http_assert((int)$after['dnc']===1 && dnc_phone_key((string)$after['phone'])===dnc_phone_key('+201299900001'),
        'real edit_contact action cannot change opted-out phone and lose original suppression');

    dnc_run_action_fixture('delete_contact',$original,'01299900001');
    api_http_assert((int)one('SELECT COUNT(*) n FROM contacts WHERE id=?',[$original])['n']===1,
        'real delete_contact action cannot erase only DNC evidence');

    dnc_run_action_fixture('add_contact',0,'01299900003');
    $fresh=one("SELECT dnc FROM contacts WHERE account_id=4 AND phone='01299900003' ORDER BY id DESC LIMIT 1");
    api_http_assert((int)($fresh['dnc']??1)===0,
        'real add_contact action still creates unrelated consented contact');

    insert("INSERT INTO contacts(account_id,site_id,name,phone,dnc,created_at)
        VALUES (5,3,'Other tenant DNC',?,1,?)",['+201299900004',now()]);
    dnc_run_action_fixture('add_contact',0,'01299900004');
    $isolated=one("SELECT dnc FROM contacts WHERE account_id=4 AND phone='01299900004' ORDER BY id DESC LIMIT 1");
    api_http_assert((int)($isolated['dnc']??1)===0,
        'real add_contact action never inherits another tenant DNC');
    echo "VOICE_DNC_ACTIONS_PASS\n";
}
