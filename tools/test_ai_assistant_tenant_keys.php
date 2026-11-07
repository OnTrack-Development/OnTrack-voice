<?php
declare(strict_types=1);
if(is_dir('/home/appontrack'))throw new RuntimeException('CI_ONLY');
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/TenantVoiceSession.php';
require_once __DIR__.'/../saas/console/_core/AiRelay.php';
seed_console();
function assistant_check(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('ASSISTANT_BYOK_FAIL '.$label);
}
$platform=ai_relay_platform();
ai_relay_assistant_limits_schema();
$plan=one("SELECT p.id,p.assistant_key_limit FROM subscriptions s JOIN plans p ON p.id=s.plan_id
    WHERE s.account_id=5 AND s.status='active' AND s.starts_at<=? AND s.ends_at>?
    ORDER BY s.id DESC LIMIT 1",[now(),now()]);
assistant_check(is_array($plan),'customer test plan present');
$keys=[];
try {
    assistant_check(ai_relay_assistant_pool_owner(5)===$platform,
        'unconfigured customer inherits only active platform assistant pool');
    $voiceCount=count(ai_relay_rows('voice',false,5));
    $blocked=false;
    try{ai_relay_add(5,'assistant','google_text','Client Text','gemini-3.8-flash','','demo-key');}
    catch(RuntimeException $e){$blocked=$e->getMessage()==='relay_plan_key_limit_reached';}
    assistant_check($blocked,'assistant BYOK disabled by plan default');
    $keys[]=$resellerA=ai_relay_add(2,'assistant','omnira_a','Reseller A','model-a',
        'https://omnira.example.net/v1/chat/completions','demo-reseller-A');
    $keys[]=$resellerB=ai_relay_add(2,'assistant','nvidia','Reseller B','model-b',
        'https://inference.example.net/v1/chat/completions','demo-reseller-B');
    assistant_check(ai_relay_assistant_pool_owner(5)===2,
        'reseller assistant pool inherited without revealing credentials');
    assistant_check(ai_relay_assistant_pool_owner(6)===2,'sibling inherits same reseller pool');
    assistant_check(ai_relay_one($resellerA,5)===null,
        'customer cannot inspect reseller encrypted assistant provider');
    assistant_check(ai_relay_model(5)==='model-a',
        'actual agent assistant model selection uses inherited tenant pool');
    execute('UPDATE plans SET assistant_key_limit=2 WHERE id=?',[(int)$plan['id']]);
    assistant_check(ai_relay_assistant_key_limit(5)===2,'explicit plan entitlement grants two BYOK text keys');
    $keys[]=$ownA=ai_relay_add(5,'assistant','google_text','Own Google Text','gemini-3.8-flash','','demo-customer-1');
    $keys[]=$ownB=ai_relay_add(5,'assistant','omnira_b','Own Omnira','model-2',
        'https://custom.example.net/v1/chat/completions','demo-customer-2');
    assistant_check(ai_relay_assistant_pool_owner(5)===5,'own assistant pool never mixes reseller credentials');
    assistant_check(ai_relay_run('assistant',static fn($row,$key)=>$key,5)==='demo-customer-1',
        'assistant relay selects own encrypted key only');
    assistant_check(ai_relay_model(5)==='gemini-3.8-flash',
        'assistant model scoped to customer BYOK');
    assistant_check(count(ai_relay_rows('voice',false,5))===$voiceCount,
        'assistant keys never mutate Google Live phone pool');
    $blocked=false;
    try{ai_relay_add(5,'assistant','nvidia','Extra Customer','model-n',
        'https://new.example.net/v1/chat/completions','demo-customer-3');}
    catch(RuntimeException $e){$blocked=$e->getMessage()==='relay_plan_key_limit_reached';}
    assistant_check($blocked,'customer third key rejected by separate assistant plan limit');
    $blocked=false;
    try{ai_relay_add(5,'voice','nvidia','Invalid Voice','model-n',
        'https://new.example.net/v1/chat/completions','demo-not-voice');}
    catch(InvalidArgumentException){$blocked=true;}
    assistant_check($blocked,'OpenAI text providers are never falsely offered as Gemini Live voice');
    $blocked=false;
    try{ai_relay_edit($resellerA,'Hacked','model-a',
        'https://other.example.net/v1/chat/completions','replacement',5);}
    catch(InvalidArgumentException){$blocked=true;}
    assistant_check($blocked,'cross-tenant assistant credential edit blocked');
    $ownPublic=ai_relay_rows('assistant',false,5);
    assistant_check(!array_key_exists('secret_enc',$ownPublic[0])&&
        !str_contains(json_encode($ownPublic),'demo-customer-1'),
        'assistant API list never sends private secrets to page or APK');
    execute('UPDATE plans SET assistant_key_limit=0 WHERE id=?',[(int)$plan['id']]);
    $blocked=false;
    try{ai_relay_assistant_pool_owner(5);}
    catch(RuntimeException $e){$blocked=$e->getMessage()==='assistant_byok_plan_disabled';}
    assistant_check($blocked,'downgraded BYOK cannot silently fall back to reseller');
    $blocked=false;
    try{ai_relay_run('assistant',static fn($row,$key)=>$key,5);}
    catch(RuntimeException $e){$blocked=true;}
    assistant_check($blocked,'suspended assistant BYOK plan prevents secret use');
    echo "SITE15_ASSISTANT_TENANT_BYOK_AND_PROVIDER_SCOPES_PASS\n";
}finally{
    execute('UPDATE plans SET assistant_key_limit=? WHERE id=?',
        [(int)$plan['assistant_key_limit'],(int)$plan['id']]);
    foreach($keys as $keyId)execute('DELETE FROM ai_relay_entries WHERE id=?',[$keyId]);
}
