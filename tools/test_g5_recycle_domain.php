<?php
declare(strict_types=1);
require __DIR__.'/../saas/console/database/Seed.php';
require __DIR__.'/../saas/console/_core/RecycleBin.php';
require __DIR__.'/../saas/console/_core/DomainVerification.php';
function g5check(bool $ok,string $why):void{
    if(!$ok)throw new RuntimeException('G5_RECYCLING_DOMAIN_'.$why);
}
seed_console();
$account=(int)(one("SELECT id FROM accounts WHERE kind='customer' ORDER BY id LIMIT 1")['id']??0);
$reseller=(int)(one("SELECT id FROM accounts WHERE kind='reseller' ORDER BY id LIMIT 1")['id']??0);
g5check($account>0&&$reseller>0,'fixture accounts');
$site=insert("INSERT INTO sites(account_id,name,domain,status,created_at) VALUES(?,?,'','active',?)",
    [$account,'G5 Restore Probe',now()]);
$agent=insert("INSERT INTO agents(account_id,site_id,name,voice,instructions,knowledge,flow_json,status,created_at)
  VALUES(?,?,'Restore Probe','google.aoede','','','{}','archived',?)",[$account,$site,now()]);
$domain=0;
try{
    recycle_archive_note($account,'agent',$agent);
    execute("UPDATE sites SET status='archived' WHERE id=? AND account_id=?",[$site,$account]);
    recycle_archive_note($account,'site',$site);
    $listed=recycle_entries($account);
    g5check(count(array_filter($listed,fn($x)=>$x['id']===$site&&$x['kind']==='site'))===1,
        'site entry scoped');
    g5check(!recycle_restore($reseller,'site',$site),'foreign tenant cannot restore');
    $blocked=false;
    try{recycle_restore($account,'agent',$agent);}catch(InvalidArgumentException){$blocked=true;}
    g5check($blocked,'cannot restore agent while site archived');
    g5check(recycle_restore($account,'site',$site),'site restored');
    g5check(recycle_restore($account,'agent',$agent),'agent restored');
    g5check((string)one('SELECT status FROM agents WHERE id=?',[$agent])['status']==='draft',
        'restored agent stays draft');
    recycle_update_retention($account,45);
    g5check(recycle_retention($account)===45,'configurable retention');
    $bad=false;
    try{recycle_update_retention($account,1);}catch(InvalidArgumentException){$bad=true;}
    g5check($bad,'unsafe retention rejected');
    $token=bin2hex(random_bytes(18));$host='g5-'.bin2hex(random_bytes(4)).'.example.org';
    $domain=insert("INSERT INTO domains(account_id,hostname,verification_token,status,created_at)
        VALUES (?,?,?,'pending',?)",[$reseller,$host,$token,now()]);
    $denied=false;
    try{domain_dns_verify($account,$domain,fn($n)=>[]);}
    catch(InvalidArgumentException){$denied=true;}
    g5check($denied,'other account cannot verify domain');
    $missing=domain_dns_verify($reseller,$domain,fn($n)=>[]);
    g5check($missing['verified']===false,'missing DNS proof cannot activate domain');
    $ok=domain_dns_verify($reseller,$domain,
        fn($name)=>[['txt'=>'ontrack-verify='.$token]]);
    g5check($ok['verified']===true&&$ok['tls_active']===false,'DNS not TLS');
    g5check((string)one('SELECT status FROM domains WHERE id=?',[$domain])['status']==='dns_verified',
        'DNS proof does not mark active TLS');
}finally{
    if($domain)execute('DELETE FROM domains WHERE id=? AND account_id=?',[$domain,$reseller]);
    execute('DELETE FROM recycle_settings WHERE account_id=?',[$account]);
    execute('DELETE FROM recycle_items WHERE account_id=? AND item_id IN (?,?)',[$account,$site,$agent]);
    execute('DELETE FROM agents WHERE id=? AND account_id=?',[$agent,$account]);
    execute('DELETE FROM sites WHERE id=? AND account_id=?',[$site,$account]);
}
echo "G5_RECYCLE_DNS_SCOPE_PASS\n";
