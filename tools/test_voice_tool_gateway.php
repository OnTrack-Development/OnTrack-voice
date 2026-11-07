<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/VoiceToolGateway.php';
if(is_dir('/home/appontrack'))throw new RuntimeException('CI fixture only');
function tool_check(bool $passed,string $case):void {
    if(!$passed)throw new RuntimeException('SITE_TOOL_FAIL '.$case);
}
seed_console();
$site=(int)one("SELECT id FROM sites WHERE account_id=4 AND status='active' LIMIT 1")['id'];
$actor=(int)one("SELECT user_id FROM memberships WHERE account_id=4 AND role='owner' LIMIT 1")['user_id'];
$other=(int)one("SELECT user_id FROM memberships WHERE account_id=5 LIMIT 1")['user_id'];
$connector=voice_connector_add(4,$site,'whmcs','WHMCS test','https://whmcs.example.com','/includes/api.php','whmcs','id:secret');
execute("UPDATE voice_data_connectors SET status='verified' WHERE id=? AND account_id=4",[$connector]);
$policy=voice_tool_policy_save(4,$site,$connector,'whmcs.ticket.create','',true);
tool_check($policy>0,'whmcs policy added');
$guard=false;
try{voice_tool_policy_save(5,$site,$connector,'whmcs.ticket.create','',true);}
catch(InvalidArgumentException){$guard=true;}
tool_check($guard,'tenant policy prevented');
$guard=false;
try{voice_tool_policy_save(4,$site,$connector,'whmcs.service.suspend','',true);}
catch(InvalidArgumentException){$guard=true;}
tool_check($guard,'service suspension unavailable');
$guard=false;
try{voice_tool_payload('whmcs.ticket.create',['client_id'=>1,'department_id'=>2,'subject'=>'hello','message'=>'hi','action'=>'SuspendAccount']);}
catch(InvalidArgumentException){$guard=true;}
tool_check($guard,'dynamic whmcs action injection denied');
$input=['client_id'=>22,'department_id'=>1,'subject'=>'Support request','message'=>'Please help'];
$prepared=voice_tool_prepare(4,$site,$policy,$actor,'booking-voice-abcdef12345',$input,'call0012345678');
tool_check($prepared['status']==='prepared'&&strlen($prepared['approval_token'])===48,'prepare requests consent');
$reused=voice_tool_prepare(4,$site,$policy,$actor,'booking-voice-abcdef12345',$input,'call0012345678');
tool_check($reused['reused']===true&&$reused['approval_token']===null,'retry does not mint token');
$changed=false;
try{voice_tool_prepare(4,$site,$policy,$actor,'booking-voice-abcdef12345',array_merge($input,['message'=>'change']));}
catch(RuntimeException){$changed=true;}
tool_check($changed,'idempotency checks payload');
$crossCall=false;
try{voice_tool_prepare(4,$site,$policy,$actor,'booking-voice-abcdef12345',$input,'callDIFFERENT9876');}
catch(RuntimeException){$crossCall=true;}
tool_check($crossCall,'idempotency cannot cross call ids');
// A prepared approval must not silently switch to a modified endpoint or secret.
$draftRest=voice_connector_add(4,$site,'laravel','Policy drift test','https://policy.example.com','/api/status','bearer','test-token');
execute("UPDATE voice_data_connectors SET status='verified' WHERE id=? AND account_id=4",[$draftRest]);
$drift=voice_tool_policy_save(4,$site,$draftRest,'rest.slots','/api/slots',true);
$beforeChange=voice_tool_prepare(4,$site,$drift,$actor,'policy-drift-abcdef123456',
    ['date'=>'2026-12-01','service_id'=>'standard']);
voice_tool_policy_save(4,$site,$draftRest,'rest.slots','/api/availability',true);
$externalOnDrift=0;$blocked=false;
try{voice_tool_confirm(4,$site,$beforeChange['request_id'],$actor,
    $beforeChange['approval_token'],true,static function()use(&$externalOnDrift):array{
        $externalOnDrift++;return ['status'=>'succeeded'];
    });}catch(InvalidArgumentException $e){$blocked=str_contains($e->getMessage(),'policy_changed');}
tool_check($blocked&&$externalOnDrift===0,'consent tied to original REST endpoint');
voice_tool_policy_save(4,$site,$draftRest,'rest.slots','/api/slots',true);
execute("UPDATE voice_data_connectors SET secret_enc=? WHERE id=? AND account_id=4",
    [seal_secret('rotated-token'),$draftRest]);
