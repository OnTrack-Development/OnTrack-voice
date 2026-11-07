<?php
declare(strict_types=1);
require_once __DIR__.'/VoiceConnectors.php';

/**
 * Tenant-bound, auditable, explicit-consent tool gateway. No LLM or caller
 * may supply a URL, HTTP verb, credentials, tenant, WHMCS action or approval.
 * Never infer success from HTTP 200 without the provider's success+real ID.
 */
function voice_tools_init():void {
    $mark=private_path().'/.schema.voice-tools.v1';
    if(is_file($mark))return;
    $mysql=driver()==='mysql';
    $pk=$mysql?'BIGINT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $int=$mysql?'BIGINT':'INTEGER';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $long=$mysql?'MEDIUMTEXT':'TEXT';
    db()->exec("CREATE TABLE IF NOT EXISTS voice_tool_policies (
       id $pk, account_id $int NOT NULL, site_id $int NOT NULL,
       connector_id $int NOT NULL, operation VARCHAR(48) NOT NULL,
       path VARCHAR(350) NOT NULL DEFAULT '', enabled INTEGER NOT NULL DEFAULT 0,
       created_at VARCHAR(19) NOT NULL,updated_at VARCHAR(19) NOT NULL,
       UNIQUE(account_id,site_id,connector_id,operation))".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS voice_tool_requests (
       id $pk,account_id $int NOT NULL,site_id $int NOT NULL,
       connector_id $int NOT NULL,policy_id $int NOT NULL,
       call_id VARCHAR(64) NOT NULL DEFAULT '',
       operation VARCHAR(48) NOT NULL,actor_id $int NOT NULL,
       idempotency_key VARCHAR(80) NOT NULL,
       payload_enc $long NOT NULL,payload_sha256 CHAR(64) NOT NULL,
       approval_sha256 CHAR(64) NOT NULL,status VARCHAR(24) NOT NULL DEFAULT 'prepared',
       reference_id VARCHAR(120) NULL,
       result_summary TEXT NULL,error_code VARCHAR(80) NULL,
       prepared_at VARCHAR(19) NOT NULL,completed_at VARCHAR(19) NULL,
       UNIQUE(account_id,site_id,idempotency_key))".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS voice_tool_traces (
       id $pk, account_id $int NOT NULL,site_id $int NOT NULL,
       request_id $int NOT NULL,call_id VARCHAR(64) NOT NULL DEFAULT '',
       operation VARCHAR(48) NOT NULL,status VARCHAR(24) NOT NULL,
       detail VARCHAR(250) NOT NULL,created_at VARCHAR(19) NOT NULL)".$engine);
    file_put_contents($mark,now(),LOCK_EX);@chmod($mark,0600);
}
function voice_tool_operations():array {
    return ['whmcs.products','whmcs.services','whmcs.invoices','whmcs.tickets',
        'whmcs.ticket.create','whmcs.ticket.reply','rest.slots','rest.booking.create'];
}
function voice_tool_is_write(string $operation):bool {
    return in_array($operation,['whmcs.ticket.create','whmcs.ticket.reply','rest.booking.create'],true);
}
function voice_tool_policy_save(int $accountId,int $siteId,int $connectorId,
    string $operation,string $path,bool $enabled):int {
    voice_tools_init();
    if(!in_array($operation,voice_tool_operations(),true))
        throw new InvalidArgumentException('tool_operation_not_allowed');
    $connector=one("SELECT c.* FROM voice_data_connectors c
       JOIN sites s ON s.id=c.site_id AND s.account_id=c.account_id AND s.status='active'
       JOIN accounts a ON a.id=c.account_id AND a.status='active'
       WHERE c.id=? AND c.account_id=? AND c.site_id=? AND c.status='verified'",
       [$connectorId,$accountId,$siteId]);
    if(!$connector)throw new InvalidArgumentException('tool_connector_unverified');
    if(str_starts_with($operation,'whmcs.')){
        if($connector['type']!=='whmcs'||(string)$connector['read_path']!=='/includes/api.php')
            throw new InvalidArgumentException('tool_whmcs_connector_invalid');
        $path='/includes/api.php';
    }else{
        if(!in_array($connector['type'],['laravel','custom'],true))
            throw new InvalidArgumentException('tool_rest_connector_invalid');
        $path=voice_connector_path($path);
        if(str_contains($path,'?')||$path==='/')
            throw new InvalidArgumentException('tool_path_query_not_allowed');
    }
    $previous=one("SELECT id FROM voice_tool_policies
       WHERE account_id=? AND site_id=? AND connector_id=? AND operation=?",
       [$accountId,$siteId,$connectorId,$operation]);
    if($previous){
        execute("UPDATE voice_tool_policies SET path=?,enabled=?,updated_at=?
          WHERE id=? AND account_id=?",[$path,$enabled?1:0,now(),$previous['id'],$accountId]);
        return (int)$previous['id'];
    }
    return insert("INSERT INTO voice_tool_policies
       (account_id,site_id,connector_id,operation,path,enabled,created_at,updated_at)
       VALUES (?,?,?,?,?,?,?,?)",[$accountId,$siteId,$connectorId,$operation,$path,$enabled?1:0,now(),now()]);
}
function voice_tool_policy(int $accountId,int $siteId,int $policyId):array {
    voice_tools_init();
    $row=one("SELECT p.*,c.type,c.base_url,c.read_path,c.auth_mode,c.secret_enc,c.status connector_status
       FROM voice_tool_policies p JOIN voice_data_connectors c
        ON c.id=p.connector_id AND c.account_id=p.account_id AND c.site_id=p.site_id
       JOIN sites s ON s.id=p.site_id AND s.account_id=p.account_id AND s.status='active'
       JOIN accounts a ON a.id=p.account_id AND a.status='active'
       WHERE p.id=? AND p.account_id=? AND p.site_id=? AND p.enabled=1
         AND c.status='verified'",[$policyId,$accountId,$siteId]);
    if(!$row)throw new InvalidArgumentException('tool_policy_unavailable');
    return $row;
}
/** A consent token is pinned to the EXACT verified origin, endpoint and policy. */
function voice_tool_approval_digest(array $policy,string $token):string {
    $f=json_encode([
        'policy_id'=>(int)$policy['id'],'connector_id'=>(int)$policy['connector_id'],
        'operation'=>(string)$policy['operation'],'path'=>(string)$policy['path'],
        'enabled'=>(int)$policy['enabled'],'type'=>(string)$policy['type'],
        'base_url'=>(string)$policy['base_url'],
        'read_path'=>(string)$policy['read_path'],
        'auth_mode'=>(string)$policy['auth_mode'],
        'credential_sha256'=>hash('sha256',(string)($policy['secret_enc']??'')),
        'policy_updated_at'=>(string)$policy['updated_at']
    ],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    return hash_hmac('sha256',$f,$token);
}
/** No untrusted body may add parameters, endpoint URLs or WHMCS actions. */
function voice_tool_payload(string $operation,array $input):array {
    $allowed=match($operation){
        'whmcs.products'=>[],
        'whmcs.services','whmcs.tickets'=>['client_id'],
        'whmcs.invoices'=>['client_id'],
        'whmcs.ticket.create'=>['client_id','department_id','subject','message'],
        'whmcs.ticket.reply'=>['ticket_id','message'],
        'rest.slots'=>['date','service_id'],
        'rest.booking.create'=>['slot_id','name','phone','service_id'],
        default=>throw new InvalidArgumentException('tool_unknown_operation')
    };
    if(array_diff(array_keys($input),$allowed))throw new InvalidArgumentException('tool_extra_fields_denied');
    $output=[];
    foreach($allowed as $key) {
        $value=$input[$key]??null;
        if(in_array($key,['client_id','department_id','ticket_id'],true)){
            if(!is_int($value)||$value<1||$value>2147483647)
                throw new InvalidArgumentException('tool_invalid_'.$key);
        }elseif($key==='date'){
            if(!is_string($value)||!preg_match('/^20[0-9]{2}-[01][0-9]-[0-3][0-9]$/D',$value)||
                date('Y-m-d',strtotime($value.' 00:00:00 UTC')?:0)!==$value)
                throw new InvalidArgumentException('tool_invalid_date');
        }elseif($key==='phone'){
            if(!is_string($value)||!preg_match('/^\+[1-9][0-9]{7,14}$/D',$value))
                throw new InvalidArgumentException('tool_invalid_phone');
        }else{
            $max=$key==='message'?1800:($key==='name'||$key==='subject'?140:90);
            if(!is_string($value)||trim($value)===''||mb_strlen($value)>$max||!mb_check_encoding($value,'UTF-8'))
                throw new InvalidArgumentException('tool_invalid_'.$key);
        }
        $output[$key]=$value;
    }
    return $output;
}
function voice_tool_actor(int $accountId,int $userId):array {
    $principal=one("SELECT m.role FROM memberships m
      JOIN users u ON u.id=m.user_id AND u.status='active'
      JOIN accounts a ON a.id=m.account_id AND a.status='active'
      WHERE m.account_id=? AND m.user_id=?",
      [$accountId,$userId]);
    if(!$principal||!in_array($principal['role'],['owner','admin','manager'],true))
        throw new RuntimeException('tool_actor_requires_owner_or_manager');
    return ['id'=>$userId,'role'=>$principal['role']];
}
function voice_tool_trace(array $request,string $state,string $detail):void {
    insert("INSERT INTO voice_tool_traces(account_id,site_id,request_id,call_id,operation,status,detail,created_at)
        VALUES (?,?,?,?,?,?,?,?)",[(int)$request['account_id'],(int)$request['site_id'],
        (int)$request['id'],(string)$request['call_id'],(string)$request['operation'],
        $state,mb_substr($detail,0,250),now()]);
}
function voice_tool_prepare(int $accountId,int $siteId,int $policyId,int $actorId,
    string $idempotencyKey,array $input,string $callId=''):array {
    $policy=voice_tool_policy($accountId,$siteId,$policyId);
    voice_tool_actor($accountId,$actorId);
    if(!preg_match('/^[A-Za-z0-9_-]{12,80}$/D',$idempotencyKey)||
       ($callId!==''&&!preg_match('/^[A-Za-z0-9_-]{8,64}$/D',$callId)))
        throw new InvalidArgumentException('tool_invalid_correlation');
    $payload=voice_tool_payload((string)$policy['operation'],$input);
    $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $hash=hash('sha256',$json);
    $prior=one("SELECT id,payload_sha256,operation,status,reference_id,error_code,actor_id,policy_id,call_id
        FROM voice_tool_requests WHERE account_id=? AND site_id=? AND idempotency_key=?",
        [$accountId,$siteId,$idempotencyKey]);
    if($prior){
        if($prior['payload_sha256']!==$hash||$prior['operation']!==$policy['operation']||
           (int)$prior['actor_id']!==$actorId||(int)$prior['policy_id']!==$policyId||
           (string)$prior['call_id']!==$callId)
            throw new RuntimeException('tool_idempotency_conflict');
        return ['request_id'=>(int)$prior['id'],'status'=>$prior['status'],
            'reference_id'=>$prior['reference_id'],'reused'=>true,'approval_token'=>null];
    }
    $token=bin2hex(random_bytes(24));
    $approvalDigest=voice_tool_approval_digest($policy,$token);
    $id=insert("INSERT INTO voice_tool_requests(account_id,site_id,connector_id,policy_id,call_id,
      operation,actor_id,idempotency_key,payload_enc,payload_sha256,approval_sha256,status,prepared_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,'prepared',?)",
      [$accountId,$siteId,$policy['connector_id'],$policyId,$callId,$policy['operation'],
       $actorId,$idempotencyKey,seal_secret($json),$hash,$approvalDigest,now()]);
    $request=['id'=>$id,'account_id'=>$accountId,'site_id'=>$siteId,
        'call_id'=>$callId,'operation'=>$policy['operation']];
    voice_tool_trace($request,'prepared','owner_confirmation_required');
    return ['request_id'=>$id,'status'=>'prepared','requires_approval'=>true,
        'approval_token'=>$token,'preview'=>['operation'=>(string)$policy['operation'],
            'fields'=>$payload,'executed'=>false],
        'summary'=>'Do not tell caller an external action succeeded.'];
}
/** Restrict response to factual fields; never leak a WHMCS record into a voice prompt. */
function voice_tool_result(string $op,array $data):array {
    if(str_starts_with($op,'whmcs.')){
        if(($data['result']??'')!=='success')throw new RuntimeException('tool_whmcs_not_success');
        if($op==='whmcs.ticket.create'){
            $ref=$data['id']??$data['ticketid']??null;
            if(!(is_int($ref)||is_string($ref))||
               !preg_match('/^[0-9]{1,16}$/D',(string)$ref)||(int)$ref<1)
                throw new RuntimeException('tool_whmcs_ticket_id_missing');
            $ticketNumber=$data['tid']??null;
            if($ticketNumber!==null&&(!is_scalar($ticketNumber)||
                !preg_match('/^[A-Za-z0-9_-]{1,36}$/D',(string)$ticketNumber)))
                throw new RuntimeException('tool_whmcs_ticket_number_invalid');
            return ['reference_id'=>(string)$ref,'status'=>'succeeded',
                'ticket_number'=>$ticketNumber===null?null:(string)$ticketNumber,
                'reference_kind'=>'new_ticket_id'];
        }
        if($op==='whmcs.ticket.reply'){
            $reply=$data['replyid']??null;
            if($reply!==null&&(!is_string($reply)&&!is_int($reply)||
                !preg_match('/^[0-9]{1,16}$/D',(string)$reply)||(int)$reply<1))
                throw new RuntimeException('tool_whmcs_reply_id_invalid');
            return ['reference_id'=>$reply===null?null:(string)$reply,'status'=>'succeeded'];
        }
        $key=match($op){
            'whmcs.products'=>'products','whmcs.services'=>'products',
            'whmcs.invoices'=>'invoices','whmcs.tickets'=>'tickets'};
        $singular=match($op){
            'whmcs.products','whmcs.services'=>'product',
            'whmcs.invoices'=>'invoice','whmcs.tickets'=>'ticket'};
        $container=$data[$key]??null;
        $list=is_array($container)?($container[$singular]??null):null;
        if($list===null){
            $count=$data['totalresults']??null;
            if(($count!==0&&$count!=='0')||($container!==null&&!is_array($container)))
                throw new RuntimeException('tool_whmcs_read_shape_invalid');
            $list=[];
        }
        if(!is_array($list)||(!array_is_list($list)&&$list!==[]))
            throw new RuntimeException('tool_whmcs_read_shape_invalid');
        foreach($list as $item)if(!is_array($item))
            throw new RuntimeException('tool_whmcs_read_shape_invalid');
        return ['status'=>'succeeded','count'=>count($list),
            'data'=>voice_tool_public_projection($list)];
    }
    if($op==='rest.booking.create'){
        if(($data['success']??false)!==true)
            throw new RuntimeException('tool_booking_not_confirmed');
        $ref=$data['booking_id']??$data['reservation_id']??null;
        if(!(is_int($ref)||is_string($ref))||
           !preg_match('/^[A-Za-z0-9_-]{1,100}$/D',(string)$ref)||
           (string)$ref==='0')
            throw new RuntimeException('tool_booking_reference_missing');
        return ['status'=>'succeeded','reference_id'=>(string)$ref];
    }
    if($op==='rest.slots'){
        if(($data['success']??false)!==true||!is_array($data['slots']??null))
            throw new RuntimeException('tool_slots_unverified');
        $slots=[];
        foreach(array_slice($data['slots'],0,25) as $slot){
            if(!is_array($slot)||
               !(is_int($slot['id']??null)||is_string($slot['id']??null))||
               !(is_int($slot['start']??null)||is_string($slot['start']??null))||
               (string)$slot['id']===''||(string)$slot['start']==='')continue;
            $slots[]=['id'=>mb_substr((string)$slot['id'],0,90),
                'start'=>mb_substr((string)$slot['start'],0,50)];
        }
        return ['status'=>'succeeded','slots'=>$slots,'count'=>count($slots)];
    }
    throw new InvalidArgumentException('tool_unknown_operation');
}
function voice_tool_public_projection(mixed $data):array {
    if(!is_array($data))return [];
    $safe=[];$keys=['id','pid','status','name','description','price','currency','duedate','total','date','subject','tid','domain','nextduedate','billingcycle'];
    foreach(array_slice($data,0,20) as $item){
        if(!is_array($item))continue;
        $entry=[];
        foreach($keys as $key)if(isset($item[$key])&&is_scalar($item[$key]))
            $entry[$key]=mb_substr((string)$item[$key],0,180);
        if($entry)$safe[]=$entry;
    }
    return $safe;
}
/** A single outbound host-pinned HTTPS request, no redirects, bounded data. */
function voice_tool_dispatch(array $policy,array $payload,string $idempotencyKey):array {
    $op=(string)$policy['operation'];
    $target=voice_connector_url((string)$policy['base_url']);
    $ip=voice_connector_public_ip($target['host']);
    if(!function_exists('curl_init'))throw new RuntimeException('tool_curl_unavailable');
    $headers=['Accept: application/json'];
    $credential=unseal_secret($policy['secret_enc']??null)??'';
    $body='';$post=false;$path=(string)$policy['path'];
    if(str_starts_with($op,'whmcs.')){
        if($path!=='/includes/api.php'||$policy['type']!=='whmcs')throw new RuntimeException('tool_whmcs_mismatch');
        $pair=explode(':',$credential,2);
        if(count($pair)!==2||$pair[0]===''||$pair[1]==='')throw new RuntimeException('tool_whmcs_key_missing');
        $action=match($op){
            'whmcs.products'=>'GetProducts','whmcs.services'=>'GetClientsProducts',
            'whmcs.invoices'=>'GetInvoices','whmcs.tickets'=>'GetTickets',
            'whmcs.ticket.create'=>'OpenTicket','whmcs.ticket.reply'=>'AddTicketReply'};
        $fields=match($op){
            'whmcs.products'=>[],
            'whmcs.services','whmcs.tickets'=>['clientid'=>$payload['client_id']],
            'whmcs.invoices'=>['userid'=>$payload['client_id']],
            'whmcs.ticket.create'=>[
                'clientid'=>$payload['client_id'],'deptid'=>$payload['department_id'],
                'subject'=>$payload['subject'],'message'=>$payload['message'],'priority'=>'Medium'],
            'whmcs.ticket.reply'=>['ticketid'=>$payload['ticket_id'],'message'=>$payload['message']]
        };
        $body=http_build_query(['identifier'=>$pair[0],'secret'=>$pair[1],
            'action'=>$action,'responsetype'=>'json']+$fields);
        $headers[]='Content-Type: application/x-www-form-urlencoded';
        $post=true;
    }else{
        if(!in_array($policy['type'],['laravel','custom'],true))throw new RuntimeException('tool_rest_connector_mismatch');
        $path=voice_connector_path($path);
        if(str_contains($path,'?'))throw new RuntimeException('tool_query_denied');
        if($policy['auth_mode']==='bearer'){
            if($credential==='')throw new RuntimeException('tool_secret_missing');
            $headers[]='Authorization: Bearer '.$credential;
        }elseif($policy['auth_mode']==='wp_application'){
            if(!str_contains($credential,':'))throw new RuntimeException('tool_auth_invalid');
            $headers[]='Authorization: Basic '.base64_encode($credential);
        }elseif($policy['auth_mode']!=='none')throw new RuntimeException('tool_auth_mode_denied');
        if($op==='rest.slots')$path.='?'.http_build_query($payload);
        else{
            $post=true;$body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $headers[]='Content-Type: application/json';
            $headers[]='Idempotency-Key: '.$idempotencyKey;
        }
    }
    $url=$target['url'].$path;
    $ch=curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
        CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>13,
        CURLOPT_HTTPHEADER=>$headers,CURLOPT_PROXY=>'',
        CURLOPT_RESOLVE=>[$target['host'].':443:'.$ip],
        CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_USERAGENT=>'OnTrack-ToolGateway/1.0'
    ]);
    if($post){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,$body);}
    try{$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);}
    finally{curl_close($ch);}
    if(!is_string($raw)||strlen($raw)>262144||$status<200||$status>=300)
        throw new RuntimeException('tool_upstream_unavailable_or_ambiguous');
    $data=json_decode($raw,true,20);
    if(!is_array($data))throw new RuntimeException('tool_invalid_upstream_json');
    return voice_tool_result($op,$data);
}
/**
 * Confirmation must be a separate authenticated POST, and never from AI prose.
 * Write requests become uncertain after transport failure, NEVER retried automatically.
 */
