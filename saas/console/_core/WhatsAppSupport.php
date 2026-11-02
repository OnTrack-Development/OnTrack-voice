<?php
declare(strict_types=1);
/**
 * Meta Cloud API WhatsApp Inbox, owned by OnTrack Voice. Native OnTrack agents
 * run independently; existing WHMCS addons are optional integrations only.
 */
function wa_e164(string $phone):string {
    $digits=preg_replace('/[^0-9]/','',$phone);
    if(!is_string($digits)||strlen($digits)<8||strlen($digits)>15)
        throw new InvalidArgumentException('whatsapp_phone_invalid');
    return $digits;
}
function wa_audit(int $accountId,?int $conversationId,string $event,string $detail=''):void {
    insert('INSERT INTO wa_audit(account_id,conversation_id,event,detail,created_at) VALUES(?,?,?,?,?)',
        [$accountId,$conversationId,mb_substr($event,0,55),mb_substr($detail,0,180),now()]);
}
function wa_connection(int $id,int $accountId):array {
    $row=one('SELECT * FROM wa_connections WHERE id=? AND account_id=?',[$id,$accountId]);
    if(!$row)throw new InvalidArgumentException('whatsapp_connection_not_found');
    return $row;
}
function wa_conversation(int $id,int $accountId):array {
    $row=one('SELECT * FROM wa_conversations WHERE id=? AND account_id=?',[$id,$accountId]);
    if(!$row)throw new InvalidArgumentException('whatsapp_conversation_not_found');
    return $row;
}
function wa_bridge_url(string $url):string {
    $url=trim($url);
    if($url==='')return '';
    $p=parse_url($url);
    // The existing WHMCS AI Support Agent is at OnTrack's WHMCS hostname.
    // No user-specified hosts, embedded secrets, arbitrary query or redirects.
    if(!is_array($p)||($p['scheme']??'')!=='https'||
        strtolower((string)($p['host']??''))!=='services.ontrackegy.com'||
        isset($p['port'])||isset($p['user'])||isset($p['pass'])||
        isset($p['query'])||isset($p['fragment'])||
        !preg_match('~^/modules/addons/ai_support_agent/[a-zA-Z0-9/_-]+\.php$~D',
            (string)($p['path']??''))||strlen($url)>350)
        throw new InvalidArgumentException('WHMCS bridge must be an authenticated OnTrack AI Support Agent addon endpoint');
    return $url;
}
function wa_graph_version(array $connection):string {
    $version=trim((string)($connection['graph_version']??''));
    if(!preg_match('/^v[0-9]{1,2}\\.0$/D',$version)){
        $version=trim((string)getenv('ONTRACK_META_GRAPH_VERSION'));
        if(!preg_match('/^v[0-9]{1,2}\\.0$/D',$version))$version='v26.0';
    }
    return $version;
}
function wa_public_callback(string $publicId):string {
    if(!preg_match('/^[a-f0-9]{36}$/D',$publicId))
        throw new InvalidArgumentException('callback_invalid');
    return 'https://app.ontrackegy.com/saas/console/whatsapp-webhook.php?id='.$publicId;
}
function wa_create_connection(int $accountId,int $siteId,string $label,string $phoneId,
 string $token,string $appSecret,string $verifyToken,string $bridgeUrl='',string $bridgeToken='',int $aiAgentId=0,
 string $wabaId='',string $metaAppId='',string $graphVersion='v26.0',string $publicId='',string $relaySecret=''):int {
    if(!one("SELECT id FROM sites WHERE id=? AND account_id=? AND status='active'",[$siteId,$accountId]))
        throw new InvalidArgumentException('whatsapp_site_not_available');
    $label=trim($label);
    if(mb_strlen($label)<2||mb_strlen($label)>120||
       !preg_match('/^[0-9]{5,100}$/D',$phoneId)||
       strlen($token)<8||strlen($token)>2000||
       strlen($appSecret)<8||strlen($appSecret)>1000||
       strlen($verifyToken)<12||strlen($verifyToken)>200||
       preg_match('/[\\x00-\\x1f\\x7f]/',$token.$appSecret.$verifyToken.$bridgeToken))
        throw new InvalidArgumentException('whatsapp_connection_fields_invalid');
    if($wabaId!==''&&!preg_match('/^[0-9]{5,100}$/D',$wabaId))
        throw new InvalidArgumentException('WABA ID غير صالح');
    if($metaAppId!==''&&!preg_match('/^[0-9]{5,100}$/D',$metaAppId))
        throw new InvalidArgumentException('Meta App ID غير صالح');
    if(!preg_match('/^v[0-9]{1,2}\\.0$/D',$graphVersion))
        throw new InvalidArgumentException('Graph API Version غير صالح');
    if(one('SELECT id FROM wa_connections WHERE phone_number_id=? LIMIT 1',[$phoneId]))
        throw new InvalidArgumentException('Phone Number ID مرتبط بالفعل؛ راجع الاتصال الحالي.');
    if($publicId!==''&&(!preg_match('/^[a-f0-9]{36}$/D',$publicId)||
        one('SELECT id FROM wa_connections WHERE public_id=? LIMIT 1',[$publicId])))
        throw new InvalidArgumentException('معرّف Callback URL غير صالح؛ حدّث الصفحة.');
    if($relaySecret!==''&&!preg_match('/^[a-f0-9]{64}$/D',$relaySecret))
        throw new InvalidArgumentException('Webhook relay secret غير صالح');
    if($aiAgentId<0)throw new InvalidArgumentException('whatsapp_agent_invalid');
    if($aiAgentId>0){
        require_once __DIR__.'/PublishedFlow.php';
        if(!published_agent_flow($accountId,$siteId,$aiAgentId))
            throw new InvalidArgumentException('whatsapp_agent_must_be_published_on_this_site');
    }
    $bridge=wa_bridge_url($bridgeUrl);
    if(($bridge==='')!==($bridgeToken==='')||strlen($bridgeToken)>2000)
        throw new InvalidArgumentException('whmcs_bridge_url_and_token_required_together');
    $public=$publicId!==''?$publicId:bin2hex(random_bytes(18));
    $id=insert("INSERT INTO wa_connections
      (account_id,site_id,public_id,label,phone_number_id,access_token_enc,app_secret_enc,
       verify_token_hash,whmcs_bridge_url,whmcs_bridge_token_enc,state,created_at,updated_at,
       waba_id,meta_app_id,graph_version)
       VALUES (?,?,?,?,?,?,?,?,?,?,'draft',?,?,?,?,?)",[
       $accountId,$siteId,$public,$label,$phoneId,seal_secret($token),
       seal_secret($appSecret),hash('sha256',$verifyToken),$bridge!==''?$bridge:null,
       $bridgeToken!==''?seal_secret($bridgeToken):null,now(),now(),
       $wabaId!==''?$wabaId:null,$metaAppId!==''?$metaAppId:null,$graphVersion
    ]);
    if($aiAgentId>0)
        execute('UPDATE wa_connections SET ai_agent_id=? WHERE id=? AND account_id=?',
            [$aiAgentId,$id,$accountId]);
    if($relaySecret!=='')execute('UPDATE wa_connections SET webhook_relay_enabled=1,webhook_relay_secret_enc=? WHERE id=? AND account_id=?',
        [seal_secret($relaySecret),$id,$accountId]);
    wa_audit($accountId,null,'whatsapp.connection.created','draft, independent agent disabled');
    return $id;
}
/**
 * Signed provider-to-OnTrack forwarding. The webhook of the user's existing
 * WhatsApp Notifications module may forward the original Meta JSON OR this
 * minimal normalized event contract. This does NOT call arbitrary remote URLs.
 * A URL that only receives Meta callbacks cannot be used as a reply API.
 */