$blocked=false;
try{voice_tool_confirm(4,$site,$beforeChange['request_id'],$actor,
    $beforeChange['approval_token'],true,static function()use(&$externalOnDrift):array{
        $externalOnDrift++;return ['status'=>'succeeded'];
    });}catch(InvalidArgumentException $e){$blocked=str_contains($e->getMessage(),'policy_changed');}
tool_check($blocked&&$externalOnDrift===0,'credential rotation invalidates old approval');
$otherPolicy=voice_tool_policy_save(4,$site,$connector,'whmcs.ticket.reply','',true);
$crossPolicy=false;
try{voice_tool_prepare(4,$site,$otherPolicy,$actor,'booking-voice-abcdef12345',
    ['ticket_id'=>55,'message'=>'x'],'call0012345678');}
catch(RuntimeException){$crossPolicy=true;}
tool_check($crossPolicy,'idempotency cannot cross operations or policies');
$denied=false;
try{voice_tool_confirm(4,$site,$prepared['request_id'],$actor,$prepared['approval_token'],false);}
catch(InvalidArgumentException){$denied=true;}
tool_check($denied,'no execution without second consent');
$denied=false;
try{voice_tool_confirm(4,$site,$prepared['request_id'],$other,$prepared['approval_token'],true);}
catch(RuntimeException|InvalidArgumentException){$denied=true;}
tool_check($denied,'cross-tenant actor blocked');
$hits=0;
$reply=voice_tool_confirm(4,$site,$prepared['request_id'],$actor,$prepared['approval_token'],true,
    static function(array $p,array $data,string $key)use(&$hits):array{
        $hits++;
        tool_check($p['operation']==='whmcs.ticket.create'&&$data['client_id']===22,'provider input pinned');
        return voice_tool_result('whmcs.ticket.create',['result'=>'success','id'=>455001]);
    });
tool_check(($reply['reference_id']??'')==='455001'&&$hits===1,'real upstream reference required');
$ticketResponse=voice_tool_result('whmcs.ticket.create',
    ['result'=>'success','id'=>'455002','tid'=>'693842']);
tool_check($ticketResponse['reference_id']==='455002'&&
    $ticketResponse['ticket_number']==='693842'&&
    $ticketResponse['reference_kind']==='new_ticket_id',
    'WHMCS internal and customer ticket identifiers distinguished');
$replyResponse=voice_tool_result('whmcs.ticket.reply',['result'=>'success']);
tool_check($replyResponse['status']==='succeeded'&&
    !isset($replyResponse['reference_id']),
    'WHMCS AddTicketReply success never invents a reply ID');
foreach([true,0,'0','not-an-id'] as $badReply){
    $invalid=false;
    try{voice_tool_result('whmcs.ticket.reply',['result'=>'success','replyid'=>$badReply]);}
    catch(RuntimeException $e){$invalid=$e->getMessage()==='tool_whmcs_reply_id_invalid';}
    tool_check($invalid,'WHMCS reply id cannot be fabricated');
}
$again=voice_tool_confirm(4,$site,$prepared['request_id'],$actor,$prepared['approval_token'],true,
    static function()use(&$hits):array{$hits++;return ['status'=>'succeeded'];});
tool_check($again['replayed']===true&&$hits===1,'single execution per request');
$fakeToken=false;
try{voice_tool_confirm(4,$site,$prepared['request_id'],$actor,str_repeat('0',48),true);}
catch(InvalidArgumentException){$fakeToken=true;}
tool_check($fakeToken,'completed request cannot disclose result with forged token');
$noId=false;
try{voice_tool_result('whmcs.ticket.create',['result'=>'success']);}
catch(RuntimeException){$noId=true;}
tool_check($noId,'http success without ticket id rejected');
$read=voice_tool_policy_save(4,$site,$connector,'whmcs.invoices','',true);
$readRequest=voice_tool_prepare(4,$site,$read,$actor,'invoice-info-abcdef12',['client_id'=>22]);
$readResult=voice_tool_confirm(4,$site,$readRequest['request_id'],$actor,$readRequest['approval_token'],true,
    static fn()=>voice_tool_result('whmcs.invoices',['result'=>'success',
        'invoices'=>['invoice'=>[['id'=>23,'status'=>'Paid','email'=>'hidden@example.com','total'=>'100.00']]]]));
tool_check($readResult['status']==='succeeded'&&$readResult['count']===1&&
    !str_contains(json_encode($readResult),'hidden@example.com'),'invoice PII redaction');
