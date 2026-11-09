<?php
declare(strict_types=1);
/**
 * Phase 2 contract probe on isolated seeded console DB. No calls, no
 * provider tokens, no phone application and no production credentials.
 */
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/TenantDeviceContract.php';
if(is_dir('/home/appontrack') && !getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('saas_phase2_fixture_only');
function contract_ok(bool $pass,string $name):void{
    if(!$pass)throw new RuntimeException('SAAS_PHASE2_CONTRACT_FAIL '.$name);
}
seed_console();
$actor=(int)one("SELECT user_id FROM memberships WHERE account_id=4 AND role='owner' LIMIT 1")['user_id'];
$agent=one("SELECT id,site_id FROM agents WHERE account_id=4 AND status!='archived' LIMIT 1");
contract_ok($agent!==null,'owner agent available');
$aid=(int)$agent['id'];$site=(int)$agent['site_id'];
publish_agent_flow(4,$aid,$actor);
$pairCode=device_pairing_create(4,$site,$aid,$actor,0);
$bound=device_pairing_redeem($pairCode,'phase2-contract-device-001',0,'Phase 2 QA SIM0');
$token=$bound['device_token'];
$binding=device_binding_authorize($token);
contract_ok(is_array($binding),'valid paired SIM token');
$before=one('SELECT last_seen_at,token_hash,agent_revision FROM device_bindings WHERE id=?',[$bound['device_id']]);
$profile=tenant_device_contract_probe($binding);
contract_ok($profile['contract']==='tenant_device_v1' && $profile['control_plane_supported']===true
    && $profile['carrier_audio_verified']===false,'probe does not fake carrier E2E');
contract_ok($profile['account_id']===4 && $profile['site_id']===$site &&
    $profile['sim_slot']===0 && $profile['agent_id']===$aid,'exact tenant SIM identity');
contract_ok($profile['published_flow_ready']===true &&
    (int)$profile['published_flow_revision']>0,'real published agent revision');
contract_ok(!str_contains(json_encode($profile),'otdev_') &&
    !array_key_exists('token',$profile) && !array_key_exists('api_key',$profile),
    'no authentication secrets in response');
$after=one('SELECT last_seen_at,token_hash,agent_revision FROM device_bindings WHERE id=?',[$bound['device_id']]);
contract_ok($after===$before,'read-only probe does not mutate heartbeat or SIM link');
$foreign=$binding;$foreign['account_id']=5;
$denied=false;
try{tenant_device_contract_probe($foreign);}
catch(InvalidArgumentException){$denied=true;}
contract_ok($denied,'cross-account probe rejected');
$foreign=$binding;$foreign['sim_slot']=1;
$denied=false;
try{tenant_device_contract_probe($foreign);}
catch(InvalidArgumentException){$denied=true;}
contract_ok($denied,'cross-slot probe rejected');
execute("UPDATE device_bindings SET status='revoked' WHERE id=? AND account_id=4",[$bound['device_id']]);
$denied=false;
try{tenant_device_contract_probe($binding);}
catch(InvalidArgumentException){$denied=true;}
contract_ok($denied,'revoked device cannot reuse previously authorized scope');
$api=file_get_contents(__DIR__.'/../saas/console/device/v1/index.php');
contract_ok(str_contains((string)$api,"if(\$operation==='contract_probe')") &&
    str_contains((string)$api,"'contract_probe'=>tenant_device_contract_probe(\$binding)"),
    'new optional endpoint wired to bearer authorization');
contract_ok(str_contains((string)$api,"if(\$operation==='voice_session')") &&
    str_contains((string)$api,"if(\$operation==='published_flow')"),
    'existing voice/published routes still exist and are unchanged');
echo "SAAS_PHASE2_TENANT_DEVICE_CONTRACT_NONREGRESSION_PASS\n";
