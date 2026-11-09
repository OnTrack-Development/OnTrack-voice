<?php
declare(strict_types=1);
/**
 * Host operator only. Reads credential from STDIN, never CLI argv or Git.
 *
 * Example (bash, in an interactive SSH session):
 *   read -rs -p 'Maintenance token: ' ONTRACK_TOKEN; echo
 *   printf '%s' "$ONTRACK_TOKEN" | php tools/provision_maintenance_token.php
 *   unset ONTRACK_TOKEN
 *
 * 0600 digest, stored outside public_html. Re-run to rotate; test authorized
 * diagnostics before and after disabling the previous credential.
 */
if(PHP_SAPI!=='cli')exit(1);
if(!is_dir('/home/appontrack') || !function_exists('posix_geteuid') || posix_geteuid()!==2379) {
    fwrite(STDERR,"Production host account required\n");
    exit(2);
}
$root='/home/appontrack/.ontrack-maintenance';
if(is_link($root) || (!is_dir($root) && !@mkdir($root,0700,true))) {
    fwrite(STDERR,"Unable to create private maintenance store\n");
    exit(3);
}
@chmod($root,0700);
$target=$root.'/token.sha256';
if(is_link($target)) {
    fwrite(STDERR,"Refusing symlink\n");
    exit(4);
}
$token=trim((string)stream_get_contents(STDIN,1024));
if(!preg_match('/\A[A-Za-z0-9._~\-]{24,256}\z/D',$token)) {
    fwrite(STDERR,"Invalid credential: 24-256 URL-safe characters required\n");
    exit(5);
}
$hash=hash('sha256',$token)."\n";
unset($token);
$tmp=tempnam($root,'.maintenance-');
if($tmp===false)exit(6);
try{
    if(@file_put_contents($tmp,$hash,LOCK_EX)!==strlen($hash)||!@chmod($tmp,0600))exit(7);
    if(!@rename($tmp,$target))exit(8);
}finally{
    if(is_file($tmp))@unlink($tmp);
}
echo "ONTRACK_MAINTENANCE_CREDENTIAL_PROVISIONED\n";
