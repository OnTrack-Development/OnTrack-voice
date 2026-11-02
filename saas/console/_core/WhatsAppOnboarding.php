<?php
declare(strict_types=1);
require_once __DIR__.'/WhatsAppSupport.php';

/** Only the platform admin owns the Meta Tech Provider App and the rollout flags. */
function wa_platform_onboarding():array {
    return one('SELECT * FROM wa_platform_onboarding WHERE id=1')??[
        'id'=>1,'app_id'=>'','app_secret_enc'=>null,'signup_config_id'=>'',
        'coexist_config_id'=>'','graph_version'=>'v26.0',
        'signup_enabled'=>0,'coexist_enabled'=>0,'verify_token_hash'=>null
    ];
}
function wa_onboarding_ready(array $settings,string $mode):bool {
    if(!in_array($mode,['signup','coexist'],true))return false;
    $config=$mode==='signup'?'signup_config_id':'coexist_config_id';
    $flag=$mode==='signup'?'signup_enabled':'coexist_enabled';
    return !empty($settings[$flag]) &&
        preg_match('/^[0-9]{5,100}$/D',(string)($settings['app_id']??''))===1 &&
        preg_match('/^[0-9]{5,100}$/D',(string)($settings[$config]??''))===1 &&
        !empty($settings['app_secret_enc']) &&
        preg_match('/^v[0-9]{1,2}\\.0$/D',(string)($settings['graph_version']??''))===1 &&
        preg_match('/^[a-f0-9]{64}$/D',(string)($settings['verify_token_hash']??''))===1;
}
function wa_onboarding_save(array $values):void {
    $current=wa_platform_onboarding();
    $appId=trim((string)($values['app_id']??''));
    $signup=trim((string)($values['signup_config_id']??''));
    $coexist=trim((string)($values['coexist_config_id']??''));
    $version=trim((string)($values['graph_version']??''));
    $secret=trim((string)($values['app_secret']??''));
    $verify=trim((string)($values['verify_token']??''));
    $enableSignup=($values['signup_enabled']??false)===true;
    $enableCoexist=($values['coexist_enabled']??false)===true;
    foreach([$appId,$signup,$coexist] as $value)
        if($value!==''&&!preg_match('/^[0-9]{5,100}$/D',$value))
            throw new InvalidArgumentException('Meta App ID أو Configuration ID غير صالح');
    if(!preg_match('/^v[0-9]{1,2}\\.0$/D',$version))
        throw new InvalidArgumentException('Graph API Version غير صالح');
    if($secret!==''&&(strlen($secret)<8||strlen($secret)>1000))
        throw new InvalidArgumentException('App Secret غير صالح');
    if($verify!==''&&(strlen($verify)<12||strlen($verify)>200))
        throw new InvalidArgumentException('Webhook Verify Token غير صالح');
    $newSecret=$secret!==''?seal_secret($secret):($current['app_secret_enc']??null);
    $newVerify=$verify!==''?hash('sha256',$verify):($current['verify_token_hash']??null);
    if(($enableSignup||$enableCoexist) &&
        ($appId===''||!$newSecret||!$newVerify))
        throw new InvalidArgumentException('أكمل Meta App ID وApp Secret وWebhook Verify Token قبل التفعيل');
    if($enableSignup&&$signup==='')
        throw new InvalidArgumentException('Configuration ID الخاص بالربط التلقائي مطلوب');
    if($enableCoexist&&$coexist==='')
        throw new InvalidArgumentException('Configuration ID الخاص بـCoexistence مطلوب');
    execute('UPDATE wa_platform_onboarding SET app_id=?,app_secret_enc=?,
        signup_config_id=?,coexist_config_id=?,graph_version=?,
        verify_token_hash=?,signup_enabled=?,coexist_enabled=?,updated_at=? WHERE id=1',[
        $appId,$newSecret,$signup,$coexist,$version,$newVerify,
        $enableSignup?1:0,$enableCoexist?1:0,now()
    ]);
}
/** Fixed Meta Graph host only, HTTPS and bounded JSON. Inject transport for tests. */
function wa_onboarding_graph(array $settings,string $method,string $path,array $params,string $token='',?callable $transport=null):array {
    if(!in_array($method,['GET','POST'],true)||
        !preg_match('~^/[A-Za-z0-9/_-]+$~D',$path))
        throw new InvalidArgumentException('meta_graph_invalid_endpoint');
    $url='https://graph.facebook.com/'.(string)$settings['graph_version'].$path;
    if($transport!==null)$response=$transport($method,$url,$params,$token);
    else{
        if(!function_exists('curl_init'))throw new RuntimeException('meta_curl_unavailable');
        $ch=curl_init($url.($method==='GET'&&$params?'?'.http_build_query($params):''));
        $headers=['Accept: application/json'];
        if($token!=='')$headers[]='Authorization: Bearer '.$token;
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HTTPHEADER=>$headers,
        ]);
        if($method==='POST')curl_setopt_array($ch,[
            CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($params)
        ]);
        $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);$response=['status'=>$status,'body'=>$body];
    }
    $body=(string)($response['body']??'');
    $result=json_decode($body,true,32);
    if((int)($response['status']??0)!==200||!is_array($result)||strlen($body)>120000)
        throw new RuntimeException('Meta لم يؤكد إكمال الربط (HTTP '.(int)($response['status']??0).'). لم يُفعّل الرقم.');
    if(isset($result['error']))throw new RuntimeException('Meta رفض إكمال الربط');
    return $result;
}
/** Meta code is exchanged server-side; neither credentials nor the token reach the UI. */
function wa_onboarding_finish(int $accountId,int $siteId,int $agentId,string $mode,string $code,
 string $wabaId,string $phoneId,?callable $transport=null):array {
    $settings=wa_platform_onboarding();
    if(!wa_onboarding_ready($settings,$mode))
        throw new InvalidArgumentException('طريقة الربط مقفولة حاليًا من إدارة المنصة');
    if(!preg_match('/^[A-Za-z0-9._~-]{10,1600}$/D',$code)||
       !preg_match('/^[0-9]{5,100}$/D',$wabaId)||
       ($phoneId!==''&&!preg_match('/^[0-9]{5,100}$/D',$phoneId)))
        throw new InvalidArgumentException('بيانات موافقة Meta غير صالحة');
    $appSecret=unseal_secret($settings['app_secret_enc']??null);
    if(!$appSecret)throw new RuntimeException('App Secret غير متاح');
    if(!one("SELECT id FROM sites WHERE id=? AND account_id=? AND status='active'",[$siteId,$accountId]))
        throw new InvalidArgumentException('الموقع لا يتبع هذا الحساب');
    if($agentId>0){
        require_once __DIR__.'/PublishedFlow.php';
        if(!published_agent_flow($accountId,$siteId,$agentId))
            throw new InvalidArgumentException('لازم تختار إيجنت منشور لنفس الموقع');
    }
    // The caller's code can authorize assets only on the returned Meta BISU token.
    $exchange=wa_onboarding_graph($settings,'GET','/oauth/access_token',[
        'client_id'=>$settings['app_id'],'client_secret'=>$appSecret,'code'=>$code
    ],'',$transport);
    $token=(string)($exchange['access_token']??'');
    if(strlen($token)<30||strlen($token)>2000)
        throw new RuntimeException('Meta لم يصدر رمز صلاحية صالح لهذا العميل');
    $numbers=wa_onboarding_graph($settings,'GET','/'.$wabaId.'/phone_numbers',
        ['fields'=>'id,display_phone_number','limit'=>100],$token,$transport);
    $owned=[];
    foreach((array)($numbers['data']??[]) as $number)
        if(is_array($number)&&preg_match('/^[0-9]{5,100}$/D',(string)($number['id']??'')))
            $owned[]=(string)$number['id'];
    $owned=array_values(array_unique($owned));
    if($phoneId===''&&count($owned)===1)$phoneId=$owned[0];
    if($phoneId===''||!in_array($phoneId,$owned,true))
        throw new InvalidArgumentException('Meta لم يؤكد الرقم المحدد داخل الـWABA. اختر الرقم الصحيح وأعد الربط.');
    if(one('SELECT id FROM wa_connections WHERE phone_number_id=?',[$phoneId]))
        throw new InvalidArgumentException('الرقم مرتبط بالفعل بحساب في OnTrack؛ لا يمكن نقله تلقائيًا');
    // Subscription must succeed before recording the local connection.
    wa_onboarding_graph($settings,'POST','/'.$wabaId.'/subscribed_apps',[],$token,$transport);
    $verifiedSecret=bin2hex(random_bytes(24));
    $id=wa_create_connection($accountId,$siteId,
        $mode==='coexist'?'WhatsApp Business App':'Meta Embedded Signup',
        $phoneId,$token,$appSecret,$verifiedSecret,'','',$agentId,$wabaId,
        (string)$settings['app_id'],(string)$settings['graph_version']);
    execute('UPDATE wa_connections SET onboarding_method=? WHERE id=? AND account_id=?',
        [$mode==='coexist'?'coexistence':'embedded_signup',$id,$accountId]);
    wa_audit($accountId,null,'whatsapp.embedded.pending',
        'Meta consent exchanged, WABA subscribed, awaiting actual webhook/activation');
    return ['connection_id'=>$id,'status'=>'pending_verification','phone_number_id'=>$phoneId];
}
