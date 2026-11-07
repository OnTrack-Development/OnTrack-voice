<?php
declare(strict_types=1);

/**
 * SITE-19: disabled-by-default protected-engine release control plane.
 *
 * This service does NOT run executable modules on the PHP host. It can only
 * serve a separately owner-signed, SHA-256 verified module from an encrypted
 * private database record to an entitled, authenticated paired SIM. The base
 * Android APK must independently pin the trusted Ed25519 signing public key,
 * verify signature/hash/ABI and load only via approved Android mechanisms.
 * No such Android integration is claimed here.
 */
function protected_engine_init():void {
    $marker=private_path().'/.schema.protected-engine.v1';
    if(is_file($marker))return;
    $mysql=driver()==='mysql';
    $pk=$mysql?'BIGINT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $integer=$mysql?'BIGINT':'INTEGER';
    $large=$mysql?'MEDIUMTEXT':'TEXT';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS protected_engine_releases (
        id $pk, version INTEGER NOT NULL UNIQUE, platform VARCHAR(16) NOT NULL,
        abi VARCHAR(32) NOT NULL, sha256 CHAR(64) NOT NULL,
        byte_length INTEGER NOT NULL, signature_hex CHAR(128) NOT NULL,
        encrypted_module $large NOT NULL, status VARCHAR(16) NOT NULL,
        created_at VARCHAR(19) NOT NULL, activated_at VARCHAR(19) NULL,
        revoked_at VARCHAR(19) NULL
    )".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS protected_engine_audit (
        id $pk, account_id $integer NULL, site_id $integer NULL,
        device_binding_id $integer NULL, sim_slot INTEGER NULL,
        release_version INTEGER NULL, event VARCHAR(32) NOT NULL,
        created_at VARCHAR(19) NOT NULL
    )".$engine);
    file_put_contents($marker,now(),LOCK_EX);
    @chmod($marker,0600);
}

