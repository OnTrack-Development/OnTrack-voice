<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/TenantWorkspace.php';
function camp68(bool $truth,string $message):void{
    if(!$truth)throw new RuntimeException('V68_SIM_CAMPAIGN_CONSENT_FAIL '.$message);
}
seed_console();
$boundId=insert("INSERT INTO device_bindings(account_id,site_id,agent_id,
    installation_id,sim_slot,slot_claim,device_name,token_hash,status,created_at)
    VALUES (4,1,1,?,?,?,?,?,'active',?)",[
    'v68-sim-test',0,hash('sha256','v68-sim-test|0'),
    'QA SIM',hash('sha256','auth-'.bin2hex(random_bytes(12))),now()]);
$b=['id'=>$boundId,'account_id'=>4,'site_id'=>1,'agent_id'=>1,'sim_slot'=>0];
$added=tenant_workspace_import($b,[
    ['name'=>'Explicit consent QA','phone'=>'+201009876543'],
    ['name'=>'Revoked consent QA','phone'=>'+201007654321']
]);
camp68($added['added']===2,'test-only contacts imported');
$contacts=tenant_workspace_contacts($b)['contacts'];
$map=[];foreach($contacts as $person)$map[$person['name']]=$person['id'];
$a=$map['Explicit consent QA'];$other=$map['Revoked consent QA'];
tcq_consent($b,$a,'written','Signed customer permission 2026-11-08');
tcq_consent($b,$other,'form','Form opt-in recorded 2026-11-08');
$local=new DateTimeImmutable('now',new DateTimeZone('Africa/Cairo'));
$s=$local->modify('-1 minute')->format('Y-m-d\TH:i');
$e=$local->modify('+60 minutes')->format('Y-m-d\TH:i');
$camp=tenant_workspace_create_campaign($b,[
 'confirmed'=>true,'name'=>'V68 approved contacts','starts'=>$s,'ends'=>$e,
 'scenario'=>'A short test call with real owner approval.','contact_ids'=>[$a,$other]]);
$id=$camp['campaign']['id'];
$arm=tenant_workspace_campaign_action($b,['confirmed'=>true,'id'=>$id,'action'=>'arm']);
camp68($arm['calls_started']===0,'arming is not dialing');
$before=getenv('ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED');
try{
    putenv('ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED=0');
    $guard=false;
    try{tcq_claim($b);}catch(RuntimeException $error){
        $guard=$error->getMessage()==='campaign_device_e2e_gate_disabled';
    }
    camp68($guard,'production rollout gate OFF by default');
    putenv('ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED=1');
    $job=tcq_claim($b)['claim'];
    camp68(is_array($job)&&strlen($job['id'])===32&&
        $job['phone']==='+201009876543','leased only to paired SIM and eligible contact');
    $safety=tcq_verify($b,$job['id']);
    camp68($safety['allowed']===true&&$safety['sim_slot']===0&&
        $safety['must_dial_within_seconds']===10,'fresh server DNC and consent check');
    $finish=tcq_finish($b,$job['id'],'unknown');
    camp68($finish['saved']===true,'uncertain carrier result persisted');
    $waiting=tcq_claim($b);
    camp68($waiting['claim']===null&&
        $waiting['reason']==='uncertain_call_requires_review',
        'unknown outgoing call blocks all further automatic attempts');
    $next=tcq_attempt($b,$job['id']);
    camp68($next['status']==='unknown','uncertain attempt durable for human review');
    $foreign=false;
    $another=$b;$another['sim_slot']=1;
    try{tcq_attempt($another,$job['id']);}catch(InvalidArgumentException){$foreign=true;}
    camp68($foreign||tcq_attempt($another,$job['id'])===null,
        'foreign SIM cannot read another slot lease');
    // Admin revocation of an approved target removes its eligibility on
    // any future attempt, regardless of prior contact consent.
    tenant_workspace_dnc($b,$other,true);
    camp68(!tcq_allowed($b,tenant_workspace_parse_id($other)),
        'do not call immediately revokes previously recorded consent');
}finally{
    putenv('ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED'.($before===false?'':'='.$before));
}
echo "V68_CAMPAIGN_PAIRED_SIM_CONSENT_DNC_E2E_GATE_UNCERTAIN_CALL_PASS\n";
