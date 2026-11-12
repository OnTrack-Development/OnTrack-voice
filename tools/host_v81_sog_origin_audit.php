<?php
declare(strict_types=1);
/** Read only routing metadata for verified reseller SOG account #8.
 * No cookies, session state, tokens, identity, billing, device identifiers.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
try{
 require '/home/appontrack/public_html/_core/Bootstrap.php';
 $acct=one('SELECT id,slug,kind,status,parent_id FROM accounts WHERE id=?',[8]);
 if(!$acct || $acct['status']!=='active')throw new RuntimeException('unexpected_sog_account');
 $domains=all('SELECT hostname,status FROM domains WHERE account_id=? ORDER BY id LIMIT 15',[8]);
 $origin=[];
 foreach($domains as $d){
   // Hostnames are public routing records; no credentials or user data here.
   $origin[]=['hostname'=>$d['hostname'],'status'=>$d['status']];
 }
 echo json_encode(['probe'=>'v81_sog_origin_audit','account_id'=>8,
   'slug'=>$acct['slug'],'account_kind'=>$acct['kind'],
   'active_domains'=>$origin,'read_only'=>true],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){
 fwrite(STDERR,'V81_SOG_ORIGIN_PROBE_FAILED '.get_class($e)."\n");exit(1);
}