function voice_tool_confirm(int $accountId,int $siteId,int $requestId,int $actorId,
    string $token,bool $consent,?callable $transport=null):array {
    voice_tools_init();
    voice_tool_actor($accountId,$actorId);
    if(!$consent||!preg_match('/^[a-f0-9]{48}$/D',$token))
        throw new InvalidArgumentException('tool_explicit_consent_required');
    $row=one("SELECT * FROM voice_tool_requests WHERE id=? AND account_id=? AND site_id=? AND actor_id=?",
        [$requestId,$accountId,$siteId,$actorId]);
    if(!$row)throw new InvalidArgumentException('tool_request_not_owned');
    $policy=voice_tool_policy($accountId,$siteId,(int)$row['policy_id']);
    if((int)$policy['connector_id']!==(int)$row['connector_id']||
       $policy['operation']!==$row['operation'])throw new RuntimeException('tool_policy_changed');
    if(!hash_equals((string)$row['approval_sha256'],voice_tool_approval_digest($policy,$token)))
        throw new InvalidArgumentException('tool_confirmation_token_invalid_or_policy_changed');
    if($row['status']!=='prepared')return ['status'=>$row['status'],'reference_id'=>$row['reference_id'],'replayed'=>true];
    $date=strtotime((string)$row['prepared_at'].' UTC');
    if($date===false||time()-$date>600)throw new RuntimeException('tool_confirmation_expired');
    $claimed=execute("UPDATE voice_tool_requests SET status='inflight'
        WHERE id=? AND account_id=? AND site_id=? AND status='prepared'",
        [$requestId,$accountId,$siteId]);
    if($claimed!==1)throw new RuntimeException('tool_concurrent_confirmation');
    voice_tool_trace($row,'inflight','provider_request_started');
    try{
        $json=unseal_secret((string)$row['payload_enc']);
        $payload=json_decode((string)$json,true,20,JSON_THROW_ON_ERROR);
        if(!is_array($payload)||hash('sha256',(string)$json)!==$row['payload_sha256'])
            throw new RuntimeException('tool_payload_integrity_error');
        $result=$transport!==null?$transport($policy,$payload,(string)$row['idempotency_key']):
            voice_tool_dispatch($policy,$payload,(string)$row['idempotency_key']);
        if(!is_array($result)||($result['status']??'')!=='succeeded')
            throw new RuntimeException('tool_provider_not_confirmed');
        $reference=$result['reference_id']??null;
        if(voice_tool_is_write((string)$row['operation'])&&
           (string)($reference??'')===''&&$row['operation']!=='whmcs.ticket.reply')
            throw new RuntimeException('tool_provider_reference_missing');
        $summary=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        execute("UPDATE voice_tool_requests SET status='succeeded',reference_id=?,
           result_summary=?,completed_at=? WHERE id=? AND account_id=?",
           [$reference,mb_substr($summary,0,1200),now(),$requestId,$accountId]);
        voice_tool_trace($row,'succeeded','provider_result_verified');
        return $result+['request_id'=>$requestId];
    }catch(Throwable $error){
        // On write, a network timeout might have reached upstream. NEVER retry blindly.
        $state=voice_tool_is_write((string)$row['operation'])?'uncertain':'failed';
        $errorCode=mb_substr($error->getMessage(),0,80);
        execute("UPDATE voice_tool_requests SET status=?,error_code=?,completed_at=?
           WHERE id=? AND account_id=?",[$state,$errorCode,now(),$requestId,$accountId]);
        voice_tool_trace($row,$state,'provider_result_not_verified');
        return ['status'=>$state,'request_id'=>$requestId,
            'error'=>'No confirmed external operation. Manual reconciliation required.',
            'retriable_automatically'=>false];
    }
}
function voice_tool_history(int $accountId,int $siteId,int $limit=50):array {
    voice_tools_init();
    $limit=max(1,min(100,$limit));
    return all("SELECT id,call_id,operation,status,reference_id,error_code,prepared_at,
      completed_at FROM voice_tool_requests WHERE account_id=? AND site_id=?
      ORDER BY id DESC LIMIT $limit",[$accountId,$siteId]);
}