/** Canonical signed metadata, with no URL, secrets, or arbitrary executable path. */
function protected_engine_metadata(int $version,string $abi,string $sha256,int $length):array {
    if($version<1||$version>1000000||
       !in_array($abi,['arm64-v8a','armeabi-v7a','x86_64'],true)||
       !preg_match('/^[0-9a-f]{64}$/D',$sha256)||
       $length<32||$length>4194304)
        throw new InvalidArgumentException('engine_metadata_invalid');
    return ['format'=>'ontrack-protected-engine-v1',
        'package'=>'com.ontrack.voiceproof','platform'=>'android',
        'abi'=>$abi,'version'=>$version,'sha256'=>$sha256,'bytes'=>$length];
}
function protected_engine_canonical(array $meta):string {
    return json_encode($meta,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
function protected_engine_signer():string {
    if(!extension_loaded('sodium'))throw new RuntimeException('engine_sodium_required');
    // Owner-provisioned, pinned signer. A server never generates or trusts its
    // own new signing key when the owner has not provisioned a trust anchor.
    $path=private_path().'/engine-trusted-signer.pub';
    if(!is_file($path)||is_link($path))
        throw new RuntimeException('engine_signer_not_provisioned');
    $raw=trim((string)file_get_contents($path));
    if(!preg_match('/^[a-f0-9]{64}$/D',$raw))
        throw new RuntimeException('engine_signer_invalid');
    return sodium_hex2bin($raw);
}
function protected_engine_verify_signature(array $meta,string $sigHex):void {
    if(!preg_match('/^[a-f0-9]{128}$/D',$sigHex)||
       !sodium_crypto_sign_verify_detached(
          sodium_hex2bin($sigHex),protected_engine_canonical($meta),protected_engine_signer()))
        throw new RuntimeException('engine_signature_invalid');
}
function protected_engine_audit(string $event,?array $binding=null,?int $version=null):void {
    protected_engine_init();
    insert('INSERT INTO protected_engine_audit
        (account_id,site_id,device_binding_id,sim_slot,release_version,event,created_at)
        VALUES (?,?,?,?,?,?,?)',[
          $binding===null?null:(int)$binding['account_id'],
          $binding===null?null:(int)$binding['site_id'],
          $binding===null?null:(int)$binding['id'],
          $binding===null?null:(int)$binding['sim_slot'],
          $version,$event,now()
        ]);
}
/** Trusted offline owner-signing step: CLI only, no web upload or eval. */
function protected_engine_stage(int $version,string $abi,string $module,string $sigHex):array {
    if(PHP_SAPI!=='cli')throw new RuntimeException('engine_stage_cli_only');
    protected_engine_init();
    $meta=protected_engine_metadata($version,$abi,hash('sha256',$module),strlen($module));
    protected_engine_verify_signature($meta,$sigHex);
    $existing=one('SELECT id FROM protected_engine_releases WHERE version=?',[$version]);
    if($existing)throw new RuntimeException('engine_version_already_registered');
    $cipher='enc:v1:'.seal_secret($module);
    insert("INSERT INTO protected_engine_releases
       (version,platform,abi,sha256,byte_length,signature_hex,encrypted_module,status,created_at)
       VALUES (?,?,?,?,?,?,?,'staged',?)",[
         $version,'android',$abi,$meta['sha256'],$meta['bytes'],$sigHex,$cipher,now()
       ]);
    protected_engine_audit('release_staged',null,$version);
    return $meta+['status'=>'staged'];
}
function protected_engine_activate(int $version):void {
    if(PHP_SAPI!=='cli')throw new RuntimeException('engine_activate_cli_only');
    protected_engine_init();
    $row=one('SELECT * FROM protected_engine_releases WHERE version=?',[$version]);
    if(!$row||$row['status']!=='staged')
        throw new RuntimeException('engine_release_not_staged');
    protected_engine_verified_bytes($row);
    $newer=one("SELECT version FROM protected_engine_releases
        WHERE platform=? AND abi=? AND version>? AND status='active'",[
        $row['platform'],$row['abi'],$version]);
    if($newer)throw new RuntimeException('engine_release_rollback_denied');
    db()->beginTransaction();
    try {
        execute("UPDATE protected_engine_releases SET status='revoked',revoked_at=?
          WHERE platform=? AND abi=? AND status='active'",[now(),$row['platform'],$row['abi']]);
        execute("UPDATE protected_engine_releases SET status='active',activated_at=?
          WHERE id=? AND status='staged'",[now(),(int)$row['id']]);
        db()->commit();
    }catch(Throwable $error){
        if(db()->inTransaction())db()->rollBack();
        throw $error;
    }
    protected_engine_audit('release_activated',null,$version);
}
function protected_engine_revoke(int $version):void {
    if(PHP_SAPI!=='cli')throw new RuntimeException('engine_revoke_cli_only');
    protected_engine_init();
    if(execute("UPDATE protected_engine_releases SET status='revoked',revoked_at=?
        WHERE version=? AND status IN ('active','staged')",[now(),$version])!==1)
        throw new RuntimeException('engine_release_not_revocable');
    protected_engine_audit('release_revoked',null,$version);
}
function protected_engine_verified_bytes(array $row):string {
    $meta=protected_engine_metadata((int)$row['version'],(string)$row['abi'],
        (string)$row['sha256'],(int)$row['byte_length']);
    protected_engine_verify_signature($meta,(string)$row['signature_hex']);
    $cipher=(string)$row['encrypted_module'];
    if(!str_starts_with($cipher,'enc:v1:'))
        throw new RuntimeException('engine_ciphertext_invalid');
    $bytes=unseal_secret(substr($cipher,7));
    if(!is_string($bytes)||strlen($bytes)!==$meta['bytes']||
       !hash_equals($meta['sha256'],hash('sha256',$bytes)))
        throw new RuntimeException('engine_payload_tampered');
    return $bytes;
}
function protected_engine_assert_binding(array $binding):void {
    $live=one("SELECT d.id FROM device_bindings d
      JOIN accounts a ON a.id=d.account_id AND a.status='active'
      JOIN sites s ON s.id=d.site_id AND s.account_id=d.account_id AND s.status='active'
      JOIN agents ag ON ag.id=d.agent_id AND ag.account_id=d.account_id
        AND ag.site_id=d.site_id AND ag.status!='archived'
      WHERE d.id=? AND d.account_id=? AND d.site_id=? AND d.sim_slot=?
        AND d.status='active'",[
        (int)($binding['id']??0),(int)($binding['account_id']??0),
        (int)($binding['site_id']??0),(int)($binding['sim_slot']??-1)
      ]);
    if(!$live)throw new RuntimeException('engine_device_not_authorized');
    require_once __DIR__.'/TenantVoiceSession.php';
    if(!tenant_voice_entitled($binding))
        throw new RuntimeException('engine_subscription_inactive');
}
/** Private HMAC is for a short-lived DOWNLOAD grant, not Android code signing. */
function protected_engine_download_grant(array $binding,array $row):string {
    $data=['b'=>(int)$binding['id'],'a'=>(int)$binding['account_id'],
        's'=>(int)$binding['site_id'],'sim'=>(int)$binding['sim_slot'],
        'r'=>(int)$row['id'],'v'=>(int)$row['version'],
        'exp'=>time()+120,'nonce'=>bin2hex(random_bytes(12))];
    $json=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $payload=rtrim(strtr(base64_encode($json),'+/','-_'),'=');
    return $payload.'.'.hash_hmac('sha256',$payload,secret_key());
}
function protected_engine_validate_grant(array $binding,string $token):array {
    if(strlen($token)>1024||
       !preg_match('/^([A-Za-z0-9_-]{40,920})\.([a-f0-9]{64})$/D',$token,$matches))
        throw new RuntimeException('engine_download_grant_invalid');
    if(!hash_equals(hash_hmac('sha256',$matches[1],secret_key()),$matches[2]))
        throw new RuntimeException('engine_download_grant_invalid');
    $json=base64_decode(strtr($matches[1],'-_','+/'),true);
    $data=is_string($json)?json_decode($json,true):null;
    if(!is_array($data)||!is_int($data['exp']??null)||$data['exp']<time()||
       $data['exp']>time()+121||!is_int($data['r']??null)||
       (int)($data['b']??-1)!==(int)$binding['id']||
       (int)($data['a']??-1)!==(int)$binding['account_id']||
       (int)($data['s']??-1)!==(int)$binding['site_id']||
       (int)($data['sim']??-1)!==(int)$binding['sim_slot'])
        throw new RuntimeException('engine_download_grant_invalid');
    return $data;
}
function protected_engine_current(array $binding,string $abi):array {
    protected_engine_assert_binding($binding);
    if(!in_array($abi,['arm64-v8a','armeabi-v7a','x86_64'],true))
        throw new InvalidArgumentException('engine_abi_invalid');
    protected_engine_init();
    $row=one("SELECT * FROM protected_engine_releases
      WHERE platform='android' AND abi=? AND status='active'
      ORDER BY version DESC LIMIT 1",[$abi]);
    if(!$row)throw new RuntimeException('engine_release_not_configured');
    // Each issued grant must be backed by fresh server-side signature and SHA
    // verification; no stale/offline or tampered blob ever becomes authorized.
    protected_engine_verified_bytes($row);
    return $row;
}
function protected_engine_manifest(array $binding,string $abi):array {
    $row=protected_engine_current($binding,$abi);
    $meta=protected_engine_metadata((int)$row['version'],(string)$row['abi'],
        (string)$row['sha256'],(int)$row['byte_length']);
    $result=['status'=>'licensed','module'=>$meta,
        'signature_ed25519_hex'=>(string)$row['signature_hex'],
        'grant'=>protected_engine_download_grant($binding,$row),
        'grant_expires_seconds'=>120,
        'verify_with_pinned_signer'=>true,
        'not_native_ota'=>true];
    protected_engine_audit('grant_issued',$binding,(int)$row['version']);
    return $result;
}
function protected_engine_download(array $binding,string $abi,string $grant):array {
    protected_engine_assert_binding($binding);
    $claims=protected_engine_validate_grant($binding,$grant);
    $row=protected_engine_current($binding,$abi);
    if((int)$row['id']!==$claims['r']||(int)$row['version']!==($claims['v']??-1))
        throw new RuntimeException('engine_release_revoked_or_changed');
    $bytes=protected_engine_verified_bytes($row);
    protected_engine_audit('module_downloaded',$binding,(int)$row['version']);
    return ['bytes'=>$bytes,'sha256'=>(string)$row['sha256'],
        'signature'=>(string)$row['signature_hex']];
}
