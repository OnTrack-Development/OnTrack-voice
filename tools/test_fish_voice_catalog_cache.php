<?php
declare(strict_types=1);
if(is_dir('/home/appontrack'))throw new RuntimeException('CI_ONLY: never run against live host');
if(PHP_SAPI!=='cli')throw new RuntimeException('CLI_ONLY');
if(!getenv('ONTRACK_CI_NAMESPACE'))putenv('ONTRACK_CI_NAMESPACE=fishcat'.bin2hex(random_bytes(8)));

require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/VoiceCatalog.php';
require_once __DIR__.'/../saas/console/_core/IoPremiumProvider.php';
require_once __DIR__.'/../saas/console/_core/TenantVoiceSession.php';
require_once __DIR__.'/../saas/console/_core/DevicePairing.php';

function ftest_assert(bool $cond,string $msg):void {
    if(!$cond)throw new RuntimeException('FISH_CATALOG_CACHE_FAIL: '.$msg);
}

seed_console();

$cols=driver()==='mysql'
    ?array_column(all('SHOW COLUMNS FROM voices'),'Field')
    :array_column(all('PRAGMA table_info(voices)'),'name');
foreach(['gender','dialect','source_ref_id','sample_path','sample_url','meta_json'] as $col){
    ftest_assert(in_array($col,$cols,true),'missing voices column: '.$col);
}

$aoede=one("SELECT gender,dialect FROM voices WHERE code='google.aoede'");
$charon=one("SELECT gender,dialect FROM voices WHERE code='google.charon'");
ftest_assert(($aoede['gender']??'')==='female','google.aoede inferred female');
ftest_assert(($charon['gender']??'')==='male','google.charon inferred male');

$labPath=private_path().'/voice-lab/owner-1.json';
if(!is_dir(dirname($labPath)))mkdir(dirname($labPath),0700,true);
file_put_contents($labPath,json_encode([
    'keys'=>['fish'=>seal_secret('fixture-fish-secret-token')],
    'settings'=>['voiceId'=>str_repeat('c',32),'fishModel'=>'drama-3','accessMode'=>'all','accessLimit'=>0]
],JSON_THROW_ON_ERROR));

$refLeb='a1a2a3a4b1b2c1c2d1d2e1e2f1f20102';
$refGulf='11223344556677889900aabbccddeeff';
$refCustom='ffeeddccbbaa00998877665544332211';

