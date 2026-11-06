<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/VoiceAccess.php';
function assert_voice(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('VOICE_ACCESS_FAIL: '.$label);
    echo "PASS: $label\n";
}
if(is_dir('/home/appontrack'))throw new RuntimeException('CI-only test; never modify hosted account data');
seed_console();
assert_voice(in_array('reception',array_keys([
    'reception'=>voice_role_scopes('reception')])), 'reception role exists');
assert_voice(in_array('finance',voice_role_scopes('manager'),true),'manager has all read-only business categories');
$owner=one("SELECT u.id FROM users u JOIN memberships m ON m.user_id=u.id WHERE m.account_id=4 AND m.role='owner' LIMIT 1");
assert_voice((bool)$owner,'tenant owner exists');
$ownerId=(int)$owner['id'];
$number='+201012345670';
$ownerRow=voice_staff_enroll(4,$ownerId,$number,'324851',['general','finance','bookings']);
assert_voice($ownerRow>0,'owner may register international phone with selected scopes');
$stored=one('SELECT pin_hash FROM voice_staff_numbers WHERE id=?',[$ownerRow]);
assert_voice(!str_contains($stored['pin_hash'],'324851')&&password_verify('324851',$stored['pin_hash']),'PIN stored as a one-way hash');
assert_voice(voice_staff_authenticate(4,$number,'111111')===null,'incorrect PIN fails closed');
assert_voice(voice_staff_authenticate(5,$number,'324851')===null,'foreign account denied even with correct PIN');
$principal=voice_staff_authenticate(4,$number,'324851');
assert_voice($principal!==null && $principal['scopes']===['general','finance','bookings'],'valid PIN preserves granted scopes only');
assert_voice(voice_action_ready($principal,'booking.create')===false,'no unapproved action execution');
$collision=false;
try{voice_staff_enroll(4,9999,$number,'324851',['general']);}
catch(InvalidArgumentException $e){$collision=true;}
assert_voice($collision,'cannot attach phone to non-member');
$receptionId=insert("INSERT INTO users(name,email,password_hash,status,created_at)
    VALUES (?,?,?,'active',?)",['Test Reception','reception-voice@example.invalid',hash_password(bin2hex(random_bytes(20))),now()]);
insert('INSERT INTO memberships(user_id,account_id,role,created_at) VALUES (?,?,?,?)',[$receptionId,4,'reception',now()]);
$elevate=false;
try{voice_staff_enroll(4,$receptionId,'+201012345671','246810',['finance']);}
catch(InvalidArgumentException $e){$elevate=true;}
assert_voice($elevate,'receptionist cannot grant finance access');
$receptionNumber='+201012345672';
$recId=voice_staff_enroll(4,$receptionId,$receptionNumber,'246810',['general','bookings']);
assert_voice($recId>0,'receptionist gets only approved limited scopes');
$integrationId=insert("INSERT INTO integrations(account_id,name,provider,token_hash,token_prefix,status,created_at)
    VALUES (?,?,?,?,?,'active',?)",[4,'WordPress Test','wordpress',hash('sha256',random_bytes(32)),'test',now()]);
$integration=one('SELECT * FROM integrations WHERE id=?',[$integrationId]);
assert_voice(voice_ingest_facts($integration,['category'=>'bookings',
    'facts'=>[['key'=>'today_count','value'=>12],['key'=>'open_count','value'=>3]]])===2,'signed connector snapshot accepts bounded facts');
assert_voice(voice_ingest_facts($integration,['category'=>'bookings',
    'facts'=>[['key'=>'today_count','value'=>13]]])===1,'repeated fact key updates instead of duplicating');
assert_voice((int)one("SELECT COUNT(*) n FROM integration_facts WHERE integration_id=? AND fact_key='today_count'",[$integrationId])['n']===1,'facts idempotent per integration and key');
voice_ingest_facts($integration,['category'=>'finance',
    'facts'=>[['key'=>'paid_today','value'=>'EGP 950']]]);
$rec=voice_staff_authenticate(4,$receptionNumber,'246810');
assert_voice($rec!==null,'receptionist PIN verified');
$visible=voice_visible_facts($rec);
assert_voice(count($visible)===2 && !in_array('finance',array_column($visible,'category'),true),
    'reception cannot see finance facts');
assert_voice(count(voice_visible_facts($principal))===3,'owner sees granted finance and booking facts');
assert_voice((int)($principal['number_id']??0)===$ownerRow,
    'authenticated principal pins exact enrolled number');
$partialRejected=false;
try {voice_ingest_facts($integration,['category'=>'bookings','facts'=>[
    ['key'=>'must_not_commit','value'=>42],['key'=>'invalid space','value'=>'oops']
]]);}
catch(InvalidArgumentException){$partialRejected=true;}
assert_voice($partialRejected&&!one('SELECT id FROM integration_facts
    WHERE integration_id=? AND fact_key=?',[$integrationId,'must_not_commit']),
    'malformed second item cannot partially commit first API fact');
$duplicateRejected=false;
try {voice_ingest_facts($integration,['category'=>'bookings','facts'=>[
    ['key'=>'duplicate_key','value'=>1],['key'=>'duplicate_key','value'=>2]
]]);}
catch(InvalidArgumentException){$duplicateRejected=true;}
assert_voice($duplicateRejected&&!one('SELECT id FROM integration_facts
    WHERE integration_id=? AND fact_key=?',[$integrationId,'duplicate_key']),
    'duplicate keys reject the complete snapshot');
$nestedRollback=false;
db()->beginTransaction();
try {
    voice_ingest_facts($integration,['category'=>'bookings','facts'=>[
        ['key'=>'nested_fact','value'=>1]
    ]]);
    assert_voice(db()->inTransaction(),'nested ingest preserves caller transaction');
    db()->rollBack();
    $nestedRollback=true;
} finally { if(db()->inTransaction())db()->rollBack(); }
assert_voice($nestedRollback&&!one('SELECT id FROM integration_facts
    WHERE integration_id=? AND fact_key=?',[$integrationId,'nested_fact']),
    'nested ingest participates atomically in outer transaction');
execute("UPDATE integrations SET status='revoked' WHERE id=?",[$integrationId]);
$inactiveDenied=false;
try{voice_ingest_facts($integration,['category'=>'bookings',
    'facts'=>[['key'=>'must_not_commit','value'=>1]]]);}
catch(InvalidArgumentException){$inactiveDenied=true;}
assert_voice($inactiveDenied,'disabled connector cannot ingest facts');
execute("UPDATE integrations SET status='active' WHERE id=?",[$integrationId]);
execute("UPDATE memberships SET role='viewer' WHERE user_id=? AND account_id=4",[$receptionId]);
$reduced=voice_staff_authenticate(4,$receptionNumber,'246810');
assert_voice($reduced!==null && $reduced['scopes']===['general'],
    'role downgrade is effective without reenrollment');
assert_voice(voice_visible_facts($rec)===[],
    'previously authenticated principal loses bookings on role downgrade');
$foreign=voice_visible_facts(['account_id'=>5,'role'=>'owner','scopes'=>['bookings','finance']]);
assert_voice($foreign===[],'connector facts never cross account');
assert_voice(voice_staff_revoke(4,$recId),'revocation succeeds');
assert_voice(voice_visible_facts($reduced)===[],
    'revoked enrolled phone cannot reuse existing principal to read facts');
assert_voice(voice_staff_authenticate(4,$receptionNumber,'246810')===null,'revoked phone cannot authenticate');
execute("UPDATE accounts SET status='suspended' WHERE id=4");
try {
    assert_voice(voice_visible_facts($principal)===[],
        'suspended account blocks previously authenticated principal');
    assert_voice(voice_staff_authenticate(4,$number,'324851')===null,
        'suspended account denies new PIN authentication');
    $denied=false;
    try {voice_ingest_facts($integration,['category'=>'bookings',
        'facts'=>[['key'=>'paused_snapshot','value'=>1]]]);}
    catch(InvalidArgumentException){$denied=true;}
    assert_voice($denied,'suspended account cannot ingest external facts');
    $denied=false;
    try {voice_staff_enroll(4,$ownerId,'+201012345677','246810',['general']);}
    catch(InvalidArgumentException){$denied=true;}
    assert_voice($denied,'suspended account cannot enroll staff numbers');
} finally {
    execute("UPDATE accounts SET status='active' WHERE id=4");
}
assert_voice(count(voice_visible_facts($principal))===3,
    'reactivated tenant restores only still-authorized facts');
assert_voice(count(voice_visible_facts_for_member(4,$ownerId))===3,
    'authenticated website member reads current permitted connector facts');
$lockedNumber='+201012345673';
voice_staff_enroll(4,$ownerId,$lockedNumber,'555666',['general']);
for($i=0;$i<5;$i++)voice_staff_authenticate(4,$lockedNumber,'000000');
assert_voice(voice_staff_authenticate(4,$lockedNumber,'555666')===null,'five failed PINs lock valid PIN for 15 minutes');
echo "VOICE_CALLER_AND_CONNECTOR_SCOPE_PASS\n";
