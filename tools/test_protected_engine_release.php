<?php
declare(strict_types=1);
if(is_dir('/home/appontrack'))throw new RuntimeException('CI_ONLY');
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/DevicePairing.php';
require_once __DIR__.'/../saas/console/_core/TenantVoiceSession.php';
require_once __DIR__.'/../saas/console/_core/ProtectedEngine.php';
seed_console();
function engine_ok(bool $assert,string $label):void{
    if(!$assert)throw new RuntimeException('ENGINE_RELEASE_SECURITY_FAIL: '.$label);
}
function engine_reject(callable $fn,string $expected):void{
    try{$fn();}catch(Throwable $e){
        engine_ok($e->getMessage()===$expected,'reject '.$expected);
        return;
    }
    throw new RuntimeException('EXPECTED_ENGINE_DENIAL '.$expected);
}
if(!extension_loaded('sodium'))throw new RuntimeException('ENGINE_ED25519_SODIUM_MISSING');
$signerPath=private_path().'/engine-trusted-signer.pub';
$oldSigner=is_file($signerPath)?file_get_contents($signerPath):null;
$kp=sodium_crypto_sign_keypair();
$public=sodium_crypto_sign_publickey($kp);
$private=sodium_crypto_sign_secretkey($kp);
file_put_contents($signerPath,sodium_bin2hex($public),LOCK_EX);
chmod($signerPath,0600);
$bindingId=null;
$version=770019;$older=770018;
$binding=null;
try{
    $bindingId=insert("INSERT INTO device_bindings
        (account_id,site_id,agent_id,installation_id,sim_slot,slot_claim,
         device_name,token_hash,status,created_at)
        VALUES (?,?,?,?,?,?,?,?,'active',?)",[
        5,3,3,'engine-ci-fixture-'.bin2hex(random_bytes(8)),0,
        hash('sha256','engine-fixture-'.bin2hex(random_bytes(8))),'Engine CI paired SIM',
        hash('sha256','engine-token-'.bin2hex(random_bytes(8))),now()]);
    $binding=['id'=>$bindingId,'account_id'=>5,'site_id'=>3,'agent_id'=>3,'sim_slot'=>0];
    engine_reject(static fn()=>protected_engine_manifest($binding,'arm64-v8a'),
        'engine_release_not_configured');
    $module=str_repeat('SIGNED-CI-BLOB-NOT-EXECUTABLE-',16);
    $meta=protected_engine_metadata($version,'arm64-v8a',
        hash('sha256',$module),strlen($module));
    $signature=sodium_bin2hex(sodium_crypto_sign_detached(
        protected_engine_canonical($meta),$private));
    engine_reject(static fn()=>protected_engine_stage($version,'arm64-v8a',$module,
        str_repeat('0',128)),'engine_signature_invalid');
    engine_reject(static fn()=>protected_engine_stage($version,'x86',$module,$signature),
        'engine_metadata_invalid');
    $staged=protected_engine_stage($version,'arm64-v8a',$module,$signature);
    engine_ok($staged['status']==='staged','only owner-signed module is staged');
    engine_reject(static fn()=>protected_engine_manifest($binding,'arm64-v8a'),
        'engine_release_not_configured');
    $encrypted=one('SELECT encrypted_module FROM protected_engine_releases WHERE version=?',
        [$version])['encrypted_module'];
    engine_ok(str_starts_with($encrypted,'enc:v1:')&&
        !str_contains($encrypted,'SIGNED-CI-BLOB'),
        'release cannot be recovered as plaintext from private database');
    protected_engine_activate($version);
    $manifest=protected_engine_manifest($binding,'arm64-v8a');
    engine_ok($manifest['status']==='licensed'&&
        $manifest['module']===$meta&&
        $manifest['signature_ed25519_hex']===$signature&&
        $manifest['grant_expires_seconds']===120,
        'pin-verifiable manifest and bounded device entitlement');
    $download=protected_engine_download($binding,'arm64-v8a',$manifest['grant']);
    engine_ok($download['bytes']===$module&&$download['sha256']===$meta['sha256']&&
        $download['signature']===$signature,'verified module bytes match SHA and Ed25519');
    $foreign=$binding;$foreign['account_id']=6;
    engine_reject(static fn()=>protected_engine_manifest($foreign,'arm64-v8a'),
        'engine_device_not_authorized');
    engine_reject(static fn()=>protected_engine_download($foreign,'arm64-v8a',
        $manifest['grant']),'engine_device_not_authorized');
    $otherSim=$binding;$otherSim['sim_slot']=1;
    engine_reject(static fn()=>protected_engine_download($otherSim,'arm64-v8a',
        $manifest['grant']),'engine_device_not_authorized');
    engine_reject(static fn()=>protected_engine_download($binding,'arm64-v8a',
        substr($manifest['grant'],0,-1).'0'),'engine_download_grant_invalid');
    $parts=explode('.',$manifest['grant'],2);
    $claims=json_decode(base64_decode(strtr($parts[0],'-_','+/')),true);
    $claims['exp']=time()-10;
    $expired=rtrim(strtr(base64_encode(json_encode($claims)),'+/','-_'),'=');
    $expired.='.'.hash_hmac('sha256',$expired,secret_key());
    engine_reject(static fn()=>protected_engine_download($binding,'arm64-v8a',
        $expired),'engine_download_grant_invalid');
    $olderMeta=protected_engine_metadata($older,'arm64-v8a',hash('sha256',$module),strlen($module));
    $oldSignature=sodium_bin2hex(sodium_crypto_sign_detached(
        protected_engine_canonical($olderMeta),$private));
    protected_engine_stage($older,'arm64-v8a',$module,$oldSignature);
    engine_reject(static fn()=>protected_engine_activate($older),
        'engine_release_rollback_denied');
    execute('UPDATE protected_engine_releases SET encrypted_module=? WHERE version=?',
        ['enc:v1:corrupted',$version]);
    engine_reject(static fn()=>protected_engine_manifest($binding,'arm64-v8a'),
        'engine_payload_tampered');
    execute('UPDATE protected_engine_releases SET encrypted_module=? WHERE version=?',
        [$encrypted,$version]);
    execute("UPDATE device_bindings SET status='revoked' WHERE id=?",[$bindingId]);
    engine_reject(static fn()=>protected_engine_download($binding,'arm64-v8a',
        $manifest['grant']),'engine_device_not_authorized');
    execute("UPDATE device_bindings SET status='active' WHERE id=?",[$bindingId]);
    protected_engine_revoke($version);
    engine_reject(static fn()=>protected_engine_download($binding,'arm64-v8a',
        $manifest['grant']),'engine_release_not_configured');
    $audit=all('SELECT event,release_version FROM protected_engine_audit WHERE release_version=?',
        [$version]);
    engine_ok(count($audit)>=5,'stage, activation, license, download and revocation audited');
    engine_ok(protected_engine_download_grant($binding,
        ['id'=>1,'version'=>$version])!=='','grants are nonempty and scoped');
    echo "SITE19_PROTECTED_ENGINE_SIGNED_LICENSE_AND_TAMPER_PASS\n";
}finally{
    if($bindingId!==null)execute('DELETE FROM device_bindings WHERE id=?',[$bindingId]);
    execute('DELETE FROM protected_engine_audit WHERE release_version IN (?,?)',[$version,$older]);
    execute('DELETE FROM protected_engine_releases WHERE version IN (?,?)',[$version,$older]);
    if(is_string($oldSigner))file_put_contents($signerPath,$oldSigner,LOCK_EX);
    elseif(is_file($signerPath))unlink($signerPath);
    sodium_memzero($private);
}
