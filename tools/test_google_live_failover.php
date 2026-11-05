<?php
declare(strict_types=1);
if(is_dir('/home/appontrack'))throw new RuntimeException('CI_ONLY');
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/TenantVoiceSession.php';
require_once __DIR__.'/../saas/console/_core/GoogleVoiceFailover.php';
seed_console();

function failover_check(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('GOOGLE_LIVE_FAILOVER_TEST_FAIL: '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
function failover_reject(callable $do,string $expected):void {
    try{$do();}catch(Throwable $e){
        failover_check($e->getMessage()===$expected,'reject '.$expected);return;
    }
    throw new RuntimeException('expected '.$expected);
}
$ids=[];$tickets=[];
try {
    $reseller=2;
    $ids[]=$key1=ai_relay_add($reseller,'voice','google_live','Failover primary','gemini-3.8-live','','test-reseller-pool-key-one');
    $ids[]=$key2=ai_relay_add($reseller,'voice','google_live','Failover reserve','gemini-3.8-live','','test-reseller-pool-key-two');
    failover_check(google_voice_pool_owner(5)===$reseller,'customer chooses same reseller pool');
    $binding=['id'=>8701,'account_id'=>5,'site_id'=>6,'agent_id'=>31,'sim_slot'=>0];
    $def=['account_id'=>5,'site_id'=>6,'agent_id'=>31,'revision'=>4,
        'flow_hash'=>str_repeat('a',64),'policy'=>['max_call_minutes'=>10]];
    $mint=static function(int $caller)use($reseller):array {
        failover_check($caller===5,'rotator retains caller account identity');
        return ai_relay_run('voice',static function(array $row,string $secret)use($reseller):array {
            failover_check(str_starts_with($secret,'test-reseller-'),'only own reseller keys exposed in backend');
            return ['token'=>'auth_tokens/'.str_repeat('x',12).$row['id'],
                'model'=>$row['model'],'entry_id'=>(int)$row['id'],'pool_owner_id'=>$reseller];
        },$reseller);
    };
    $initial=$mint(5);
    failover_check($initial['entry_id']===$key1,'primary key selected at call start');
    $ticket=google_voice_failover_register($binding,$def,$initial)['failover_ticket'];
    $tickets[]=$ticket;
    failover_check(strlen($ticket)===48,'opaque ticket issued to paired SIM');
    failover_reject(static fn()=>google_voice_failover_rotate(
        ['id'=>8702,'account_id'=>6,'site_id'=>6,'agent_id'=>31,'sim_slot'=>1],
        $ticket,0,'429',$mint),'voice_failover_ticket_invalid_or_stale');
    failover_reject(static fn()=>google_voice_failover_rotate(
        $binding,$ticket,0,'400',$mint),'voice_failover_invalid_request');
    $switch=google_voice_failover_rotate($binding,$ticket,0,'429',$mint);
    failover_check($switch['failover_generation']===1 && $switch['failover_ticket']===$ticket,
        'rotation increments generation and keeps call ticket');
    failover_check($switch['model']==='gemini-3.8-live' && $switch['revision']===4 &&
        $switch['hash']===$def['flow_hash'] && $switch['agent_id']===31,
        'original published agent revision stays pinned');
    $row1=ai_relay_one($key1,$reseller);
    $cooldown=strtotime((string)$row1['cooldown_until'])-time();
    failover_check($row1['last_status']==='429' && $cooldown>=895 && $cooldown<=901,
        '429 applies 900-second cooldown ONLY to exhausted key');
    $row2=ai_relay_one($key2,$reseller);
    failover_check($row2['last_status']==='ok' && $row2['cooldown_until']===null,
        'healthy second key remains available');
    failover_reject(static fn()=>google_voice_failover_rotate($binding,$ticket,0,'429',$mint),
        'voice_failover_ticket_invalid_or_stale');
    $used=one('SELECT generation,state FROM google_voice_live_calls WHERE ticket=?',[$ticket]);
    failover_check((int)$used['generation']===1 && $used['state']==='active',
        'duplicate old callback cannot issue another session');
    failover_reject(static fn()=>google_voice_failover_rotate($binding,$ticket,1,'503',$mint),
        'voice_failover_no_healthy_keys');
    $used=one('SELECT state FROM google_voice_live_calls WHERE ticket=?',[$ticket]);
    failover_check($used['state']==='exhausted','no healthy key is a terminal, visible state');
    failover_reject(static fn()=>google_voice_failover_rotate($binding,$ticket,1,'timeout',$mint),
        'voice_failover_ticket_invalid_or_stale');
    // A different tenant with a BYOK pool never falls back to reseller/platform.
    $ids[]=$own=ai_relay_add(5,'voice','google_live','Customer own','gemini-3.8-live','','test-customer-own-one');
    failover_check(google_voice_pool_owner(5)===5,'customer BYOK isolates pool');
    echo "GOOGLE_LIVE_MIDCALL_FAILOVER_TEST_PASS\n";
}finally{
    foreach($tickets as $ticket)execute('DELETE FROM google_voice_live_calls WHERE ticket=?',[$ticket]);
    foreach($ids as $id)execute('DELETE FROM ai_relay_entries WHERE id=?',[$id]);
}
