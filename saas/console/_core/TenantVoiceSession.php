<?php
declare(strict_types=1);
require_once __DIR__.'/PublishedFlow.php';
/**
 * Tenant-native Gemini Live session: no legacy global pilot bearer or pilot policy.
 * User must be paired to one SIM, and the Flow & answer policy must be published.
 * No ephemeral token is issued if the launch provider is unavailable.
 */
function tenant_voice_definition(array $binding):array {
    $accountId=(int)$binding['account_id'];$siteId=(int)$binding['site_id'];$agentId=(int)$binding['agent_id'];
    $flow=published_agent_flow($accountId,$siteId,$agentId);
    if(!$flow)throw new InvalidArgumentException('agent_flow_not_published');
    $graph=json_decode((string)$flow['flow_json'],true,32,JSON_THROW_ON_ERROR);
    flow_preview_compile($graph);
    $policy=answer_policy_normalize((array)(json_decode((string)($flow['answer_policy_json']??''),true)??[]));
    $text=[];
    foreach($graph['nodes'] as $node){
        $type=(string)($node['type']??'');$line=trim((string)($node['text']??''));
        if(in_array($type,['start','say','ask','listen','collect_field','condition','instruction','end'],true)&&$line!=='')
            $text[]='['.$type.'] '.$line;
    }
    $instruction=implode("\n",[
        'أنت مساعد صوتي رسمي للشركة، والتزم بمحتوى السيناريو المنشور فقط.',
        'الإيجنت: '.(string)$flow['agent_id'].'، النسخة: '.(int)$flow['revision'],
        (string)$flow['instructions'],
        'قاعدة المعرفة الرسمية: '.mb_substr((string)$flow['knowledge'],0,8000),
        'المحتوى الإرشادي والمسارات المنشورة (لا تقرأ عقد instruction بصوت مسموع): '.implode("\n",array_slice($text,0,75)),
        'السلوك الأساسي: هذا مساعد استشاري حواري وليس استمارة أسئلة. ابدأ بمعرفة حاجة العميل، ثم أجب مباشرة من قاعدة المعرفة. لا تجمع بيانات شخصية إلا لو العميل وافق على متابعة أو حجز.',
        'أي سؤال جانبي أو اعتراض يُجاب عنه من المعرفة الموثقة ثم ارجع للمسار أو لسؤال العميل الأصلي. لا تقفز تلقائيا من سؤال إلى الذي يليه، ولا تُنهي المحادثة بمجرد إنهاء أسئلة القالب.',
        'القالب يحدد أهدافاً ومسارات قرار، وليس نصاً إلزامياً بالحرف. شرح المنتج والمزايا والفروق أهم من جمع الاسم والرقم.',
        'إذا كانت قاعدة المعرفة لا تحتوي الإجابة فقل إن المعلومة غير متاحة واقترح مسؤولاً بشرياً. لا تختلق سعراً أو موعداً أو عرضاً أو خصماً.',
        'اعترف بإجابة العميل السابقة واستخدمها؛ اسأل عن تفاصيل أساسية واحدة وقت الحاجة، وليس كل حقول القالب.',
        'لا تؤكد حجزًا أو عملية دفع أو تحويل CRM أو نتيجة طبية أو عائد استثمار دون تكامل فعلي معتمد.',
        'اسأل سؤالًا واحدًا كل مرة، استمع للعميل، ولا تعيد السؤال إن تمت الإجابة عنه.',
        'إن طلب العميل عدم التواصل مرة ثانية فتوقف عن التسويق واحترم طلبه.',
        'اسأل عن موافقة المتصل على الحديث، وتعرّف بنفسك كإيجنت آلي.',
        'في حالة الحاجة لموظف بشري لا تدّعِ إتمام التحويل إلا بعد نجاح النظام.'
    ]);
    return ['account_id'=>$accountId,'site_id'=>$siteId,'agent_id'=>$agentId,
        'revision'=>(int)$flow['revision'],'flow_hash'=>(string)$flow['flow_hash'],
        'policy'=>$policy,'instruction'=>$instruction,'voice'=>(string)$flow['voice']];
}
/** Server-side only, fixed Google hostname and TLS-verifying POST. No master API key in response. */
function tenant_voice_ephemeral_token(string $apiKey,int $minutes=10):string {
    if($apiKey===''||!preg_match('/\A[A-Za-z0-9._~-]{4,256}\z/D',$apiKey))
        throw new RuntimeException('voice_provider_not_configured');
    if(!function_exists('curl_init'))throw new RuntimeException('voice_curl_unavailable');
    $expiry=gmdate('Y-m-d\TH:i:s\Z',time()+min(30,max(1,$minutes))*60);
    $start=gmdate('Y-m-d\TH:i:s\Z',time()+60);
    $json=json_encode(['uses'=>1,'expireTime'=>$expiry,'newSessionExpireTime'=>$start],JSON_THROW_ON_ERROR);
    $curl=curl_init('https://generativelanguage.googleapis.com/v1beta/auth_tokens');
    if($curl===false)throw new RuntimeException('voice_provider_unavailable');
    curl_setopt_array($curl,[
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$json,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-goog-api-key: '.$apiKey],
        CURLOPT_TIMEOUT=>8,CURLOPT_CONNECTTIMEOUT=>4,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2
    ]);
    try{
        $response=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    }finally{curl_close($curl);}
    if(!is_string($response)||strlen($response)>16000||$status!==200)
        throw new RuntimeException('voice_provider_token_failed');
    $data=json_decode($response,true);
    $token=(string)($data['name']??'');
    if(!preg_match('~^auth_tokens/[A-Za-z0-9_-]{12,512}$~D',$token))
        throw new RuntimeException('voice_provider_token_invalid');
    return $token;
}
/** Resolve the platform's encrypted Live provider, with no legacy pilot fallback. */
function tenant_voice_provider():array {
    $model=trim((string)getenv('ONTRACK_GEMINI_LIVE_MODEL'));
    $key=trim((string)getenv('ONTRACK_GEMINI_API_KEY'));
    if($key===''||$model===''){
        // Platform configuration only; never use the legacy phone pilot key.
        $platform=one("SELECT id FROM accounts WHERE kind='platform' AND status='active' ORDER BY id LIMIT 1");
        $profile=$platform?one("SELECT name,secret_enc FROM integrations WHERE account_id=? AND provider='google_live' AND status='active' ORDER BY id DESC LIMIT 1",[(int)$platform['id']]):null;
        if($profile){
            $model=trim((string)$profile['name']);
            $key=(string)(unseal_secret($profile['secret_enc']??null)??'');
        }
    }
    if($model===''||$key===''||!preg_match('/^[A-Za-z0-9._-]{5,120}$/D',$model))
        throw new RuntimeException('voice_provider_not_configured');
    return ['model'=>$model,'key'=>$key];
}
/** Validate Google Live access for an authenticated paired SIM, without placing
 * a call and without making production telephony active or returning a bearer. */
