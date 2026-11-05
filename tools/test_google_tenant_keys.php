<?php
declare(strict_types=1);
if(is_dir('/home/appontrack'))throw new RuntimeException('CI_ONLY');
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/TenantVoiceSession.php';
require_once __DIR__.'/../saas/console/_core/GoogleTenantKeys.php';
seed_console();
function google_key_assert(bool $ok,string $message):void {
    if(!$ok)throw new RuntimeException('GOOGLE_KEY_ISOLATION_FAIL '.$message);
    echo "PASS ".$message.PHP_EOL;
}
$platform=ai_relay_platform();
$ids=[];
try{
    google_voice_key_schema();
    google_key_assert(google_voice_key_limit(5)===1,'customer starter plan allows one key');
    $ids[]=$platformKey=ai_relay_add($platform,'voice','google_live','Platform Test Pool','gemini-3.8-live','','platform-demo-key');
    google_key_assert(google_voice_pool_owner(4)===$platform,'direct customer inherits platform pool');
    $ids[]=$r1=ai_relay_add(2,'voice','google_live','Reseller Pool 1','gemini-3.8-live','','reseller-demo-key-1');
    $ids[]=$r2=ai_relay_add(2,'voice','google_live','Reseller Pool 2','gemini-3.8-live','','reseller-demo-key-2');
    google_key_assert(google_voice_pool_owner(5)===2,'reseller customer inherits reseller not platform');
    google_key_assert(ai_relay_one($r1,5)===null&&ai_relay_one($platformKey,5)===null,
        'client cannot read reseller or platform encrypted keys');
    $ids[]=$own=ai_relay_add(5,'voice','google_live','Customer Own Pool','gemini-3.8-live','','customer-demo-key');
    google_key_assert(google_voice_pool_owner(5)===5,'BYOK client becomes own-only');
    google_key_assert(ai_relay_run('voice',static fn($r,$key)=>$key,5)==='customer-demo-key',
        'BYOK never selects reseller or platform key');
    $blocked=false;
    try{ai_relay_add(5,'voice','google_live','Customer Second','gemini-3.8-live','','other-demo-key');}
    catch(RuntimeException){$blocked=true;}
    google_key_assert($blocked,'plan key ceiling prevents excess customer keys');
    $blocked=false;
    try{ai_relay_edit($r1,'Bad Edit','gemini-3.8-live','','',5);}
    catch(InvalidArgumentException){$blocked=true;}
    google_key_assert($blocked,'client cannot alter reseller key');
    $blocked=false;
    try{google_voice_key_set_customer_limit(3,5,1);}
    catch(InvalidArgumentException){$blocked=true;}
    google_key_assert($blocked,'foreign reseller cannot override customer key cap');
    $old=one('SELECT google_key_limit FROM plans WHERE id=4')['google_key_limit'];
    execute('UPDATE plans SET google_key_limit=3 WHERE id=4');
    try{
        google_voice_key_set_customer_limit(2,5,2);
        google_key_assert(google_voice_key_limit(5)===2,'reseller override below plan applied');
        $ids[]=$second=ai_relay_add(5,'voice','google_live','Customer Second','gemini-3.8-live','','customer-demo-key-2');
        $blocked=false;
        try{google_voice_key_set_customer_limit(2,5,4);}
        catch(InvalidArgumentException){$blocked=true;}
        google_key_assert($blocked&&google_voice_key_limit(5)===2,
            'invalid override cannot erase prior key allowance');
        google_voice_key_set_customer_limit(2,5,0);
        $blocked=false;
        try{ai_relay_run('voice',static fn($r,$key)=>$key,5);}
        catch(RuntimeException){$blocked=true;}
        google_key_assert($blocked,'plan suspension blocks BYOK use without fallback');
    }finally{execute('UPDATE plans SET google_key_limit=? WHERE id=4',[$old]);}
    google_key_assert(google_voice_pool_owner(6)===2,'sibling customer without own key uses reseller');
    google_key_assert(ai_relay_run('voice',static fn($row,$key)=>$key,2)==='reseller-demo-key-1',
        'reseller pool may contain multiple keys');
    echo "GOOGLE_VOICE_TENANT_KEYS_ISOLATION_PASS\n";
}finally{
    foreach($ids as $id)execute('DELETE FROM ai_relay_entries WHERE id=?',[$id]);
    execute('DELETE FROM google_voice_key_overrides WHERE account_id=?',[5]);
}
