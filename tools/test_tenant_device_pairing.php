<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/_core/DevicePairing.php';

function test_tenant_device_pairing():void {
    execute("DELETE FROM device_bindings WHERE installation_id LIKE 'realistic-device-install-%'");
    execute("DELETE FROM device_pairings WHERE account_id=4");
    execute("DELETE FROM agent_flow_versions WHERE account_id=4 AND agent_id=1");
    $admin=one("SELECT id FROM users WHERE email='admin@ontrackegy.com'");
    $actor=(int)$admin['id'];
    $codeA=device_pairing_create(4,1,1,$actor,0);
    ok((bool)preg_match('/^[0-9]{6}$/D',$codeA),'device code is exactly 6 digits');
    ok(strlen($codeA)===6,'SIM 1 six-digit code does not expose slot marker');
    $ab=intdiv(time(),300);
    $storedA=one('SELECT status FROM device_pairings WHERE code_hash IN (?,?)',
        [device_pairing_hash($codeA,$ab),device_pairing_hash($codeA,$ab-1)]);
    ok($storedA['status']==='pending_sim0','database records intended SIM independently of QR');
    $wrongSlot=false;
    try{device_pairing_redeem($codeA,'wrong-slot-install-0000001',1,'Wrong SIM');}
    catch(InvalidArgumentException $e){$wrongSlot=$e->getMessage()==='device_pairing_slot_mismatch';}
    ok($wrongSlot,'SIM 1 code cannot be redeemed on SIM 2, even before first use');
    $invalidIssue=false;
    try{device_pairing_create(4,1,1,$actor,2);}catch(InvalidArgumentException $e){$invalidIssue=true;}
    ok($invalidIssue,'server rejects pairing ticket for nonexistent SIM slot');
    ok(!str_contains(json_encode(all('SELECT * FROM device_pairings WHERE account_id=4')),$codeA),
        'plaintext pairing secret not persisted');
    $invalidScope=false;
    try{device_pairing_create(4,3,3,$actor,0);}catch(InvalidArgumentException $e){$invalidScope=true;}
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
    ok($profile['account_name']===(string)account(4)['name'] && $profile['account_name']!=='',
        'heartbeat identifies the bound company without revealing credentials');
    ok((int)$profile['sim_slot']===0 && (int)$profile['device_id']===(int)$a['device_id'],
        'SIM 1 profile is bound to its own device and physical slot');
    ok((int)$profile['agent_id']===(int)$session['agent_id'] &&
        $profile['instruction']===null && $profile['voice']===null &&
        $profile['flow_ready']===false,
        'unpublished agent draft policy must not be sent to Android SIM');
    ok(!isset($profile['device_token'])&&!isset($profile['token_hash']),
        'heartbeat never returns token or hash');
    $used=false;
    try{device_pairing_redeem($codeA,'other-install-00000000001',0,'Replay');}
    catch(InvalidArgumentException $e){$used=$e->getMessage()==='device_pairing_expired_or_used';}
    ok($used,'one-time code replay denied');
    $legacy=device_pairing_create(4,1,1,$actor,0);
    $lb=intdiv(time(),300);
    execute("UPDATE device_pairings SET status='pending' WHERE code_hash IN (?,?)",
        [device_pairing_hash($legacy,$lb),device_pairing_hash($legacy,$lb-1)]);
    $legacyDenied=false;
    try{device_pairing_redeem($legacy,'legacy-install-000000001',0,'Legacy ticket');}
    catch(InvalidArgumentException $e){$legacyDenied=$e->getMessage()==='device_pairing_expired_or_used';}
    ok($legacyDenied,'pre-upgrade ambiguous ticket cannot bind a SIM after rollout');
    $codeB0=device_pairing_create(5,3,3,$actor,0);
    $codeB=device_pairing_create(5,3,3,$actor,1);
    ok((bool)preg_match('/^[0-9]{6}$/D',$codeB),'SIM 2 gets numeric code; server locks slot independently');
    $sameSlot=false;
    try{device_pairing_redeem($codeB0,$inst,0,'Collision');}
    catch(PDOException $e){$sameSlot=true;}
    ok($sameSlot,'same physical SIM slot cannot be claimed by second tenant');
    $wrongB=false;
    try{device_pairing_redeem($codeB,$inst,0,'Wrong tenant slot');}
    catch(InvalidArgumentException $e){$wrongB=$e->getMessage()==='device_pairing_slot_mismatch';}
    ok($wrongB,'SIM 2 ticket cannot be used to overwrite SIM 1 tenant binding');
    $b=device_pairing_redeem($codeB,$inst,1,'Android test SIM 2');
    $sessionB=device_binding_authorize($b['device_token']);
    ok($sessionB!==null && (int)$sessionB['account_id']===5 && (int)$sessionB['sim_slot']===1,
        'two SIM slots isolate separate customer accounts');
    $profileB=device_binding_profile($sessionB);
    ok((int)$profileB['device_id']===(int)$b['device_id'] && (int)$profileB['sim_slot']===1 &&
        (int)$profileB['agent_id']===(int)$sessionB['agent_id'] &&
        (int)$profileB['account_id']!== (int)$profile['account_id'],
        'SIM 2 receives a separate tenant and agent profile');
    ok(device_binding_authorize($a['device_token'])['account_id']!==$sessionB['account_id'],
        'SIM token cannot cross into another tenant');
    ok(device_binding_revoke(5,$a['device_id'])===false,'cross-tenant device revoke denied');
    // A queued campaign belongs only to this SIM. Revocation pauses it and
    // makes its in-flight attempt forever uncertain, never reassignable.
    require_once __DIR__.'/../saas/console/_core/TenantCampaignQueue.php';
    tcq_init();
    $campaignKey=bin2hex(random_bytes(12));
    insert("INSERT INTO tenant_campaigns(id,account_id,site_id,device_binding_id,sim_slot,
        name,starts_at,ends_at,scenario,contact_ids_json,status,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",[
        $campaignKey,4,1,$a['device_id'],0,'هاتف قيد الفصل',
        gmdate('Y-m-d H:i:s',time()-60),gmdate('Y-m-d H:i:s',time()+3600),
        'demo','[]','ready',now()
    ]);
    $attemptKey=bin2hex(random_bytes(16));
    insert("INSERT INTO tenant_campaign_attempts(id,campaign_id,account_id,site_id,
        device_binding_id,sim_slot,contact_id,phone_key,status,leased_epoch)
        VALUES(?,?,?,?,?,?,?,?,?,?)",[
        $attemptKey,$campaignKey,4,1,$a['device_id'],0,12345,'01099999999','leased',time()
    ]);
    ok(device_binding_revoke(4,$a['device_id'])===true,'owner scope revokes active SIM token');
    $oldCampaign=one('SELECT status FROM tenant_campaigns WHERE id=?',[$campaignKey]);
    $uncertain=one('SELECT status,result FROM tenant_campaign_attempts WHERE id=?',[$attemptKey]);
    ok($oldCampaign['status']==='paused' && $uncertain['status']==='unknown' &&
        $uncertain['result']==='device_revoked',
        'G1: SIM revoke halts campaign and invalidates in-flight GSM lease');
    execute('DELETE FROM tenant_campaign_attempts WHERE id=?',[$attemptKey]);
    execute('DELETE FROM tenant_campaigns WHERE id=?',[$campaignKey]);
    ok(device_binding_authorize($a['device_token'])===null,'revoked SIM token immediately denied');
    $staleHeartbeatDenied=false;
    try{device_binding_heartbeat($session);}
    catch(RuntimeException $e){$staleHeartbeatDenied=$e->getMessage()==='device_binding_revoked';}
    ok($staleHeartbeatDenied,'in-flight stale heartbeat cannot disclose revoked SIM agent profile');
    $replacement=device_pairing_create(4,1,1,$actor,0);
    $relinked=device_pairing_redeem($replacement,$inst,0,'Android relink SIM 1');
    ok($relinked['device_id']!==$a['device_id'],'revoked slot can re-pair independently');
    ok(device_binding_authorize($b['device_token'])!==null,'other account SIM untouched by revoke');
    $cancel=device_pairing_create(4,1,1,$actor,0);
    $cb=intdiv(time(),300);
    $pid=one('SELECT id FROM device_pairings WHERE code_hash IN (?,?)',
        [device_pairing_hash($cancel,$cb),device_pairing_hash($cancel,$cb-1)]);
    ok(device_pairing_cancel(5,(int)$pid['id'])===false,'cannot cancel another tenant pairing');
    ok(device_pairing_cancel(4,(int)$pid['id'])===true,'owner scoped pairing cancellation');
    $cancelled=false;
    try{device_pairing_redeem($cancel,'other-install-00000000002',0,'Cancelled');}
    catch(InvalidArgumentException $e){$cancelled=true;}
    ok($cancelled,'cancelled pairing rejected');
    $expired=device_pairing_create(4,1,1,$actor,0);
    $eb=intdiv(time(),300);
    execute("UPDATE device_pairings SET expires_at='2000-01-01 00:00:00' WHERE code_hash IN (?,?)",
        [device_pairing_hash($expired,$eb),device_pairing_hash($expired,$eb-1)]);
    $stale=false;
    try{device_pairing_redeem($expired,'other-install-00000000003',0,'Expired');}
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
    try{device_pairing_create(4,1,1,$actor,0);}
    catch(InvalidArgumentException $e){$blockedCreate=true;}
    ok($blockedCreate,'suspended tenant cannot issue pairing');
    execute("UPDATE accounts SET status='active' WHERE id=4");
    ok(device_binding_disconnect($sessionB)===true,'authenticated SIM can disconnect itself');
    ok(device_binding_authorize($b['device_token'])===null,'disconnected SIM token rejected immediately');
    ok(device_binding_authorize($relinked['device_token'])!==null,'other customer SIM unaffected by disconnect');
    ok(device_binding_disconnect($sessionB)===false,'repeat disconnect rejected');
    $repaired=device_pairing_create(5,3,3,$actor,1);
    $newB=device_pairing_redeem($repaired,$inst,1,'SIM 2 after disconnect');
    ok($newB['device_id']!==$b['device_id'] && device_binding_authorize($newB['device_token'])!==null,
        'disconnected slot can enroll again without changing other slot');
    echo "TENANT_SIM_PAIRING_PASS\n";
}