$createdPublicFiles=[];
try{
    $mockSyncTransport=static function(string $url,string $apiKey)use($refLeb,$refGulf):array {
        ftest_assert(str_contains($url,'api.fish.audio/model'),'uses Fish catalog endpoint');
        ftest_assert($apiKey==='fixture-fish-secret-token','uses sealed platform Fish key');
        return [
            'status'=>200,
            'body'=>json_encode([
                'items'=>[
                    [
                        '_id'=>$refLeb,
                        'title'=>'Nour Lebanese',
                        'description'=>'Natural warm female Beirut voice',
                        'tags'=>['Lebanese','female'],
                        'languages'=>['ar'],
                        'samples'=>[['audio'=>'https://cdn.fish.audio/sample-leb.mp3']],
                    ],
                    [
                        '_id'=>$refGulf,
                        'title'=>'Khalid Gulf Male',
                        'description'=>'Clear Saudi Arabic voice',
                        'tags'=>['Saudi','male'],
                        'languages'=>['ar'],
                        'samples'=>[],
                    ],
                ]
            ],JSON_THROW_ON_ERROR)
        ];
    };

    $sync1=fish_audio_sync_catalog(null,'Arabic',30,$mockSyncTransport,false);
    ftest_assert($sync1['synced']===2&&$sync1['inserted']===2,'initial sync inserted 2 voices');
    $lebRow=one('SELECT * FROM voices WHERE code=?',['fish.'.$refLeb]);
    $gulfRow=one('SELECT * FROM voices WHERE code=?',['fish.'.$refGulf]);
    ftest_assert(is_array($lebRow)&&is_array($gulfRow),'synced rows stored');
    ftest_assert($lebRow['gender']==='female'&&$lebRow['dialect']==='لبناني','Lebanese female metadata inferred');
    ftest_assert($gulfRow['gender']==='male'&&$gulfRow['dialect']==='خليجي','Gulf male metadata inferred');
    ftest_assert((int)$lebRow['ready']===0,'markReady=false leaves voices unready initially');

    $sync2=fish_audio_sync_catalog(null,'Arabic',30,$mockSyncTransport,true);
    ftest_assert($sync2['updated']===2,'second sync updates without duplicating');
    $lebRow=one('SELECT * FROM voices WHERE code=?',['fish.'.$refLeb]);
    ftest_assert((int)$lebRow['ready']===1,'markReady=true activates voices');

    $rejectedInvalid=false;
    try{fish_audio_add_custom('Bad','not-32-hex','female','مصري',true);}
    catch(InvalidArgumentException){$rejectedInvalid=true;}
    ftest_assert($rejectedInvalid,'invalid 32-hex ref rejected');

    $customId=fish_audio_add_custom('Custom Egyptian Female',$refCustom,'female','مصري',true);
    ftest_assert($customId>0,'custom Fish voice added');

    // 1. Download-based sample (0 TTS tokens) + cache hit + private self-heal
    $mp3Bytes="ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x00",64);
    $dlCalls=0;
    $dlTransport=static function(string $mode,string $url)use(&$dlCalls,$mp3Bytes):array {
        $dlCalls++;
        ftest_assert($mode==='download','uses remote sample URL before TTS');
        return ['status'=>200,'body'=>$mp3Bytes];
    };
    $s1=voice_sample_cache_ensure((int)$lebRow['id'],$dlTransport);
    ftest_assert($s1['cached']===false&&$s1['tokens_used']===0&&$dlCalls===1,'remote sample saved cleanly');
    $pubRel=(string)one('SELECT sample_path FROM voices WHERE id=?',[(int)$lebRow['id']])['sample_path'];
    $pubFile=dirname(__DIR__).'/saas/console/'.$pubRel;
    $createdPublicFiles[]=$pubFile;
    ftest_assert(is_file($pubFile),'public sample file written');

    $spyCalls=0;
    $spyTransport=static function()use(&$spyCalls):array {$spyCalls++;return ['status'=>500,'body'=>''];};
    $s2=voice_sample_cache_ensure((int)$lebRow['id'],$spyTransport);
    ftest_assert($s2['cached']===true&&$s2['tokens_used']===0&&$spyCalls===0,'cache hit spends 0 tokens');

    @unlink($pubFile);
    ftest_assert(!is_file($pubFile),'simulated public asset wipe before redeploy');
    $s3=voice_sample_cache_ensure((int)$lebRow['id'],$spyTransport);
    ftest_assert($s3['cached']===true&&$s3['tokens_used']===0&&$spyCalls===0&&is_file($pubFile),'self-healed public file from private mirror with 0 tokens');

    // 2. TTS fallback sample (1 TTS token once) + zero-token repeat
    $ttsCalls=0;
    $ttsTransport=static function(string $mode,string $url,?string $payload,array $opts)use(&$ttsCalls,$mp3Bytes):array {
        $ttsCalls++;
        ftest_assert($mode==='fish_tts','falls back to one-time fish_tts when sample_url missing');
        return ['status'=>200,'body'=>$mp3Bytes];
    };
    $g1=voice_sample_cache_ensure((int)$gulfRow['id'],$ttsTransport);
    ftest_assert($g1['cached']===false&&$g1['tokens_used']===1&&$ttsCalls===1,'one-time TTS generated');
    $gRel=(string)one('SELECT sample_path FROM voices WHERE id=?',[(int)$gulfRow['id']])['sample_path'];
    $createdPublicFiles[]=dirname(__DIR__).'/saas/console/'.$gRel;
    $g2=voice_sample_cache_ensure((int)$gulfRow['id'],$spyTransport);
    ftest_assert($g2['cached']===true&&$g2['tokens_used']===0&&$spyCalls===0,'repeated preview uses local cache with 0 tokens');

    // 3. Multi-voice fish.* entitlement isolation & persona directives
    ftest_assert(tenant_voice_is_fish('fish.'.$refCustom),'fish.* recognized as Fish voice');
    ftest_assert(tenant_voice_resolve_fish(1,'fish.'.$refCustom)===$refCustom,'platform owner resolves any ready fish.* without grant');
    ftest_assert(tenant_voice_resolve_fish(4,'fish.'.$refCustom)===null,'customer denied ungranted fish.* voice');
    insert('INSERT INTO voice_grants(account_id,voice_id) VALUES (?,?)',[4,$customId]);
    ftest_assert(tenant_voice_resolve_fish(4,'fish.'.$refCustom)===$refCustom,'customer resolves explicitly granted fish.* voice');

    $prompt=tenant_dynamic_instruction('ليلى',['instructions'=>'','knowledge'=>''],answer_policy_defaults(),'','');
    ftest_assert(str_contains($prompt,'بدّل أسلوبك فورًا إلى التأنيث الكامل')&&str_contains($prompt,'ذاكرة المكالمة النشطة'),
        'gender-adaptive female lock and active call memory injected into tenant instruction');

    echo "FISH_VOICE_CATALOG_CACHE_PASS\n";
}finally{
    foreach($createdPublicFiles as $f){if(is_file($f))@unlink($f);}
    $privDir=voice_sample_private_dir();
    foreach(glob($privDir.'/v*.*')?:[] as $pf){@unlink($pf);}
}