function wa_relay_signature_valid(array $connection,string $header,string $raw):bool {
    if(empty($connection['webhook_relay_enabled']))return false;
    $secret=unseal_secret($connection['webhook_relay_secret_enc']??null);
    if(!is_string($secret)||!preg_match('/^[a-f0-9]{64}$/D',$secret))return false;
    if(!preg_match('/^sha256=[a-f0-9]{64}$/D',$header))return false;
    return hash_equals('sha256='.hash_hmac('sha256',$raw,$secret),$header);
}
function wa_relay_normalize(array $connection,array $payload):array {
    if(($payload['object']??null)==='whatsapp_business_account')return $payload;
    if(($payload['version']??null)!==1||
        ($payload['channel']??null)!=='whatsapp'||
        ($payload['event']??null)!=='message.received'||
        !is_array($payload['message']??null))
        throw new InvalidArgumentException('unsupported_webhook_payload');
    $expected=(string)$connection['phone_number_id'];
    if((string)($payload['phone_number_id']??'')!==$expected)
        throw new InvalidArgumentException('relay_phone_mismatch');
    $message=$payload['message'];
    $id=(string)($message['id']??'');
    if(!preg_match('/^[A-Za-z0-9._:-]{8,150}$/D',$id))
        throw new InvalidArgumentException('relay_message_id_invalid');
    $from=wa_e164((string)($message['from']??''));
    $text=trim((string)($message['text']??''));
    if($text===''||mb_strlen($text)>4096)
        throw new InvalidArgumentException('relay_message_text_invalid');
    $name=trim((string)($message['name']??''));
    if(mb_strlen($name)>150)throw new InvalidArgumentException('relay_contact_name_invalid');
    return ['object'=>'whatsapp_business_account','entry'=>[[
        'changes'=>[['field'=>'messages','value'=>[
            'metadata'=>['phone_number_id'=>$expected],
            'contacts'=>[['wa_id'=>$from,'profile'=>['name'=>$name]]],
            'messages'=>[['id'=>$id,'from'=>$from,'type'=>'text','text'=>['body'=>$text]]]
        ]]]
    ]]];
}
/**
 * Keep the original Meta callback untouched. A previously connected module
 * forwards signed original events to a DIFFERENT per-tenant OnTrack URL.
 * Existing Meta token/App Secret are needed for sending AI replies.
 */
