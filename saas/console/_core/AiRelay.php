<?php
declare(strict_types=1);
/** Private in-app platform relay. Independent Voice and Flow Builder pools. */
function ai_relay_init():void {
    $marker=private_path().'/.schema.ai-relay.v1';
    if(is_file($marker))return;
    $mysql=driver()==='mysql';
    $pk=$mysql?'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS ai_relay_entries(
      id $pk,account_id BIGINT NOT NULL,scope VARCHAR(12) NOT NULL,
      driver VARCHAR(25) NOT NULL,label VARCHAR(100) NOT NULL,
      model VARCHAR(120) NOT NULL,endpoint VARCHAR(600) NOT NULL DEFAULT '',
      secret_enc TEXT NOT NULL,priority INTEGER NOT NULL,enabled INTEGER NOT NULL DEFAULT 1,
      cooldown_until VARCHAR(19) NULL,last_status VARCHAR(32) NOT NULL DEFAULT 'new',
      last_checked_at VARCHAR(19) NULL,created_at VARCHAR(19) NOT NULL
    )".$engine);
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}
function ai_relay_platform():int {
    $row=one("SELECT id FROM accounts WHERE kind='platform' AND status='active' ORDER BY id LIMIT 1");
    if(!$row)throw new RuntimeException('relay_platform_not_found');
    return (int)$row['id'];
}
/** One-time adoption of an existing encrypted Google Live platform credential.
 * No plaintext is read or exported. Never re-import after a manual deletion. */
function ai_relay_legacy_voice_once():void {
    $mark=private_path().'/.schema.ai-relay-voice-import.v1';
    if(is_file($mark))return;
    $platform=ai_relay_platform();
    $existing=one("SELECT COUNT(*) n FROM ai_relay_entries WHERE account_id=? AND scope='voice'",[$platform]);
    if((int)($existing['n']??0)===0){
        $legacy=one("SELECT name,secret_enc FROM integrations WHERE account_id=? AND provider='google_live'
            AND status='active' ORDER BY id DESC LIMIT 1",[$platform]);
        if($legacy&&is_string($legacy['secret_enc']??null)&&
            unseal_secret($legacy['secret_enc'])!==null){
            insert("INSERT INTO ai_relay_entries(account_id,scope,driver,label,model,endpoint,
                secret_enc,priority,enabled,last_status,created_at)
                VALUES(?,'voice','google_live','Google Live (existing)',?,'',?,1,1,'imported',?)",
                [$platform,(string)$legacy['name'],(string)$legacy['secret_enc'],now()]);
        }
    }
    file_put_contents($mark,now(),LOCK_EX);@chmod($mark,0600);
}
function ai_relay_rows(string $scope,bool $enabledOnly=false,?int $accountId=null):array {
    if(!in_array($scope,['voice','assistant'],true))throw new InvalidArgumentException('relay_scope');
    ai_relay_init();
    if($scope==='voice')ai_relay_legacy_voice_once();
    return all("SELECT id,account_id,scope,driver,label,model,endpoint,priority,enabled,
        cooldown_until,last_status,last_checked_at FROM ai_relay_entries
        WHERE account_id=? AND scope=? ".($enabledOnly?'AND enabled=1 ':'').
        "ORDER BY priority ASC,id ASC",[$accountId??ai_relay_platform(),$scope]);
}
function ai_relay_one(int $id,?int $accountId=null):?array {
    ai_relay_init();
    return one('SELECT * FROM ai_relay_entries WHERE id=? AND account_id=?',
        [$id,$accountId??ai_relay_platform()]);
}
function ai_relay_has(string $scope):bool {return ai_relay_rows($scope)!==[];}
function ai_relay_endpoint(string $url):string {
    $u=parse_url($url);
    if(!is_array($u)||strlen($url)>600||strtolower((string)($u['scheme']??''))!=='https'||
       isset($u['user'])||isset($u['pass'])||isset($u['fragment'])||isset($u['query'])||
       (isset($u['port'])&&(int)$u['port']!==443))
        throw new InvalidArgumentException('المزوّد المخصص يحتاج HTTPS من غير مفاتيح في URL');
    $host=strtolower((string)($u['host']??''));
    if(!preg_match('/\A[a-z0-9-]+(?:\.[a-z0-9-]+)+\z/D',$host)||strlen($host)>253||
        filter_var($host,FILTER_VALIDATE_IP)||!str_starts_with((string)($u['path']??''),'/')||
        str_contains((string)$u['path'],'..'))
        throw new InvalidArgumentException('عنوان المزوّد غير مسموح؛ استخدم دومين HTTPS عام');
    return $url;
}

