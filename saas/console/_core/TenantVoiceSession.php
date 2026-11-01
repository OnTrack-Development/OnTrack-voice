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
        if(in_array($type,['start','say','ask','listen','collect_field','condition','end'],true)&&$line!=='')
            $text[]='['.$type.'] '.$line;
    }
    $instruction=implode("\n",[
        'أنت مساعد صوتي رسمي للشركة، والتزم بمحتوى السيناريو المنشور فقط.',
        'الإيجنت: '.(string)$flow['agent_id'].'، النسخة: '.(int)$flow['revision'],
        (string)$flow['instructions'],
        'قاعدة المعرفة الرسمية: '.mb_substr((string)$flow['knowledge'],0,8000),
        'خطوات السيناريو المنشور: '.implode("\n",array_slice($text,0,75)),
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
    if($apiKey===''||!preg_match('/^[A-Za-z0-9_-]{16,256}$/D',$apiKey))
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
function tenant_voice_session(array $binding):array {
    $definition=tenant_voice_definition($binding);
    $model=trim((string)getenv('ONTRACK_GEMINI_LIVE_MODEL'));
    $key=trim((string)getenv('ONTRACK_GEMINI_API_KEY'));
    if($model===''||$key===''||!preg_match('/^[A-Za-z0-9._-]{5,120}$/D',$model))
        throw new RuntimeException('voice_provider_not_configured');
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