$empty=voice_tool_result('whmcs.tickets',['result'=>'success','totalresults'=>0]);
tool_check($empty['count']===0&&$empty['data']===[],'genuine zero WHMCS tickets');
$ambiguous=false;
try{voice_tool_result('whmcs.tickets',['result'=>'success','tickets'=>'broken']);}
catch(RuntimeException $e){$ambiguous=$e->getMessage()==='tool_whmcs_read_shape_invalid';}
tool_check($ambiguous,'broken WHMCS response is not invented as no tickets');
$ambiguous=false;
try{voice_tool_result('whmcs.services',['result'=>'success','totalresults'=>3,'products'=>[]]);}
catch(RuntimeException $e){$ambiguous=true;}
tool_check($ambiguous,'missing paid services cannot be presented as empty');
$rest=voice_connector_add(4,$site,'laravel','Appointment test','https://appointments.example.com','/api/slots','bearer','demo-key');
execute("UPDATE voice_data_connectors SET status='verified' WHERE id=? AND account_id=4",[$rest]);
$slotPolicy=voice_tool_policy_save(4,$site,$rest,'rest.slots','/api/slots',true);
$booking=voice_tool_policy_save(4,$site,$rest,'rest.booking.create','/api/bookings',true);
$bad=false;try{voice_tool_policy_save(4,$site,$rest,'rest.booking.create','/admin/../credentials',true);}
catch(InvalidArgumentException){$bad=true;}
tool_check($bad,'path traversal rejected');
$slots=voice_tool_prepare(4,$site,$slotPolicy,$actor,'slot-list-abcdef123456',
    ['date'=>'2026-12-01','service_id'=>'standard']);
$found=voice_tool_confirm(4,$site,$slots['request_id'],$actor,$slots['approval_token'],true,
    static fn()=>voice_tool_result('rest.slots',['success'=>true,'slots'=>[['id'=>'room-3','start'=>'2026-12-01T09:00:00']]]));
tool_check($found['count']===1&&$found['slots'][0]['id']==='room-3','available slots result');
$res=voice_tool_prepare(4,$site,$booking,$actor,'rest-book-abcdef123456',
    ['slot_id'=>'room-3','name'=>'Test Client','phone'=>'+201234567890','service_id'=>'standard']);
$unconfirmed=voice_tool_confirm(4,$site,$res['request_id'],$actor,$res['approval_token'],true,
    static fn()=>voice_tool_result('rest.booking.create',['success'=>true]));
tool_check($unconfirmed['status']==='uncertain'&&!$unconfirmed['retriable_automatically'],'missing booking reference stays uncertain');
$again=voice_tool_confirm(4,$site,$res['request_id'],$actor,$res['approval_token'],true);
tool_check($again['status']==='uncertain'&&$again['replayed']===true,'uncertain not auto-retried');
$confirmed=voice_tool_prepare(4,$site,$booking,$actor,'rest-book-abcdef654321',
    ['slot_id'=>'room-3','name'=>'Test Client','phone'=>'+201234567890','service_id'=>'standard']);
$ok=voice_tool_confirm(4,$site,$confirmed['request_id'],$actor,$confirmed['approval_token'],true,
    static fn()=>voice_tool_result('rest.booking.create',['success'=>true,'booking_id'=>'BK-1001']));
tool_check($ok['reference_id']==='BK-1001','confirmed booking ref');
tool_check(count(voice_tool_history(4,$site))>=4,'audit history');
tool_check(!str_contains(json_encode(voice_tool_history(4,$site)),'demo-key'),'no secrets in trace');
// Provider must return a real identifier, not a boolean that PHP casts to "1".
foreach ([true, false, 0, '0'] as $fakeId) {
    $refDenied=false;
    try { voice_tool_result('rest.booking.create',['success'=>true,'booking_id'=>$fakeId]); }
    catch (RuntimeException $e) { $refDenied=$e->getMessage()==='tool_booking_reference_missing'; }
    tool_check($refDenied,'booking id must be a nonzero scalar identifier');
}
foreach ([true, 0, '0'] as $fakeId) {
    $refDenied=false;
    try { voice_tool_result('whmcs.ticket.create',['result'=>'success','id'=>$fakeId]); }
    catch (RuntimeException $e) { $refDenied=$e->getMessage()==='tool_whmcs_ticket_id_missing'; }
    tool_check($refDenied,'ticket id must be a positive numeric identifier');
}
$badSlots=voice_tool_result('rest.slots',['success'=>true,'slots'=>[
    ['id'=>false,'start'=>'2026-12-01T09:00:00'],
    ['id'=>'slot-real','start'=>'2026-12-01T09:30:00']
]]);
tool_check($badSlots['count']===1&&$badSlots['slots'][0]['id']==='slot-real',
    'invalid boolean slot identifiers excluded');

