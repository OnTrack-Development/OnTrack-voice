<?php
declare(strict_types=1);

/**
 * IO Premium uses the encrypted Fish Audio credentials from the existing
 * owner Voice Lab. The secret never reaches HTML, JS, logs, database or APK.
 * Only platform owners/admins may change these shared provider credentials.
 */
require_once __DIR__.'/../io/voice-lab/security.php';

function io_premium_config_public():array {
    $data=voice_lab_read(1);
    $settings=$data['settings']??[];
    $sealed=$data['keys']['fish']??null;
    $hasKey=is_string($sealed) && (unseal_secret($sealed)??'')!=='';
    $voiceId=strtolower((string)($settings['voiceId']??''));
    $model=(string)($settings['fishModel']??'drama-3');
    $voice=one("SELECT id,ready,status FROM voices WHERE code='io.premium'");
    return [
        'key_saved'=>$hasKey,
        'voice_id'=>$voiceId,
        'model'=>$model,
        'configured'=>$hasKey && preg_match('/^[a-f0-9]{32}$/D',$voiceId)===1,
        'ready'=>(int)($voice['ready']??0)===1,
        'visible'=>(string)($voice['status']??'')==='active',
    ];
}

/** Probe a minimal real Fish PCM stream without storing, playing or logging it. */
function io_premium_test_fish(string $key,string $voiceId,string $model):array {
    if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL غير متاح على السيرفر');
    $body=json_encode([
        'text'=>'أهلاً بيك.',
        'reference_id'=>$voiceId,
        'format'=>'pcm',
        'sample_rate'=>44100,
        'streaming'=>true,
        'latency'=>'low',
    ],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $bytes=0;$firstMs=null;$started=microtime(true);
    $curl=curl_init('https://api.fish.audio/v1/tts');
    if($curl===false)throw new RuntimeException('تعذر تهيئة فحص Fish Audio');
    try {
        curl_setopt_array($curl,[
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$body,
            CURLOPT_HTTPHEADER=>[
                'Authorization: Bearer '.$key,'Content-Type: application/json',
                'Accept: application/octet-stream','model: '.$model,
            ],
            CURLOPT_CONNECTTIMEOUT=>6,
            CURLOPT_TIMEOUT=>18,
            CURLOPT_RETURNTRANSFER=>false,
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_WRITEFUNCTION=>static function($resource,string $chunk)use(&$bytes,&$firstMs,$started):int {
                if($firstMs===null)$firstMs=(int)round((microtime(true)-$started)*1000);
                $length=strlen($chunk);
                $bytes+=$length;
                return $length;
            },
        ]);
        $ok=curl_exec($curl);
        $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
        if($ok!==true||$status!==200||$bytes<128)
            throw new RuntimeException('اختبار Fish Audio لم ينجح (HTTP '.$status.')؛ الإعدادات القديمة محفوظة');
    } finally {
        curl_close($curl);
    }
    return ['first_byte_ms'=>$firstMs,'received_bytes'=>$bytes];
}

/**
 * Do not persist or mark usable until Fish answers with real streaming PCM.
 * Preserve existing Google Live credentials, other Voice Lab settings, and
 * a manually hidden catalogue entry. Grant to active platform accounts only.
 */
function io_premium_save_settings(string $keyInput,string $voiceId,string $model):array {
    $voiceId=strtolower(trim($voiceId));
    if(preg_match('/^[a-f0-9]{32}$/D',$voiceId)!==1)
        throw new InvalidArgumentException('Fish Voice ID لازم يكون 32 خانة hexadecimal');
    if(!in_array($model,['drama-3','s2.1-pro-free','s2.1-pro'],true))
        throw new InvalidArgumentException('موديل Fish غير مدعوم');
    $keyInput=trim($keyInput);
    if(strlen($keyInput)>2048||preg_match('/[\x00-\x1F\x7F]/',$keyInput))
        throw new InvalidArgumentException('مفتاح Fish يحتوي رموز غير صالحة');
    $data=voice_lab_read(1);
    $stored=$data['keys']['fish']??null;
    $existing=is_string($stored)?(unseal_secret($stored)??''):'';
    $key=$keyInput!==''?$keyInput:$existing;
    if($key==='')throw new InvalidArgumentException('أدخل Fish Audio API Key أولاً');
    // External provider request happens BEFORE changing any saved setting.
    $result=io_premium_test_fish($key,$voiceId,$model);
    if($keyInput!=='')$data['keys']['fish']=seal_secret($keyInput);
    $data['settings']['voiceId']=$voiceId;
    $data['settings']['fishModel']=$model;
    voice_lab_write(1,$data);
    $voice=one("SELECT id,status FROM voices WHERE code='io.premium'");
    if(!$voice)throw new RuntimeException('IO Premium مش موجود في كتالوج الأصوات');
    execute("UPDATE voices SET ready=1 WHERE id=?",[(int)$voice['id']]);
    foreach(all("SELECT id FROM accounts WHERE kind='platform' AND status='active'") as $platform){
        $accountId=(int)$platform['id'];
        if(!one('SELECT id FROM voice_grants WHERE account_id=? AND voice_id=?',[$accountId,(int)$voice['id']]))
            insert('INSERT INTO voice_grants(account_id,voice_id) VALUES (?,?)',[$accountId,(int)$voice['id']]);
    }
    return $result;
}