/**
 * Assistant/Flow Text BYOK is independent of Live Voice keys. Existing user
 * plans start at zero assistant keys until an owner grants a plan entitlement.
 * Platform and reseller have their own unmetered pools; no cross-tenant key
 * access or browser delivery of decrypted secrets.
 */
function ai_relay_assistant_limits_schema():void {
    $mark=private_path().'/.schema.ai-assistant-key-limit.v1';
    if(is_file($mark))return;
    $cols=driver()==='mysql'
        ?array_column(all('SHOW COLUMNS FROM plans'),'Field')
        :array_column(all('PRAGMA table_info(plans)'),'name');
    if(!in_array('assistant_key_limit',$cols,true))
        db()->exec('ALTER TABLE plans ADD COLUMN assistant_key_limit INTEGER NOT NULL DEFAULT 0');
    file_put_contents($mark,now(),LOCK_EX);@chmod($mark,0600);
}
function ai_relay_assistant_key_limit(int $accountId):int {
    ai_relay_assistant_limits_schema();
    $owner=account($accountId);
    if(!$owner||$owner['status']!=='active')return 0;
    if(in_array($owner['kind'],['platform','reseller'],true))return 0; // unlimited
    $row=one("SELECT p.assistant_key_limit FROM subscriptions s
        JOIN plans p ON p.id=s.plan_id AND p.kind='user' AND p.status='active'
        WHERE s.account_id=? AND s.status='active'
          AND s.starts_at<=? AND s.ends_at>?
        ORDER BY s.id DESC LIMIT 1",[$accountId,now(),now()]);
    return max(0,min(1000,(int)($row['assistant_key_limit']??0)));
}
function ai_relay_assistant_pool_owner(int $accountId):int {
    $owner=account($accountId);
    if(!$owner||$owner['status']!=='active')
        throw new RuntimeException('assistant_account_inactive');
    if(ai_relay_rows('assistant',false,$accountId)!==[]){
        if($owner['kind']==='customer'&&ai_relay_assistant_key_limit($accountId)<1)
            throw new RuntimeException('assistant_byok_plan_disabled');
        return $accountId;
    }
    if($owner['kind']==='customer'&&(int)($owner['parent_id']??0)>0){
        $reseller=account((int)$owner['parent_id']);
        if($reseller&&$reseller['kind']==='reseller'&&$reseller['status']==='active'&&
            ai_relay_rows('assistant',false,(int)$reseller['id'])!==[])
            return (int)$reseller['id'];
    }
    return ai_relay_platform();
}
function ai_relay_is_compatible_text_provider(string $driver):bool {
    return in_array($driver,['openai_compatible','omnira_a','omnira_b','nvidia'],true);
}

function ai_relay_add(int $account,string $scope,string $driver,string $name,string $model,string $endpoint,string $key):int {
    ai_relay_init();
    if(!in_array($scope,['voice','assistant'],true)||
       !in_array($driver,$scope==='voice'?['google_live']:
            ['google_live','google_text','openai_compatible','omnira_a','omnira_b','nvidia'],true))
        throw new InvalidArgumentException('نوع مزوّد غير صالح');
    if($account!==ai_relay_platform()){
        $owner=account($account);
        if(!$owner||$owner['status']!=='active')
            throw new RuntimeException('relay_account_inactive');
        require_once __DIR__.'/GoogleTenantKeys.php';
        $limit=$scope==='voice'?google_voice_key_limit($account):
            ai_relay_assistant_key_limit($account);
        if($owner['kind']==='customer'&&
            (int)one('SELECT COUNT(*) n FROM ai_relay_entries WHERE account_id=? AND scope=?',
                [$account,$scope])['n'] >= $limit)
            throw new RuntimeException('relay_plan_key_limit_reached');
    }
    if(mb_strlen($name)<2||mb_strlen($name)>100||
       !preg_match('/\A[A-Za-z0-9._-]{4,120}\z/D',$model)||
       strlen($key)<4||strlen($key)>512||preg_match('/[\r\n]/',$key))
        throw new InvalidArgumentException('الاسم أو الموديل أو المفتاح غير صالح');
    $endpoint=ai_relay_is_compatible_text_provider($driver)?ai_relay_endpoint($endpoint):'';
    $maximum=one('SELECT COALESCE(MAX(priority),0) n FROM ai_relay_entries WHERE account_id=? AND scope=?',[$account,$scope]);
    return insert('INSERT INTO ai_relay_entries(account_id,scope,driver,label,model,endpoint,secret_enc,priority,enabled,created_at)
      VALUES(?,?,?,?,?,?,?,?,1,?)',[$account,$scope,$driver,trim($name),$model,$endpoint,seal_secret($key),(int)$maximum['n']+1,now()]);
}
function ai_relay_edit(int $id,string $name,string $model,string $endpoint,string $key,?int $accountId=null):void {
    $row=ai_relay_one($id,$accountId);
    if(!$row)throw new InvalidArgumentException('المزوّد غير موجود');
    if(mb_strlen(trim($name))<2||mb_strlen($name)>100||
       !preg_match('/\A[A-Za-z0-9._-]{4,120}\z/D',$model)||
       strlen($key)>512||preg_match('/[\r\n]/',$key))
        throw new InvalidArgumentException('اسم أو موديل أو مفتاح غير صالح');
    $url=ai_relay_is_compatible_text_provider((string)$row['driver'])?ai_relay_endpoint($endpoint):'';
    $encrypted=$key!==''?seal_secret($key):$row['secret_enc'];
    execute("UPDATE ai_relay_entries SET label=?,model=?,endpoint=?,secret_enc=?,
        cooldown_until=NULL,last_status='edited' WHERE id=? AND account_id=?",
        [trim($name),$model,$url,$encrypted,$id,$row['account_id']]);
}
function ai_relay_modify(int $id,string $action,?int $accountId=null):void {
    $item=ai_relay_one($id,$accountId);
    if(!$item)throw new InvalidArgumentException('المزوّد غير موجود');
    if($action==='toggle'){
        execute("UPDATE ai_relay_entries SET enabled=?,last_status='manual' WHERE id=?",
            [1-(int)$item['enabled'],$id]);return;
    }
    if($action==='reset'){
        execute("UPDATE ai_relay_entries SET cooldown_until=NULL,last_status='ready' WHERE id=?",[$id]);return;
    }
    if($action==='delete'){execute('DELETE FROM ai_relay_entries WHERE id=?',[$id]);return;}
    if($action==='up'||$action==='down'){
        $rows=ai_relay_rows((string)$item['scope'],false,(int)$item['account_id']);
        $ids=array_map('intval',array_column($rows,'id'));
        $pos=array_search($id,$ids,true);
        $other=$pos+($action==='up'?-1:1);
        if($pos===false||$other<0||$other>=count($ids))return;
        [$ids[$pos],$ids[$other]]=[$ids[$other],$ids[$pos]];
        foreach($ids as $offset=>$entry)
            execute('UPDATE ai_relay_entries SET priority=? WHERE id=?',[$offset+1,$entry]);
        return;
    }
    throw new InvalidArgumentException('relay_action');
}
function ai_relay_error(Throwable $e):string {
    $s=$e->getMessage();
    if(preg_match('/(?:HTTP[_ ]?|http_)(429|500|502|503|504|401|403|404|400)\b/i',$s,$m))return $m[1];
    if(preg_match('/quota|rate.?limit|RESOURCE_EXHAUSTED/i',$s))return '429';
    if(preg_match('/timeout|read_failed|connection_lost/i',$s))return 'timeout';
    return 'error';
}
function ai_relay_mark(int $id,string $code):void {
    if($code==='ok'){
        // A concurrent successful token issuance must NOT cancel a 429/503
        // cooldown from another live call on the same credential.
        execute("UPDATE ai_relay_entries SET last_status='ok',last_checked_at=?,cooldown_until=NULL
            WHERE id=? AND (cooldown_until IS NULL OR cooldown_until<=?)",
            [now(),$id,now()]);
        return;
    }
    $cool=in_array($code,['429','503','timeout'],true)?gmdate('Y-m-d H:i:s',time()+900):null;
    execute('UPDATE ai_relay_entries SET last_status=?,last_checked_at=?,cooldown_until=? WHERE id=?',
        [$code,now(),$cool,$id]);
}
function ai_relay_run(string $scope,callable $callback,?int $accountId=null):mixed {
    $all=ai_relay_rows($scope,false,$accountId);
    if($scope==='voice'&&$accountId!==null&&$accountId!==ai_relay_platform()){
        $poolAccount=account($accountId);
        if($poolAccount&&$poolAccount['kind']==='customer'){
            require_once __DIR__.'/GoogleTenantKeys.php';
            $limit=google_voice_key_limit($accountId);
            if($limit<1)throw new RuntimeException('google_key_plan_limit_reached');
            $all=array_slice($all,0,$limit);
        }
    }
    if($scope==='assistant'&&$accountId!==null&&$accountId!==ai_relay_platform()){
        $pool=account($accountId);
        if(!$pool||$pool['status']!=='active')
            throw new RuntimeException('assistant_pool_inactive');
        if($pool['kind']==='customer'){
            $limit=ai_relay_assistant_key_limit($accountId);
            if($limit<1)throw new RuntimeException('assistant_key_plan_limit_reached');
            $all=array_slice($all,0,$limit);
        }
    }
    $rows=array_values(array_filter($all,static fn($row)=>(int)$row['enabled']===1));
    if(!$rows)throw new RuntimeException('ai_relay_no_enabled_entries');
    $count=0;$last='not_available';
    foreach($rows as $row){
        if(!empty($row['cooldown_until'])&&$row['cooldown_until']>now())continue;
        $count++;
        $full=ai_relay_one((int)$row['id'],(int)$row['account_id']);
        $key=$full?unseal_secret($full['secret_enc']??null):null;
        if(!is_string($key)||$key===''){ai_relay_mark((int)$row['id'],'key_unavailable');continue;}
        try{$result=$callback($row,$key);ai_relay_mark((int)$row['id'],'ok');return $result;}
        catch(Throwable $e){$last=ai_relay_error($e);ai_relay_mark((int)$row['id'],$last);}
    }
    throw new RuntimeException($count===0?'كل مفاتيح '.$scope.' في فترة تهدئة 15 دقيقة':
        'كل مزوّدي '.$scope.' المتاحين فشلوا؛ آخر كود '.$last);
}
/** Public-DNS pinned HTTPS only: no localhost, private address, redirect or proxy. */
function ai_relay_custom_request(string $url,string $key,array $body):array {
    ai_relay_endpoint($url);
    $host=(string)parse_url($url,PHP_URL_HOST);
    $answers=@dns_get_record($host,DNS_A);
    if(!is_array($answers)||$answers===[])throw new RuntimeException('relay_dns_failed');
    $ip='';
    foreach($answers as $answer){
        $value=(string)($answer['ip']??'');
        if(!filter_var($value,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))
            throw new RuntimeException('relay_private_dns_denied');
        if($ip==='')$ip=$value;
    }
    $payload=json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if(strlen($payload)>110000)throw new RuntimeException('relay_request_size');
    $ch=curl_init($url);
    if(!$ch)throw new RuntimeException('relay_curl_failed');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$key],
        CURLOPT_RESOLVE=>[$host.':443:'.$ip],CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>40,CURLOPT_CONNECTTIMEOUT=>7,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
    try{$reply=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);}finally{curl_close($ch);}
    if($status!==200||!is_string($reply))throw new RuntimeException('relay_HTTP_'.$status);
    if(strlen($reply)>120000)throw new RuntimeException('relay_response_size');
    $doc=json_decode($reply,true,40);
    if(!is_array($doc))throw new RuntimeException('relay_invalid_json');
    return $doc;
}
function ai_relay_parse_object(string $raw):array {
    $value=json_decode(trim($raw),true,40);
    if(!is_array($value)||array_is_list($value))throw new RuntimeException('relay_invalid_structured_response');
    return $value;
}
/** Assistant legacy continuity is independent of the Voice Relay pool.
 * Once Assistant providers exist, their own credentials and cooldown apply. */
