<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/_core/DevicePairing.php';

function test_tenant_device_pairing():void {
    $admin=one("SELECT id FROM users WHERE email='admin@ontrackegy.com'");
    $actor=(int)$admin['id'];
    $codeA=device_pairing_create(4,1,1,$actor);
    ok((bool)preg_match('/^[A-F0-9]{4}(?:-[A-F0-9]{4}){5}$/D',$codeA),'device code long random one-time format');
    ok(!str_contains(json_encode(all('SELECT * FROM device_pairings WHERE account_id=4')),$codeA),
        'plaintext pairing secret not persisted');
    $invalidScope=false;
    try{device_pairing_create(4,3,3,$actor);}catch(InvalidArgumentException $e){$invalidScope=true;}
    ok($invalidScope,'cannot issue pairing for another tenant site/agent');
    $inst='realistic-device-install-001';
    $a=device_pairing_redeem($codeA,$inst,0,'Android test SIM 1');
    ok($a['site_id']===1 && $a['agent_id']===1 && $a['sim_slot']===0,
        'first SIM bound to tenant and fixed agent');
    $session=device_binding_authorize($a['device_token']);
    ok($session!==null && (int)$session['account_id']===4,'issued SIM token authenticates only its tenant');
    $profile=device_binding_heartbeat($session);
    ok($profile['account_id']===4 && $profile['agent_name']==='مساعد المتجر',
        'heartbeat serves correct account and agent policy');
    ok(!isset($profile['device_token'])&&!isset($profile['token_hash']),
        'heartbeat never returns token or hash');
    $replacementAgent=insert("INSERT INTO agents(account_id,site_id,name,voice,instructions,knowledge,flow_json,status,created_at)
        VALUES (?,?,?,?,?,?,?,'draft',?)",
        [4,1,'إيجنت مكالمات بديل','google.aoede','رد مختصر على المكالمة','', '{}',now()]);
    $assigned=device_binding_assign_agent(4,$a['device_id'],$replacementAgent);
    ok($assigned['agent_id']===$replacementAgent && $assigned['agent_revision']===2,
        'switch agent for same physical SIM without re-pairing');
    ok($assigned['sim_slot']===0 && $assigned['account_id']===4 && $assigned['site_id']===1,
        'SIM assignment must never move tenant site or slot');
    $freshProfile=device_binding_heartbeat($session);
    ok($freshProfile['agent_id']===$replacementAgent && $freshProfile['agent_revision']===2,
        'same old bearer refreshes changed agent revision');
    $history=all('SELECT agent_id,agent_revision FROM device_agent_revisions WHERE device_id=? ORDER BY agent_revision',
        [$a['device_id']]);
    ok(count($history)===2 && (int)$history[0]['agent_id']===1 && (int)$history[1]['agent_id']===$replacementAgent,
        'retain past agent versions for attribution without rewriting older sessions');
    $foreignSwitch=false;
    try{device_binding_assign_agent(5,$a['device_id'],$replacementAgent);}
    catch(InvalidArgumentException $e){$foreignSwitch=true;}
    ok($foreignSwitch,'cross-tenant agent switch rejected');
    $foreignAgent=false;
    try{device_binding_assign_agent(4,$a['device_id'],3);}
    catch(InvalidArgumentException $e){$foreignAgent=true;}
    ok($foreignAgent,'cross-site or cross-account agent rejected');
    $same=device_binding_assign_agent(4,$a['device_id'],$replacementAgent);
    ok($same['agent_revision']===2,'idempotent reselect does not increment revision');
    $restored=device_binding_assign_agent(4,$a['device_id'],1);
    ok($restored['agent_revision']===3 && $restored['agent_id']===1,
        'reverting agent updates next call but preserves original bearer');
    ok(device_binding_authorize($a['device_token'])!==null,
        'agent switch does not revoke active SIM bearer');

    $used=false;
    try{device_pairing_redeem($codeA,'other-install-00000000001',1,'Replay');}
    catch(InvalidArgumentException $e){$used=$e->getMessage()==='device_pairing_expired_or_used';}
    ok($used,'one-time code replay denied');
    $codeB=device_pairing_create(5,3,3,$actor);
    $sameSlot=false;
    try{device_pairing_redeem($codeB,$inst,0,'Collision');}
    catch(PDOException $e){$sameSlot=true;}
    ok($sameSlot,'same physical SIM slot cannot be claimed by second tenant');
    $b=device_pairing_redeem($codeB,$inst,1,'Android test SIM 2');
    $sessionB=device_binding_authorize($b['device_token']);
    ok($sessionB!==null && (int)$sessionB['account_id']===5 && (int)$sessionB['sim_slot']===1,
        'two SIM slots isolate separate customer accounts');
    ok(device_binding_authorize($a['device_token'])['account_id']!==$sessionB['account_id'],
        'SIM token cannot cross into another tenant');
    ok(device_binding_revoke(5,$a['device_id'])===false,'cross-tenant device revoke denied');
    ok(device_binding_revoke(4,$a['device_id'])===true,'owner scope revokes active SIM token');
    ok(device_binding_authorize($a['device_token'])===null,'revoked SIM token immediately denied');
    $replacement=device_pairing_create(4,1,1,$actor);
    $relinked=device_pairing_redeem($replacement,$inst,0,'Android relink SIM 1');
    ok($relinked['device_id']!==$a['device_id'],'revoked slot can re-pair independently');
    ok(device_binding_authorize($b['device_token'])!==null,'other account SIM untouched by revoke');
    $cancel=device_pairing_create(4,1,1,$actor);
    $pid=one('SELECT id FROM device_pairings WHERE code_hash=?',[hash('sha256',strtolower(str_replace('-','',$cancel)))]);
    ok(device_pairing_cancel(5,(int)$pid['id'])===false,'cannot cancel another tenant pairing');
    ok(device_pairing_cancel(4,(int)$pid['id'])===true,'owner scoped pairing cancellation');
    $cancelled=false;
    try{device_pairing_redeem($cancel,'other-install-00000000002',1,'Cancelled');}
    catch(InvalidArgumentException $e){$cancelled=true;}
    ok($cancelled,'cancelled pairing rejected');
    $expired=device_pairing_create(4,1,1,$actor);
    execute("UPDATE device_pairings SET expires_at='2000-01-01 00:00:00' WHERE code_hash=?",
        [hash('sha256',strtolower(str_replace('-','',$expired)))]);
    $stale=false;
    try{device_pairing_redeem($expired,'other-install-00000000003',1,'Expired');}
    catch(InvalidArgumentException $e){$stale=true;}
    ok($stale,'expired pairing rejected');
    execute("UPDATE sites SET status='archived' WHERE id=3 AND account_id=5");
    ok(device_binding_authorize($b['device_token'])===null,'archived tenant site disables SIM token');
    execute("UPDATE sites SET status='active' WHERE id=3 AND account_id=5");
    $invalidSlot=false;
    try{device_pairing_redeem(str_repeat('A',24),'realistic-device-install-001',2,'Wrong slot');}
    catch(InvalidArgumentException $e){$invalidSlot=true;}
    ok($invalidSlot,'SIM slot must be zero or one');
    execute("UPDATE accounts SET status='suspended' WHERE id=4");
    ok(device_binding_authorize($relinked['device_token'])===null,'suspended account immediately revokes usable access');
    $blockedCreate=false;
    try{device_pairing_create(4,1,1,$actor);}
    catch(InvalidArgumentException $e){$blockedCreate=true;}
    ok($blockedCreate,'suspended tenant cannot issue pairing');
    execute("UPDATE accounts SET status='active' WHERE id=4");
    echo "TENANT_SIM_PAIRING_PASS\n";
}