function wa_create_existing_connection(int $accountId,int $siteId,int $agentId,
 string $label,string $sourceUrl,string $sourceVerify,string $phoneId,
 string $wabaId,string $metaAppId,string $graphVersion,string $accessToken,string $appSecret,
 string $publicId,string $relaySecret):int {
    $sourceUrl=trim($sourceUrl);
    $url=parse_url($sourceUrl);
    if(!is_array($url)||($url['scheme']??'')!=='https'||
       empty($url['host'])||isset($url['user'])||isset($url['pass'])||
       isset($url['fragment'])||strlen($sourceUrl)>400||
       !preg_match('/^[a-zA-Z0-9.-]+$/D',(string)($url['host']??''))||
       !str_contains((string)$url['host'],'.'))
        throw new InvalidArgumentException('رابط Webhook الحالي غير صالح؛ مطلوب HTTPS عام');
    if(strlen($sourceVerify)<12||strlen($sourceVerify)>200)
        throw new InvalidArgumentException('Verify Token الحالي غير صالح');
    if(!preg_match('/^[a-f0-9]{64}$/D',$relaySecret))
        throw new InvalidArgumentException('مفتاح التوقيع غير صالح');
    // The original token is a reference only; Meta keeps the original callback.
    $newVerify=bin2hex(random_bytes(24));
    $id=wa_create_connection($accountId,$siteId,$label,$phoneId,$accessToken,
        $appSecret,$newVerify,'','',$agentId,$wabaId,$metaAppId,$graphVersion,
        $publicId,$relaySecret);
    execute("UPDATE wa_connections SET onboarding_method='existing_webhook',
        source_callback_url=?,source_verify_hash=?,updated_at=?
        WHERE id=? AND account_id=?",[
        $sourceUrl,hash('sha256',$sourceVerify),now(),$id,$accountId
    ]);
    wa_audit($accountId,null,'whatsapp.existing.setup',
        'existing Meta webhook kept unchanged; awaiting signed message forwarding');
    return $id;
}
function wa_webhook_challenge(array $connection,string $mode,string $token,string $challenge):?string {
    if($mode!=='subscribe'||$token===''||strlen($challenge)>300||
      !hash_equals((string)$connection['verify_token_hash'],hash('sha256',$token)))return null;
    return $challenge;
}
function wa_signature_valid(array $connection,string $header,string $raw):bool {
    $secret=unseal_secret($connection['app_secret_enc']??null);
    if(!$secret||!preg_match('/^sha256=[a-f0-9]{64}$/D',$header))return false;
    return hash_equals('sha256='.hash_hmac('sha256',$raw,$secret),$header);
}
function wa_webhook_ingest(array $connection,array $payload):array {
    if(($payload['object']??'')!=='whatsapp_business_account')return ['accepted'=>0,'duplicates'=>0];
    $accepted=0;$duplicate=0;$conversationIds=[];
    foreach((array)($payload['entry']??[]) as $entry){
        foreach((array)($entry['changes']??[]) as $change){
            $value=$change['value']??null;
            if(!is_array($value)||($value['metadata']['phone_number_id']??null)!==(string)$connection['phone_number_id'])
                continue; // fail closed on cross-connection callbacks
            // Meta delivery receipts are distinct from send acceptance.
            foreach((array)($value['statuses']??[]) as $status){
                if(!is_array($status))continue;
                $providerId=(string)($status['id']??'');
                $state=(string)($status['status']??'');
                if(!preg_match('/^[A-Za-z0-9._:-]{5,160}$/D',$providerId)||
                    !in_array($state,['sent','delivered','read','failed'],true))continue;
                $message=one("SELECT id,conversation_id,delivery_status FROM wa_messages
                    WHERE connection_id=? AND account_id=? AND provider_message_id=? AND role IN ('staff','ai')",
                    [(int)$connection['id'],(int)$connection['account_id'],$providerId]);
                if(!$message)continue;
                $ranks=['accepted'=>0,'sent'=>1,'delivered'=>2,'read'=>3,'failed'=>1];
                $old=(string)$message['delivery_status'];
                if(($ranks[$state]??0)>($ranks[$old]??0)){
                    execute('UPDATE wa_messages SET delivery_status=? WHERE id=? AND account_id=?',
                        [$state,(int)$message['id'],(int)$connection['account_id']]);
                }
            }
            $profiles=[];
            foreach((array)($value['contacts']??[]) as $profile){
                if(is_array($profile)&&is_string($profile['wa_id']??null))
                    $profiles[$profile['wa_id']]=mb_substr((string)($profile['profile']['name']??''),0,150);
            }
            foreach((array)($value['messages']??[]) as $message){
                if(!is_array($message))continue;
                $providerId=(string)($message['id']??'');
                $from=(string)($message['from']??'');
                if(!preg_match('/^[A-Za-z0-9._:-]{5,160}$/D',$providerId))continue;
                try{$from=wa_e164($from);}catch(InvalidArgumentException){continue;}
                $type=(string)($message['type']??'');
                $body=match($type){
                    'text'=>(string)($message['text']['body']??''),
                    'button'=>(string)($message['button']['text']??''),
                    'interactive'=>(string)($message['interactive']['button_reply']['title']??$message['interactive']['list_reply']['title']??''),
                    default=>'[رسالة '.mb_substr($type,0,20).' غير نصية؛ تنتظر موظف]'
                };
                $body=mb_substr(trim($body),0,4096);
                if($body==='')$body='[محتوى غير متاح؛ ينتظر مراجعة موظف]';
                $accountId=(int)$connection['account_id'];$id=(int)$connection['id'];
                try{
                    db()->beginTransaction();
                    $conv=one('SELECT * FROM wa_conversations WHERE connection_id=? AND contact=?',[$id,$from]);
                    if(!$conv){
                        try{
                            $convId=insert("INSERT INTO wa_conversations
                                (account_id,site_id,connection_id,contact,contact_name,status,generation,
                                 last_customer_at,last_message_at,created_at)
                                VALUES (?,?,?,?,?,'open',0,?,?,?)",[
                                $accountId,(int)$connection['site_id'],$id,$from,$profiles[$from]??null,
                                now(),now(),now()]);
                        }catch(PDOException $e){
                            $conv=one('SELECT * FROM wa_conversations WHERE connection_id=? AND contact=?',[$id,$from]);
                            if(!$conv)throw $e;
                            $convId=(int)$conv['id'];
                        }
                    }else $convId=(int)$conv['id'];
                    if(one('SELECT id FROM wa_messages WHERE connection_id=? AND provider_message_id=?',[$id,$providerId])){
                        $duplicate++;db()->commit();continue;
                    }
                    insert("INSERT INTO wa_messages(account_id,conversation_id,connection_id,provider_message_id,
                        role,body,delivery_status,created_at) VALUES (?,?,?,?,'customer',?,'received',?)",
                        [$accountId,$convId,$id,$providerId,$body,now()]);
                    execute('UPDATE wa_conversations SET last_customer_at=?,last_message_at=?,
                        contact_name=CASE WHEN ?<>\'\' THEN ? ELSE contact_name END,
                        generation=generation+1 WHERE id=? AND account_id=?',
                        [now(),now(),(string)($profiles[$from]??''),(string)($profiles[$from]??''),$convId,$accountId]);
                    wa_audit($accountId,$convId,'whatsapp.inbound',mb_substr($providerId,0,120));
                    $native=((int)($connection['ai_enabled']??0)===1 && (int)($connection['ai_agent_id']??0)>0);
                    if($native&&in_array($type,['text','button','interactive'],true)){
                        $current=one('SELECT generation,ai_pause_until,human_locked_at FROM wa_conversations WHERE id=? AND account_id=?',
                            [$convId,$accountId]);
                        if($current&&wa_ai_allowed($current)){
                            insert("INSERT INTO wa_ai_jobs
                              (account_id,conversation_id,connection_id,source_message_id,generation,status,created_at,updated_at)
                              VALUES (?,?,?,?,?,'pending',?,?)",[
                              $accountId,$convId,$id,$providerId,(int)$current['generation'],now(),now()]);
                        }
                    }
                    db()->commit();$accepted++;$conversationIds[]=$convId;
                }catch(Throwable $e){
                    if(db()->inTransaction())db()->rollBack();
                    if($e instanceof PDOException && str_contains(strtolower($e->getMessage()),'unique')){
                        $duplicate++;continue;
                    }
                    throw $e;
                }
            }
        }
    }
    if($accepted){
        execute('UPDATE wa_connections SET last_webhook_at=?,updated_at=? WHERE id=? AND account_id=?',
            [now(),now(),(int)$connection['id'],(int)$connection['account_id']]);
    }
    return ['accepted'=>$accepted,'duplicates'=>$duplicate,'conversation_ids'=>array_values(array_unique($conversationIds))];
}
function wa_human_takeover(int $accountId,int $conversationId,bool $permanent):array {
    $conversation=wa_conversation($conversationId,$accountId);
    $expiry=gmdate('Y-m-d H:i:s',time()+15*60);
    if($permanent){
        execute('UPDATE wa_conversations SET ai_pause_until=?,human_locked_at=?,generation=generation+1 WHERE id=? AND account_id=?',
            [$expiry,now(),$conversationId,$accountId]);
    }else{
        execute('UPDATE wa_conversations SET ai_pause_until=?,generation=generation+1 WHERE id=? AND account_id=?',
            [$expiry,$conversationId,$accountId]);
    }
    wa_audit($accountId,$conversationId,$permanent?'whatsapp.human.lock':'whatsapp.human.pause',
        $permanent?'until_staff_release':'15_minutes');
    return wa_conversation($conversationId,$accountId);
}
function wa_release_human(int $accountId,int $conversationId):void {
    wa_conversation($conversationId,$accountId);
    execute('UPDATE wa_conversations SET human_locked_at=NULL,generation=generation+1 WHERE id=? AND account_id=?',
        [$conversationId,$accountId]);
    wa_audit($accountId,$conversationId,'whatsapp.human.release','manual_release');
}
function wa_ai_allowed(array $conversation):bool {
    return empty($conversation['human_locked_at']) &&
       (empty($conversation['ai_pause_until']) || strcmp((string)$conversation['ai_pause_until'],now())<=0);
}
function wa_meta_send(array $connection,string $destination,string $body,?callable $transport=null,array $buttons=[]):array {
    if(!in_array($connection['state'],['verified','active'],true))
        throw new RuntimeException('whatsapp_connection_not_verified');
    $destination=wa_e164($destination);
    if(trim($body)===''||mb_strlen($body)>4096)
        throw new InvalidArgumentException('whatsapp_message_invalid');
    $version=wa_graph_version($connection);
    $phoneId=(string)$connection['phone_number_id'];
    if(!preg_match('/^[0-9]{5,100}$/D',$phoneId))
        throw new InvalidArgumentException('whatsapp_sender_invalid');
    $message=['messaging_product'=>'whatsapp','recipient_type'=>'individual',
        'to'=>$destination,'type'=>'text','text'=>['preview_url'=>false,'body'=>$body]];
    if($buttons!==[]){
        if(count($buttons)>3||mb_strlen($body)>1024)throw new InvalidArgumentException('whatsapp_buttons_invalid');
        $replies=[];
        foreach(array_values($buttons) as $n=>$title){
            if(!is_string($title)||trim($title)===''||mb_strlen($title)>20)
                throw new InvalidArgumentException('whatsapp_button_title_invalid');
            $replies[]=['type'=>'reply','reply'=>['id'=>'ot_option_'.($n+1),'title'=>trim($title)]];
        }
        $message['type']='interactive';
        unset($message['text']);
        $message['interactive']=['type'=>'button','body'=>['text'=>$body],
            'action'=>['buttons'=>$replies]];
    }
    $payload=json_encode($message,
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $key=unseal_secret($connection['access_token_enc']??null);
    if(!$key)throw new RuntimeException('whatsapp_token_missing');
    $endpoint='https://graph.facebook.com/'.$version.'/'.$phoneId.'/messages';
    if($transport!==null){
        $response=$transport($endpoint,$key,$payload);
    }else{
        if(!function_exists('curl_init'))throw new RuntimeException('whatsapp_curl_unavailable');
        $ch=curl_init($endpoint);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $response=['status'=>$http,'body'=>$raw];
    }
    $parsed=json_decode((string)($response['body']??''),true);
    $msgId=(string)($parsed['messages'][0]['id']??'');
    if((int)($response['status']??0)!==200||$msgId==='')
        throw new RuntimeException('Meta لم يؤكد قبول الرسالة (HTTP '.(int)($response['status']??0).')');
    return ['provider_message_id'=>$msgId,'status'=>'accepted_not_delivered'];
}
function wa_manual_reply(int $accountId,int $conversationId,string $body,?callable $transport=null,array $buttons=[]):array {
    $conversation=wa_conversation($conversationId,$accountId);
    $connection=wa_connection((int)$conversation['connection_id'],$accountId);
    $body=trim($body);
    if($body===''||mb_strlen($body)>4096)
        throw new InvalidArgumentException('اكتب رسالة حتى 4096 حرف');
    if(empty($conversation['last_customer_at'])||
        strtotime((string)$conversation['last_customer_at'])<time()-86400)
        throw new RuntimeException('خارج نافذة واتساب 24 ساعة؛ يلزم قالب معتمد بدل رسالة نصية.');
    // The human takes ownership BEFORE external send so an in-flight AI
    // response cannot be started after staff has intervened.
    wa_human_takeover($accountId,$conversationId,false);
    try{$sent=wa_meta_send($connection,$conversation['contact'],$body,$transport,$buttons);}
    catch(Throwable $e){
        wa_audit($accountId,$conversationId,'whatsapp.staff.send_failed','Meta rejected or unreachable');
        throw $e;
    }
    $mid=insert("INSERT INTO wa_messages(account_id,conversation_id,connection_id,provider_message_id,
        role,body,delivery_status,created_at) VALUES (?,?,?,?,?,?,?,?)",[
        $accountId,$conversationId,(int)$connection['id'],$sent['provider_message_id'],'staff',
        $body,'accepted',now()]);
    execute('UPDATE wa_conversations SET last_message_at=? WHERE id=? AND account_id=?',
        [now(),$conversationId,$accountId]);
    wa_audit($accountId,$conversationId,'whatsapp.staff.sent','meta_accepted_not_delivered');
    return ['message_id'=>$mid,'status'=>'accepted_not_delivered','paused_minutes'=>15];
}

