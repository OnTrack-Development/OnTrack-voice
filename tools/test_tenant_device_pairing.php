<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/_core/DevicePairing.php';

function test_tenant_device_pairing():void {
    $admin=one("SELECT id FROM users WHERE email='admin@ontrackegy.com'");
    $actor=(int)$admin['id'];
    $codeA=device_pairing_create(4,1,1,$actor,0);
    ok((bool)preg_match('/^[A-F0-9]{4}(?:-[A-F0-9]{4}){5}$/D',$codeA),'device code long random one-time format');
    ok($codeA[0]==='0','SIM 1 code carries server-locked slot marker');
    $storedA=one('SELECT status FROM device_pairings WHERE code_hash=?',
        [hash('sha256',strtolower(str_replace('-','',$codeA)))]);
    ok($storedA['status']==='pending_sim0','database records intended SIM independently of QR');
    $legacy=device_pairing_create(4,1,1,$actor,0);
    execute("UPDATE device_pairings SET status='pending' WHERE code_hash=?",
        [hash('sha256',strtolower(str_replace('-','',$legacy)))]);
    $legacyDenied=false;
    try{device_pairing_redeem($legacy,'legacy-install-000000001',0,'Legacy ticket');}
    catch(InvalidArgumentException $e){$legacyDenied=$e->getMessage()==='device_pairing_expired_or_used';}
    ok($legacyDenied,'pre-upgrade ambiguous ticket cannot bind a SIM after rollout');
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
    $initialHistory=one('SELECT agent_id FROM device_agent_revisions WHERE device_id=? AND agent_revision=1',[$a['device_id']]);
    ok((int)$initialHistory['agent_id']===1,'SIM1 initial agent assignment is durably versioned');
    $profile=device_binding_heartbeat($session);
    $sameSecond=device_binding_heartbeat($session);
    ok($sameSecond['device_id']===$profile['device_id'],
        'repeated heartbeat in same second keeps a valid SIM binding active');
    ok($profile['account_id']===4 && $profile['agent_name']==='مساعد المتجر',
        'heartbeat serves correct account and agent policy');
    ok($profile['account_name']===(string)account(4)['name'] && $profile['account_name']!=='',
        'heartbeat identifies the bound company without revealing credentials');
    ok((int)$profile['sim_slot']===0 && (int)$profile['device_id']===(int)$a['device_id'],
        'SIM 1 profile is bound to its own device and physical slot');
    ok((int)$profile['agent_id']===(int)$session['agent_id'] &&
        $profile['instruction']===(string)one('SELECT instructions FROM agents WHERE id=? AND account_id=? AND site_id=?',
            [$session['agent_id'],$session['account_id'],$session['site_id']])['instructions'],
        'SIM 1 instructions originate from its tenant-scoped agent');
    ok(!isset($profile['device_token'])&&!isset($profile['token_hash']),
        'heartbeat never returns token or hash');
    $used=false;
    try{device_pairing_redeem($codeA,'other-install-00000000001',0,'Replay');}
    catch(InvalidArgumentException $e){$used=$e->getMessage()==='device_pairing_expired_or_used';}
    ok($used,'one-time code replay denied');
    $codeB0=device_pairing_create(5,3,3,$actor,0);
    $codeB=device_pairing_create(5,3,3,$actor,1);
    ok($codeB[0]==='1','SIM 2 code carries server-locked slot marker');
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
    // Tenant SIM contact browsing uses the actual contact DB and durable DNC.
    $contactA=insert('INSERT INTO contacts(account_id,site_id,name,phone,dnc,created_at) VALUES (?,?,?,?,?,?)',
        [4,1,'SIM1 directory example','+201012345678',0,now()]);
    $contactB=insert('INSERT INTO contacts(account_id,site_id,name,phone,dnc,created_at) VALUES (?,?,?,?,?,?)',
        [5,3,'SIM2 directory example','+201099999999',0,now()]);
    $aContacts=device_contact_list($session,'SIM1 directory');
    ok(count($aContacts)===1 && $aContacts[0]['id']===$contactA &&
       $aContacts[0]['phone']==='+201012345678',
       'SIM1 reads only its own tenant and site contacts');
    $bContacts=device_contact_list($sessionB,'SIM2 directory');
    ok(count($bContacts)===1 && $bContacts[0]['id']===$contactB &&
       $bContacts[0]['phone']==='+201099999999',
       'SIM2 reads only its other tenant contact directory');
    ok(count(device_contact_list($session,'SIM2 directory'))===0 &&
       count(device_contact_list($sessionB,'SIM1 directory'))===0,
       'SIM bearers cannot search another company directory');
    dnc_record_suppression(4,'+201012345678');
    ok(device_contact_list($session,'SIM1 directory')[0]['do_not_call']===true &&
       device_contact_list($sessionB,'SIM2 directory')[0]['do_not_call']===false,
       'DNC status reflects only the matching account durable ledger');
    $invalidSearch=false;
    try { device_contact_list($session,'%'); } catch(InvalidArgumentException $e) {
        $invalidSearch=$e->getMessage()==='device_contacts_invalid_search';
    }
    ok($invalidSearch,'SIM contact search rejects wildcard patterns');
    // Actual Android telephony observations, without invented numbers or audio.
    $callA='01234567-89ab-4cde-8f01-23456789abcd';
    $callB='12345678-9abc-4def-8abc-123456789abc';
    $timestamp=gmdate('Y-m-d\\TH:i:s\\Z');
    $event=['sim_slot'=>0,'device_id'=>(int)$a['device_id'],'call_id'=>$callA,'phase'=>'ringing',
        'duration_seconds'=>0,'occurred_at'=>$timestamp];
    $created=device_call_event($session,$event);
    ok($created['duplicate']===false && $created['event_id']>0,'SIM1 call ringing event recorded');
    $replayed=device_call_event($session,$event);
    ok($replayed['duplicate']===true && $replayed['event_id']===$created['event_id'],
        'offline retries do not duplicate a call phase');
    $event['phase']='active';
    ok(device_call_event($session,$event)['duplicate']===false,'SIM1 answered event recorded');
    $event['phase']='ended';$event['duration_seconds']=43;
    ok(device_call_event($session,$event)['duplicate']===false,'SIM1 ended duration recorded');
    $stored=one('SELECT account_id,site_id,agent_id,device_id,sim_slot,duration_seconds FROM tenant_call_events WHERE device_id=? AND call_id=? AND phase=?',
        [$a['device_id'],$callA,'ended']);
    ok((int)$stored['account_id']===4 && (int)$stored['site_id']===1 &&
        (int)$stored['agent_id']===1 && (int)$stored['sim_slot']===0 &&
        (int)$stored['duration_seconds']===43,'call record derives tenant, site and agent from SIM token');
    $missedId='23456789-abcd-4def-8abc-23456789abcd';
    $missed=['sim_slot'=>0,'device_id'=>(int)$a['device_id'],'call_id'=>$missedId,
        'phase'=>'ringing','duration_seconds'=>0,'occurred_at'=>$timestamp];
    device_call_event($session,$missed);
    $missed['phase']='ended';
    ok(device_call_event($session,$missed)['duplicate']===false,
        'unanswered SIM call is persisted with zero connected seconds');
    $crossSlot=false;
    try{device_call_event($sessionB,$event);}catch(InvalidArgumentException $e){$crossSlot=true;}
    ok($crossSlot,'SIM2 bearer cannot write SIM1 event');
    $wrongBinding=$event;$wrongBinding['device_id']=(int)$b['device_id'];
    $crossBinding=false;
    try{device_call_event($session,$wrongBinding);}catch(InvalidArgumentException $e){$crossBinding=true;}
    ok($crossBinding,'queued event from another binding cannot be attributed to this tenant');
    $other=['sim_slot'=>1,'device_id'=>(int)$b['device_id'],'call_id'=>$callA,'phase'=>'active',
        'duration_seconds'=>0,'occurred_at'=>$timestamp];
    ok(device_call_event($sessionB,$other)['duplicate']===false,
        'same call UUID on SIM2 remains separate from SIM1');
    $historyA=device_call_history($session);
    $historyB=device_call_history($sessionB);
    ok(count($historyA)===2 && count($historyB)===1,
        'native call history API scopes to original SIM binding only');
    ok($historyA[0]['state']==='unanswered' && $historyA[0]['duration_seconds']===0,
        'missed call appears as unanswered on mobile');
    ok($historyB[0]['state']==='active' && $historyB[0]['call_id']===$callA,
        'SIM2 cannot read SIM1 call rows even with colliding call UUID');
    ok((int)one('SELECT COUNT(*) n FROM tenant_call_events WHERE account_id=4')['n']===5 &&
       (int)one('SELECT COUNT(*) n FROM tenant_call_events WHERE account_id=5')['n']===1,
       'call history rows are strictly isolated by account');
    // Human reports attach only to observed, ended calls on the exact tenant SIM.
    $manual=tenant_call_report_save(4,(int)$a['device_id'],$callA,
        'satisfied','عميل راضٍ حسب مراجعة الموظف','device');
    ok($manual['outcome']==='satisfied' && $manual['source']==='device',
        'paired SIM can save explicit human outcome after ended call');
    $historyWithReport=device_call_history($session);
    $reported=array_values(array_filter($historyWithReport,
        static fn(array $item):bool=>$item['call_id']===$callA));
    ok(count($reported)===1 && $reported[0]['report']['note']==='عميل راضٍ حسب مراجعة الموظف',
        'mobile history shows only the manually entered report');
    tenant_call_report_save(4,(int)$a['device_id'],$callA,
        'follow_up','مراجعة بشرية لاحقة','web',$actor);
    $savedReport=one('SELECT outcome,source,actor_user_id FROM tenant_call_reports WHERE account_id=? AND device_id=? AND call_id=?',
        [4,$a['device_id'],$callA]);
    ok($savedReport['outcome']==='follow_up' && $savedReport['source']==='web' &&
        (int)$savedReport['actor_user_id']===$actor,
        'web editor updates existing report with real user actor and no duplicate');
    ok((int)one('SELECT COUNT(*) n FROM tenant_call_reports WHERE account_id=4 AND device_id=?',
        [$a['device_id']])['n']===1,'report update is idempotent per call and device');
    $wrongTenantReport=false;
    try{tenant_call_report_save(5,(int)$a['device_id'],$callA,'satisfied','','device');}
    catch(InvalidArgumentException $e){$wrongTenantReport=true;}
    ok($wrongTenantReport,'SIM2 tenant cannot annotate SIM1 device or call');
    $unfinishedReport=false;
    try{tenant_call_report_save(5,(int)$b['device_id'],$callA,'satisfied','','device');}
    catch(InvalidArgumentException $e){$unfinishedReport=true;}
    ok($unfinishedReport,'active call cannot receive a fabricated finished-call report');
    $invalidOutcome=false;
    try{tenant_call_report_save(4,(int)$a['device_id'],$callA,'do_not_call','','device');}
    catch(InvalidArgumentException $e){$invalidOutcome=true;}
    ok($invalidOutcome,'manual disposition cannot pretend to apply DNC without verified number');
    // A human opt-out requires a manually supplied number; it cannot be inferred
    // from call-state events, and must survive report edits and duplicate retries.
    $noConfirm=false;
    try{tenant_call_report_save(4,(int)$a['device_id'],$callA,'do_not_call','','device',null,'01012345678');}
    catch(InvalidArgumentException $e){$noConfirm=true;}
    ok($noConfirm,'SIM cannot suppress a number without explicit human confirmation');
    $badPhone=false;
    try{tenant_call_report_save(4,(int)$a['device_id'],$callA,'do_not_call','','device',null,'123',true);}
    catch(InvalidArgumentException $e){$badPhone=true;}
    ok($badPhone,'SIM cannot suppress an invalid or unverified phone key');
    $optOut=tenant_call_report_save(4,(int)$a['device_id'],$callA,
        'do_not_call','العميل طلب عدم الاتصال','device',null,'01012345678',true);
    ok($optOut['dnc_suppressed']===true && dnc_is_suppressed(4,'+201012345678'),
        'human-confirmed SIM report creates durable account-scoped DNC suppression');
    ok(!dnc_is_suppressed(5,'+201012345678'),
        'other tenant remains unaffected by a SIM opt-out');
    tenant_call_report_save(4,(int)$a['device_id'],$callA,
        'do_not_call','إعادة إرسال','device',null,'+201012345678',true);
    ok((int)one('SELECT COUNT(*) n FROM dnc_suppressions WHERE account_id=? AND phone_key=?',
        [4,dnc_phone_key('01012345678')])['n']===1,
        'repeat call opt-out is idempotent');
    tenant_call_report_save(4,(int)$a['device_id'],$callA,'satisfied','','web',$actor);
    $dncContact=insert('INSERT INTO contacts(account_id,site_id,name,phone,dnc,created_at) VALUES (?,?,?,?,?,?)',
        [4,1,'عميل طلب الحظر','01012345678',0,now()]);
    require_once __DIR__.'/../saas/console/_core/Views.php';
    ob_start();render_contacts(['account_id'=>4,'role'=>'owner','kind'=>'customer']);
    $contactHtml=(string)ob_get_clean();
    ok(str_contains($contactHtml,'عميل طلب الحظر') && str_contains($contactHtml,'DNC'),
        'contact UI reflects call-derived DNC ledger even if old contact flag is zero');
    ok(dnc_is_suppressed(4,'01012345678'),
        'later report changes cannot erase an existing DNC suppression');
    $missedReport=tenant_call_report_save(4,(int)$a['device_id'],$missedId,
        'unknown','لم يتم الرد','web',$actor);
    ok($missedReport['outcome']==='unknown',
        'missed call may carry a human note without fabricated sentiment');
    // Render the real tenant console: reports must be escaped and isolated.
    tenant_call_report_save(4,(int)$a['device_id'],$callA,
        'satisfied','<script>alert(1)</script>','web',$actor);
    require_once __DIR__.'/../saas/console/_core/Views.php';
    $oldSession=$_SESSION;
    $_SESSION=['uid'=>$actor,'account_id'=>1,
        'support'=>['account_id'=>4,'origin_id'=>1,'expires'=>time()+900]];
    ob_start();render_calls(mine());$account4Html=(string)ob_get_clean();
    ok(str_contains($account4Html,'save_tenant_call_report') &&
        str_contains($account4Html,'&lt;script&gt;') &&
        !str_contains($account4Html,'<script>alert(1)</script>'),
        'real web call report editor is available and escapes staff-entered notes');
    $_SESSION['support']['account_id']=5;
    ob_start();render_calls(mine());$account5Html=(string)ob_get_clean();
    ok(!str_contains($account5Html,'&lt;script&gt;') &&
        !str_contains($account5Html,'مراجعة بشرية لاحقة'),
        'web call reports do not leak across tenant accounts');
    $_SESSION=$oldSession;
    tenant_call_report_save(4,(int)$a['device_id'],$callA,
        'follow_up','مراجعة بشرية لاحقة','web',$actor);
    // Reassign SIM1 to a new same-site agent without touching SIM2 or re-pairing.
    $newAgent=insert("INSERT INTO agents(account_id,site_id,name,voice,instructions,knowledge,flow_json,status,created_at)
        VALUES (?,?,?,?,?,?,?,'draft',?)",
        [4,1,'إيجنت مبيعات جديد','ar-EG','تعليمات جديدة','','{}',now()]);
    $badTenant=false;
    try{device_binding_assign_agent(5,(int)$a['device_id'],$newAgent);}
    catch(InvalidArgumentException $e){$badTenant=true;}
    ok($badTenant,'other tenant cannot reassign SIM');
    $badSite=false;
    try{device_binding_assign_agent(4,(int)$a['device_id'],3);}
    catch(InvalidArgumentException $e){$badSite=true;}
    ok($badSite,'agent from another site cannot be assigned');
    $phaseId='3456789a-bcde-4fab-8abc-3456789abcde';
    device_call_event($session,['sim_slot'=>0,'device_id'=>(int)$a['device_id'],
        'call_id'=>$phaseId,'phase'=>'ringing','agent_revision'=>1,'duration_seconds'=>0,'occurred_at'=>$timestamp]);
    $assigned=device_binding_assign_agent(4,(int)$a['device_id'],$newAgent);
    ok((int)one('SELECT agent_id FROM device_agent_revisions WHERE device_id=? AND agent_revision=2',
        [$a['device_id']])['agent_id']===$newAgent,'SIM agent revision 2 maps to new agent');
    ok($assigned['agent_id']===$newAgent && $assigned['agent_revision']===2,
        'existing SIM accepts new same-site agent and increments revision');
    ok(device_binding_assign_agent(4,(int)$a['device_id'],$newAgent)['agent_revision']===2,
        'repeated same agent assignment is idempotent');
    $freshProfile=device_binding_heartbeat($session);
    ok($freshProfile['agent_id']===$newAgent && $freshProfile['instruction']==='تعليمات جديدة',
        'same bearer gets new agent instructions on heartbeat');
    ok(device_binding_heartbeat($sessionB)['agent_id']===(int)$b['agent_id'],
        'SIM2 in another account is unchanged');
    device_call_event($session,['sim_slot'=>0,'device_id'=>(int)$a['device_id'],
        'call_id'=>$phaseId,'phase'=>'active','agent_revision'=>1,'duration_seconds'=>0,'occurred_at'=>$timestamp]);
    device_call_event($session,['sim_slot'=>0,'device_id'=>(int)$a['device_id'],
        'call_id'=>$phaseId,'phase'=>'ended','agent_revision'=>1,'duration_seconds'=>3,'occurred_at'=>$timestamp]);
    $phases=all('SELECT agent_id FROM tenant_call_events WHERE device_id=? AND call_id=?',
        [$a['device_id'],$phaseId]);
    ok(count($phases)===3 && count(array_filter($phases,
        static fn(array $row):bool=>(int)$row['agent_id']!==1))===0,
        'ongoing call phases remain pinned to original agent');
    $freshId='456789ab-cdef-4abc-8abc-456789abcdef';
    device_call_event($session,['sim_slot'=>0,'device_id'=>(int)$a['device_id'],
        'call_id'=>$freshId,'phase'=>'ringing','agent_revision'=>2,'duration_seconds'=>0,'occurred_at'=>$timestamp]);
    ok((int)one('SELECT agent_id FROM tenant_call_events WHERE device_id=? AND call_id=?',
        [$a['device_id'],$freshId])['agent_id']===$newAgent,
        'new call belongs to reassigned agent');
    // An offline call observed under revision 1 must not be assigned to revision 2.
    $delayedId='56789abc-def0-4abc-8abc-56789abcdef0';
    $delayed=['sim_slot'=>0,'device_id'=>(int)$a['device_id'],'call_id'=>$delayedId,
        'phase'=>'ringing','agent_revision'=>1,'duration_seconds'=>0,'occurred_at'=>$timestamp];
    device_call_event($session,$delayed);
    ok((int)one('SELECT agent_id FROM tenant_call_events WHERE device_id=? AND call_id=?',
        [$a['device_id'],$delayedId])['agent_id']===1,
        'offline call remains attributed to historical SIM agent after reassignment');
    $delayed['phase']='ended';
    device_call_event($session,$delayed);
    $delayed['agent_revision']=2;
    $wrongRevision=false;
    try{device_call_event($session,$delayed);}catch(InvalidArgumentException $e){$wrongRevision=true;}
    ok($wrongRevision,'one call cannot switch agent revisions between phases or retries');
    $unknownRevision=false;
    try{device_call_event($session,['sim_slot'=>0,'device_id'=>(int)$a['device_id'],
        'call_id'=>'6789abcd-ef01-4abc-8abc-6789abcdef01','phase'=>'ringing',
        'agent_revision'=>999,'duration_seconds'=>0,'occurred_at'=>$timestamp]);}
    catch(InvalidArgumentException $e){$unknownRevision=true;}
    ok($unknownRevision,'unknown SIM agent revision cannot be fabricated');
    $legacyAfterReassign=false;
    try{device_call_event($session,['sim_slot'=>0,'device_id'=>(int)$a['device_id'],
        'call_id'=>'789abcde-f012-4abc-8abc-789abcdef012','phase'=>'ringing',
        'duration_seconds'=>0,'occurred_at'=>$timestamp]);}
    catch(InvalidArgumentException $e){$legacyAfterReassign=true;}
    ok($legacyAfterReassign,'old Android without revision cannot misattribute new calls after agent switch');
    $history=device_call_history($session);
    ok(count($history)>0 && isset($history[0]['agent_name']),
        'Android call history includes agent pinned to first call phase');
    ob_start();render_devices(['account_id'=>4,'role'=>'owner','kind'=>'customer']);
    $deviceHtml=(string)ob_get_clean();
    ok(str_contains($deviceHtml,'assign_device_agent') &&
        str_contains($deviceHtml,'إيجنت مبيعات جديد'),
        'tenant UI renders per-SIM agent selector');
    $withoutStart=false;
    try{device_call_event($sessionB,['sim_slot'=>1,'device_id'=>(int)$b['device_id'],'call_id'=>$callB,
        'phase'=>'ended','duration_seconds'=>1,'occurred_at'=>$timestamp]);}
    catch(InvalidArgumentException $e){$withoutStart=true;}
    ok($withoutStart,'server refuses fabricated end without start');
    $future=false;
    try{device_call_event($sessionB,['sim_slot'=>1,'device_id'=>(int)$b['device_id'],'call_id'=>$callB,
        'phase'=>'active','duration_seconds'=>0,'occurred_at'=>'2038-01-01T00:00:00Z']);}
    catch(InvalidArgumentException $e){$future=true;}
    ok($future,'server refuses unbounded device clock timestamps');
    ok(device_binding_authorize($a['device_token'])['account_id']!==$sessionB['account_id'],
        'SIM token cannot cross into another tenant');
    ok(device_binding_revoke(5,$a['device_id'])===false,'cross-tenant device revoke denied');
    ok(device_binding_revoke(4,$a['device_id'])===true,'owner scope revokes active SIM token');
    ok(device_binding_authorize($a['device_token'])===null,'revoked SIM token immediately denied');
    $revokedEventDenied=false;
    try{device_call_event($session,['sim_slot'=>0,'device_id'=>(int)$a['device_id'],'call_id'=>$callA,'phase'=>'active',
        'duration_seconds'=>0,'occurred_at'=>$timestamp]);}
    catch(RuntimeException $e){$revokedEventDenied=$e->getMessage()==='device_binding_revoked';}
    ok($revokedEventDenied,'stale SIM session cannot write events after revocation');
    $staleHeartbeatDenied=false;
    try{device_binding_heartbeat($session);}
    catch(RuntimeException $e){$staleHeartbeatDenied=$e->getMessage()==='device_binding_revoked';}
    ok($staleHeartbeatDenied,'in-flight stale heartbeat cannot disclose revoked SIM agent profile');
    $revokedContactsDenied=false;
    try{device_contact_list($session,'SIM1 directory');}
    catch(RuntimeException $e){$revokedContactsDenied=$e->getMessage()==='device_binding_revoked';}
    ok($revokedContactsDenied,'revoked SIM token cannot browse tenant contacts');
    $revokedHistoryDenied=false;
    try{device_call_history($session);}
    catch(RuntimeException $e){$revokedHistoryDenied=$e->getMessage()==='device_binding_revoked';}
    ok($revokedHistoryDenied,'revoked SIM cannot read prior call history');
    $revokedReportDenied=false;
    try{tenant_call_report_save(4,(int)$a['device_id'],$callA,'satisfied','','device');}
    catch(InvalidArgumentException $e){$revokedReportDenied=true;}
    ok($revokedReportDenied,'revoked SIM cannot write a new human report');
    $historicalWebReport=tenant_call_report_save(4,(int)$a['device_id'],$callA,
        'unsatisfied','مراجعة بشرية بعد الفصل','web',$actor);
    ok($historicalWebReport['outcome']==='unsatisfied',
        'authorized web staff can annotate historical calls after device revoke');
    $replacement=device_pairing_create(4,1,1,$actor,0);
    $relinked=device_pairing_redeem($replacement,$inst,0,'Android relink SIM 1');
    ok($relinked['device_id']!==$a['device_id'],'revoked slot can re-pair independently');
    ok(device_binding_authorize($b['device_token'])!==null,'other account SIM untouched by revoke');
    $cancel=device_pairing_create(4,1,1,$actor,0);
    $pid=one('SELECT id FROM device_pairings WHERE code_hash=?',[hash('sha256',strtolower(str_replace('-','',$cancel)))]);
    ok(device_pairing_cancel(5,(int)$pid['id'])===false,'cannot cancel another tenant pairing');
    ok(device_pairing_cancel(4,(int)$pid['id'])===true,'owner scoped pairing cancellation');
    $cancelled=false;
    try{device_pairing_redeem($cancel,'other-install-00000000002',0,'Cancelled');}
    catch(InvalidArgumentException $e){$cancelled=true;}
    ok($cancelled,'cancelled pairing rejected');
    $expired=device_pairing_create(4,1,1,$actor,0);
    execute("UPDATE device_pairings SET expires_at='2000-01-01 00:00:00' WHERE code_hash=?",
        [hash('sha256',strtolower(str_replace('-','',$expired)))]);
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