function tenant_voice_preflight(array $binding):array {
    $definition=tenant_voice_definition($binding);
    $provider=tenant_voice_provider();
    $ephemeral=tenant_voice_ephemeral_token($provider['key'],1);
    if(!str_starts_with($ephemeral,'auth_tokens/'))throw new RuntimeException('voice_provider_token_invalid');
    return ['ready'=>true,'revision'=>$definition['revision'],
        'agent_id'=>$definition['agent_id'],'site_id'=>$definition['site_id'],
        'sim_slot'=>(int)$binding['sim_slot'],'model'=>$provider['model']];
}
function tenant_voice_session(array $binding):array {
    $definition=tenant_voice_definition($binding);
    $provider=tenant_voice_provider();
    $model=$provider['model'];$key=$provider['key'];
    // Keep the endpoint disabled until the actual per-SIM call + PCM E2E is certified.
    if(getenv('ONTRACK_TENANT_VOICE_ENABLED')!=='1')
        throw new RuntimeException('tenant_voice_not_launched');
    $token=tenant_voice_ephemeral_token($key,$definition['policy']['max_call_minutes']);
    return ['token'=>$token,'model'=>$model,'system_instruction'=>$definition['instruction'],
        'voice'=>$definition['voice'],'answer_policy'=>$definition['policy'],
        'revision'=>$definition['revision'],'hash'=>$definition['flow_hash'],
        'agent_id'=>$definition['agent_id'],'site_id'=>$definition['site_id'],
        'sim_slot'=>(int)$binding['sim_slot'],'transport'=>'tenant_gemini_live_preview'];
}