function ai_relay_assistant_legacy_key():string {
    $env=trim((string)getenv('ONTRACK_GEMINI_API_KEY'));
    if($env!=='')return $env;
    $legacy=one("SELECT secret_enc FROM integrations WHERE account_id=? AND provider='google_live'
        AND status='active' ORDER BY id DESC LIMIT 1",[ai_relay_platform()]);
    $key=$legacy?unseal_secret($legacy['secret_enc']??null):null;
    if(!is_string($key)||$key==='')throw new RuntimeException('assistant_provider_not_configured');
    return $key;
}
function ai_relay_proposal(string $system,string $prompt,array $schema,?int $callerAccountId=null):array {
    $poolOwner=$callerAccountId===null?ai_relay_platform():
        ai_relay_assistant_pool_owner($callerAccountId);
    if(ai_relay_rows('assistant',false,$poolOwner)===[]){
        $setup=flow_ai_live_setup($system."\nOnly call submit_editor_proposal; never speak or execute actions.",$schema);
        return flow_ai_live_exchange(ai_relay_assistant_legacy_key(),$setup,$prompt);
    }
    return ai_relay_run('assistant',static function(array $row,string $key)use($system,$prompt,$schema):array {
        if($row['driver']==='google_live'){
            $setup=flow_ai_live_setup($system."\nOnly submit the structured editor proposal.",$schema,(string)$row['model']);
            return flow_ai_live_exchange($key,$setup,$prompt);
        }
        if($row['driver']==='google_text'){
            require_once __DIR__.'/FlowAiTransport.php';
            $payload=['systemInstruction'=>['parts'=>[['text'=>$system]]],
                'contents'=>[['role'=>'user','parts'=>[['text'=>$prompt]]]],
                'generationConfig'=>['responseMimeType'=>'application/json','responseSchema'=>$schema,'maxOutputTokens'=>7000]];
            $raw=flow_ai_google_request($payload,$key,(string)$row['model'],120000);
            $doc=json_decode($raw,true,40);
            $text='';
            foreach((array)($doc['candidates'][0]['content']['parts']??[]) as $part)
                if(($part['thought']??false)!==true&&is_string($part['text']??null))$text.=$part['text'];
            return ai_relay_parse_object($text);
        }
        $doc=ai_relay_custom_request((string)$row['endpoint'],$key,
          ['model'=>$row['model'],'messages'=>[['role'=>'system','content'=>$system."\nOnly one JSON object, no prose."],
             ['role'=>'user','content'=>$prompt]],'response_format'=>['type'=>'json_object']]);
        $m=$doc['choices'][0]['message']??null;
        if(!is_array($m))throw new RuntimeException('relay_missing_completion');
        foreach((array)($m['tool_calls']??[]) as $call)
            if(($call['function']['name']??'')==='submit_editor_proposal')
                return ai_relay_parse_object((string)($call['function']['arguments']??''));
        return ai_relay_parse_object((string)($m['content']??''));
    },$poolOwner);
}
function ai_relay_model(?int $callerAccountId=null):string {
    // Lightweight protocol tests intentionally run without database bootstrap.
    if(!function_exists('private_path')||!function_exists('db'))return flow_ai_live_model();
    $owner=$callerAccountId===null?ai_relay_platform():
        ai_relay_assistant_pool_owner($callerAccountId);
    foreach(ai_relay_rows('assistant',true,$owner) as $row)
        if(empty($row['cooldown_until'])||$row['cooldown_until']<=now())
            return (string)$row['model'];
    return flow_ai_live_model();
}
function ai_relay_voice_session(int $minutes,?int $callerAccountId=null):array {
    require_once __DIR__.'/GoogleTenantKeys.php';
    $owner=$callerAccountId===null?ai_relay_platform():google_voice_pool_owner($callerAccountId);
    return ai_relay_run('voice',static function(array $row,string $key)use($minutes,$owner){
        // Server-only metadata for a failover ticket; no master key is sent to Android.
        return ['token'=>tenant_voice_ephemeral_token($key,$minutes),'model'=>$row['model'],
            'entry_id'=>(int)$row['id'],'pool_owner_id'=>$owner];
    },$owner);
}
function ai_relay_test(int $id,?int $accountId=null):string {
    $row=ai_relay_one($id,$accountId);
    if(!$row)throw new InvalidArgumentException('المزوّد غير موجود');
    $key=(string)(unseal_secret($row['secret_enc']??null)??'');
    try{
        if($row['driver']==='google_live'){
            require_once __DIR__.'/TenantVoiceSession.php';
            tenant_voice_ephemeral_token($key,1);
        }elseif($row['driver']==='google_text'){
            require_once __DIR__.'/VoiceCatalog.php';
            google_api_probe($key);
        }else{
            $result=ai_relay_custom_request((string)$row['endpoint'],$key,
                ['model'=>$row['model'],'messages'=>[['role'=>'user','content'=>'Return a JSON object: {"ok":true}']],
                 'response_format'=>['type'=>'json_object'],'max_tokens'=>32]);
            if(!isset($result['choices'][0]))throw new RuntimeException('relay_test_no_response');
        }
        ai_relay_mark($id,'ok');return 'اتصال المزود نجح.';
    }catch(Throwable $e){
        $code=ai_relay_error($e);ai_relay_mark($id,$code);
        return 'فشل اختبار الاتصال ('.$code.'). راجع الموديل وصلاحيات API.';
    }
}