// Revoke access to existing PREPARED writes when the site or tenant is stopped.
// The data stays prepared and cannot be executed while entitlement is gone.
$archiveRequest=voice_tool_prepare(4,$site,$booking,$actor,'rest-book-archive-abcdef',
    ['slot_id'=>'room-3','name'=>'Archive QA','phone'=>'+201234567890','service_id'=>'standard']);
$siteStatus=(string)one('SELECT status FROM sites WHERE id=? AND account_id=4',[$site])['status'];
$externalCalls=0;
execute("UPDATE sites SET status='archived' WHERE id=? AND account_id=4",[$site]);
try {
    $blocked=false;
    try { voice_tool_policy(4,$site,$booking); }
    catch (InvalidArgumentException $e) { $blocked=$e->getMessage()==='tool_policy_unavailable'; }
    tool_check($blocked,'archived site revokes approved policy');
    $blocked=false;
    try { voice_tool_confirm(4,$site,$archiveRequest['request_id'],$actor,
        $archiveRequest['approval_token'],true,static function()use(&$externalCalls):array {
            $externalCalls++;return ['status'=>'succeeded','reference_id'=>'should-not-exist'];
        }); }
    catch (InvalidArgumentException $e) { $blocked=$e->getMessage()==='tool_policy_unavailable'; }
    tool_check($blocked&&$externalCalls===0,'archived site cannot dispatch prepared booking');
    tool_check((string)one('SELECT status FROM voice_tool_requests WHERE id=?',
        [$archiveRequest['request_id']])['status']==='prepared',
        'blocked booking remains unexecuted');
} finally {
    execute('UPDATE sites SET status=? WHERE id=? AND account_id=4',[$siteStatus,$site]);
}
$approvedAfterSiteRestore=voice_tool_confirm(4,$site,$archiveRequest['request_id'],
    $actor,$archiveRequest['approval_token'],true,
    static fn()=>voice_tool_result('rest.booking.create',['success'=>true,'booking_id'=>'SITE-RESTORED']));
tool_check($approvedAfterSiteRestore['reference_id']==='SITE-RESTORED',
    'restored site permits explicitly confirmed, still valid booking');

$accountRequest=voice_tool_prepare(4,$site,$booking,$actor,'rest-book-suspend-abcde',
    ['slot_id'=>'room-3','name'=>'Suspension QA','phone'=>'+201234567890','service_id'=>'standard']);
$accountStatus=(string)one('SELECT status FROM accounts WHERE id=4')['status'];
execute("UPDATE accounts SET status='suspended' WHERE id=4");
try {
    $blocked=false;
    try { voice_tool_actor(4,$actor); }
    catch (RuntimeException $e) { $blocked=$e->getMessage()==='tool_actor_requires_owner_or_manager'; }
    tool_check($blocked,'suspended tenant loses tool actor rights');
    $blocked=false;
    try { voice_tool_prepare(4,$site,$booking,$actor,'rest-book-paused-abcdef',
        ['slot_id'=>'room-3','name'=>'Blocked QA','phone'=>'+201234567890','service_id'=>'standard']); }
    catch (InvalidArgumentException) { $blocked=true; }
    tool_check($blocked,'suspended tenant cannot prepare new booking');
    $blocked=false;
    try { voice_tool_confirm(4,$site,$accountRequest['request_id'],$actor,
        $accountRequest['approval_token'],true,static function()use(&$externalCalls):array {
            $externalCalls++;return ['status'=>'succeeded','reference_id'=>'should-not-exist'];
        }); }
    catch (RuntimeException) { $blocked=true; }
    tool_check($blocked&&$externalCalls===0,
        'suspended tenant cannot dispatch previously prepared booking');
} finally {
    execute('UPDATE accounts SET status=? WHERE id=4',[$accountStatus]);
}
$approvedAfterResume=voice_tool_confirm(4,$site,$accountRequest['request_id'],
    $actor,$accountRequest['approval_token'],true,
    static fn()=>voice_tool_result('rest.booking.create',['success'=>true,'booking_id'=>'TENANT-RESTORED']));
tool_check($approvedAfterResume['reference_id']==='TENANT-RESTORED',
    're-enabled tenant still needs explicit booking confirmation');
echo "VOICE_SITE_TOOL_GATEWAY_PASS\n";