/** Provider metadata check, no outbound customer message. */
function wa_test_meta_connection(array $connection,?callable $transport=null):array {
    $token=unseal_secret($connection['access_token_enc']??null);
    $phoneId=(string)$connection['phone_number_id'];
    if(!$token||!preg_match('/^[0-9]{5,100}$/D',$phoneId))
        throw new InvalidArgumentException('whatsapp_test_configuration_invalid');
    $version=wa_graph_version($connection);
    $endpoint='https://graph.facebook.com/'.$version.'/'.$phoneId.'?fields=id,display_phone_number';
    if($transport!==null)$response=$transport($endpoint,$token);
    else{
        if(!function_exists('curl_init'))throw new RuntimeException('whatsapp_curl_unavailable');
        $ch=curl_init($endpoint);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>11,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);$response=['status'=>$status,'body'=>$raw];
    }
    $json=json_decode((string)($response['body']??''),true);
    if((int)($response['status']??0)!==200||
       (string)($json['id']??'')!==$phoneId)
        throw new RuntimeException('Meta لم يؤكد ملكية Phone Number ID (HTTP '.(int)($response['status']??0).')');
    $wabaId=trim((string)($connection['waba_id']??''));
    if($wabaId!==''){
        if(!preg_match('/^[0-9]{5,100}$/D',$wabaId))
            throw new InvalidArgumentException('WABA ID غير صالح');
        $wabaEndpoint='https://graph.facebook.com/'.$version.'/'.$wabaId.'/phone_numbers?fields=id&limit=100';
        if($transport!==null)$wabaReply=$transport($wabaEndpoint,$token);
        else{
            $ch=curl_init($wabaEndpoint);
            if(!$ch)throw new RuntimeException('whatsapp_curl_unavailable');
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],
                CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>11,CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
            $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
            curl_close($ch);$wabaReply=['status'=>$status,'body'=>$raw];
        }
        $numbers=json_decode((string)($wabaReply['body']??''),true);
        $matched=false;
        foreach((array)($numbers['data']??[]) as $number){
            if(is_array($number)&&(string)($number['id']??'')===$phoneId){$matched=true;break;}
        }
        if((int)($wabaReply['status']??0)!==200||!$matched)
            throw new RuntimeException('لم يثبت تطابق WABA ID مع Phone Number ID (HTTP '.(int)($wabaReply['status']??0).').');
    }
    execute("UPDATE wa_connections SET state='verified',last_tested_at=?,updated_at=? WHERE id=? AND account_id=?",
        [now(),now(),(int)$connection['id'],(int)$connection['account_id']]);
    wa_audit((int)$connection['account_id'],null,'whatsapp.meta.verified','phone_number_id confirmed, webhook pending');
    return ['verified'=>true,'phone_display'=>(string)($json['display_phone_number']??'')];
}
