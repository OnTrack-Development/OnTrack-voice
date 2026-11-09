<?php
declare(strict_types=1);
/** Isolated first-run Vault key race / re-use / ciphertext compatibility proof. */
if(PHP_SAPI!=='cli'||is_dir('/home/appontrack')||
    !preg_match('/^[A-Za-z0-9_-]{8,60}$/D',(string)getenv('ONTRACK_CI_NAMESPACE')))
    throw new RuntimeException('vault_test_isolation_required');
require __DIR__.'/../saas/console/_core/Bootstrap.php';
function vault_check(bool $ok,string $name):void{
    if(!$ok)throw new RuntimeException('SAAS_VAULT_INTEGRITY_FAIL '.$name);
}
$key=secret_key();
vault_check(strlen($key)===32,'32-byte initial key');
$keyPath=private_path().'/console-vault.key';
vault_check(is_file($keyPath)&&!is_link($keyPath),'real private file');
vault_check((fileperms($keyPath)&0077)===0,'private key 0600');
$box=seal_secret('sensitive-synthetic-key-v1');
vault_check($box!=='sensitive-synthetic-key-v1','AES-GCM encryption');
vault_check(unseal_secret($box)==='sensitive-synthetic-key-v1','existing ciphertext decrypts');
vault_check(hash_equals($key,secret_key()),'reuse existing key, never auto rotate');
$raw=base64_decode($box,true);
vault_check(is_string($raw),'base64 ciphertext');
$raw[strlen($raw)-1]=chr(ord($raw[strlen($raw)-1])^1);
vault_check(unseal_secret(base64_encode($raw))===null,'reject tampered GCM tag/ciphertext');
vault_check(unseal_secret('not-valid-base64')===null,'malformed secret refused');
echo "SAAS_VAULT_FIRST_RUN_ENCRYPT_AND_RESTORE_PASS\n";
