<?php
declare(strict_types=1);

/**
 * One encrypted, platform-owned IO Premium Fish Audio profile.
 * This is the same private profile used by /io/voice-lab and device/v1/tts.php.
 * An Agent selects a voice code; it NEVER receives an API key.
 */
function io_premium_admin_profile(): array {
    $path=private_path().'/voice-lab/owner-1.json';
    if(is_link($path))throw new RuntimeException('مسار إعدادات IO Premium غير آمن');
    if(!is_file($path))return ['keys'=>[],'settings'=>[]];
    $data=json_decode((string)file_get_contents($path),true);
    if(!is_array($data)||!is_array($data['keys']??null)||!is_array($data['settings']??null))
        throw new RuntimeException('تعذر قراءة إعدادات الصوت المحفوظة');
    return $data;
}
function io_premium_admin_state(): array {
    $profile=io_premium_admin_profile();
    $sealed=$profile['keys']['fish']??null;
    $keyReady=is_string($sealed)&&($key=unseal_secret($sealed))!==null&&$key!=='';
    $voiceId=(string)($profile['settings']['voiceId']??'');
    $model=(string)($profile['settings']['fishModel']??'drama-3');
    $configured=$keyReady&&preg_match('/^[a-f0-9]{32}$/D',$voiceId)===1
        && in_array($model,['drama-3','s2.1-pro-free','s2.1-pro'],true);
    return ['key_saved'=>$keyReady,'voice_id'=>$voiceId,'fish_model'=>$model,'configured'=>$configured];
}
/**
 * Platform-wide availability is independent of users' BYOK keys and the old
 * manual voice_grants table. In limited mode, the oldest N active reseller /
 * customer accounts receive the slots deterministically. The platform owner
 * is excluded from that cap; disabled always denies everyone.
 */
function io_premium_access_policy():array {
    $settings=io_premium_admin_profile()['settings'];
    $mode=(string)($settings['accessMode']??'all');
    if(!in_array($mode,['all','limited','off'],true))$mode='off';
    $limit=max(0,min(100000,(int)($settings['accessLimit']??0)));
    return ['mode'=>$mode,'limit'=>$limit];
}
function io_premium_access_allowed(int $accountId):bool {
    if($accountId<1)return false;
    $acct=account($accountId);
    if(!$acct||($acct['status']??'')!=='active')return false;
    $p=io_premium_access_policy();
    if($p['mode']==='off')return false;
    $voice=one("SELECT ready,status FROM voices WHERE code='io.premium'");
    if(!$voice||(int)$voice['ready']!==1||$voice['status']!=='active'
        ||!io_premium_admin_state()['configured'])return false;
    if($acct['kind']==='platform')return true;
    if($p['mode']==='all')return true;
    if($p['limit']<1)return false;
    $slots=all("SELECT id FROM accounts WHERE kind!='platform' AND status='active'
        ORDER BY id ASC LIMIT ".$p['limit']);
    foreach($slots as $slot)if((int)$slot['id']===$accountId)return true;
    return false;
}
function io_premium_admin_save_access(string $mode,int $limit):void {
    if(!in_array($mode,['all','limited','off'],true)||$limit<0||$limit>100000||
        ($mode==='limited'&&$limit<1))
        throw new InvalidArgumentException('سياسة توزيع IO Premium غير صالحة');
    $profile=io_premium_admin_profile();
    $profile['settings']['accessMode']=$mode;
    $profile['settings']['accessLimit']=$mode==='limited'?$limit:0;
    $path=private_path().'/voice-lab/owner-1.json';
    $folder=dirname($path);
    if(is_link($folder)||is_link($path))throw new RuntimeException('مسار حفظ غير آمن');
    if(!is_dir($folder)&&!mkdir($folder,0700,true)&&!is_dir($folder))
        throw new RuntimeException('لا يمكن تجهيز المجلد الآمن');
    $out=json_encode($profile,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $temp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';
    try{
        if(file_put_contents($temp,$out,LOCK_EX)!==strlen($out))
            throw new RuntimeException('تعذر حفظ توزيع IO Premium');
        @chmod($temp,0600);
        if(!rename($temp,$path))throw new RuntimeException('تعذر اعتماد توزيع IO Premium');
        @chmod($path,0600);
    }finally{if(is_file($temp))@unlink($temp);}
}
function io_premium_admin_verify_fish(string $key,string $voiceId,string $model):void {
    if(!function_exists('curl_init'))throw new RuntimeException('cURL غير متاح للتحقق من Fish Audio');
    $request=json_encode([
        'text'=>'أهلاً بيك.','reference_id'=>$voiceId,'format'=>'pcm',
        'sample_rate'=>48000,'streaming'=>true,'latency'=>'low'
    ],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $handle=curl_init('https://api.fish.audio/v1/tts');
    $bytes=0;
    curl_setopt_array($handle,[
        CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$request,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json',
            'Accept: application/octet-stream','model: '.$model],
        CURLOPT_CONNECTTIMEOUT=>7,CURLOPT_TIMEOUT=>25,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_RETURNTRANSFER=>false,
        CURLOPT_WRITEFUNCTION=>static function($h,string $chunk)use(&$bytes):int{
            $bytes+=strlen($chunk);
            return strlen($chunk);
        }
    ]);
    $result=curl_exec($handle);
    $status=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE);
    curl_close($handle);
    if($result!==true||$status!==200||$bytes<128)
        throw new RuntimeException('تعذر التأكد من مفتاح Fish Audio والصوت المختار. لم يتم حفظ أي تغيير.');
}
function io_premium_admin_save(string $incomingKey,string $voiceId,string $model):void {
    if(strlen($incomingKey)>2048||preg_match('/[\x00-\x1f\x7f]/',$incomingKey))
        throw new InvalidArgumentException('مفتاح Fish Audio يحتوي على رموز غير مسموحة');
    $voiceId=strtolower(trim($voiceId));
    if(!preg_match('/^[a-f0-9]{32}$/D',$voiceId))
        throw new InvalidArgumentException('Voice ID يجب أن يكون 32 حرف hex من Fish Audio');
    if(!in_array($model,['drama-3','s2.1-pro-free','s2.1-pro'],true))
        throw new InvalidArgumentException('موديل Fish Audio غير مدعوم');
    $profile=io_premium_admin_profile();
    $sealed=$profile['keys']['fish']??null;
    $oldKey=is_string($sealed)?(unseal_secret($sealed)??''):'';
    $key=trim($incomingKey)!==''?trim($incomingKey):$oldKey;
    if($key==='')throw new InvalidArgumentException('أدخل Fish Audio API Key أو استخدم المفتاح المحفوظ');
    // Do not activate an unverified key/voice, even with a syntactically valid ID.
    io_premium_admin_verify_fish($key,$voiceId,$model);
    $profile['keys']['fish']=$incomingKey!==''?seal_secret($key):$sealed;
    $profile['settings']['voiceId']=$voiceId;
    $profile['settings']['fishModel']=$model;
    $path=private_path().'/voice-lab/owner-1.json';
    $folder=dirname($path);
    if(is_link($folder)||is_link($path))throw new RuntimeException('مسار حفظ غير آمن');
    if(!is_dir($folder)&&!mkdir($folder,0700,true)&&!is_dir($folder))
        throw new RuntimeException('لا يمكن تجهيز المجلد الآمن');
    $out=json_encode($profile,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $temp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';
    try{
        if(file_put_contents($temp,$out,LOCK_EX)!==strlen($out))
            throw new RuntimeException('تعذر حفظ إعدادات IO Premium');
        @chmod($temp,0600);
        if(!rename($temp,$path))throw new RuntimeException('تعذر اعتماد الإعدادات المشفرة');
        @chmod($path,0600);
    }finally{if(is_file($temp))@unlink($temp);}
    $voice=one("SELECT id,status FROM voices WHERE code='io.premium'");
    if(!$voice)throw new RuntimeException('سجل IO Premium غير موجود في كتالوج المنصة');
    execute('UPDATE voices SET ready=1 WHERE id=?',[(int)$voice['id']]);
    foreach(all("SELECT id FROM accounts WHERE kind='platform' AND status='active'") as $platform){
        $acct=(int)$platform['id'];
        if(!one('SELECT id FROM voice_grants WHERE account_id=? AND voice_id=?',[$acct,(int)$voice['id']]))
            insert('INSERT INTO voice_grants(account_id,voice_id) VALUES (?,?)',[$acct,(int)$voice['id']]);
    }
}
function render_io_premium_admin_provider():string {
    $state=io_premium_admin_state();
    $voice=one("SELECT id,ready,status FROM voices WHERE code='io.premium'");
    $message=$state['configured']?'المفتاح محفوظ ومشفر على السيرفر · Voice ID جاهز':'لسه محتاج مفتاح Fish وVoice ID صحيح';
    $enabled=$voice&&(int)$voice['ready']===1&&(string)$voice['status']==='active';
    $content='<p class="info"><strong>الحالة: '.e($enabled?'IO Premium مفعّل':'IO Premium غير مفعّل').'</strong> · '.e($message).'</p>'.
        '<p class="dim">مفتاح Fish Audio بيتحفظ مشفر خارج public_html، ومش بيتبعت للموبايل أو للإيجنت. مفتاح Google Live بيتدار بصورة منفصلة من إعدادات المنصة.</p>'.
        form_start('save_io_premium_provider','/?tab=voices').
        '<div class="form-grid"><label class="field">Fish Audio API Key <input type="password" name="io_fish_api_key" autocomplete="new-password" maxlength="2048" placeholder="'.
        e($state['key_saved']?'مفتاح موجود بالفعل — اتركه فارغاً للاحتفاظ به':'الصق مفتاح Fish Audio هنا').'"></label>'.
        '<label class="field">Fish Voice ID <input name="io_voice_id" dir="ltr" inputmode="text" pattern="[a-fA-F0-9]{32}" maxlength="32" required value="'.e($state['voice_id']).'"></label>'.
        select_html('موديل IO Premium','io_fish_model',[
            'drama-3'=>'Drama 3 · مشاعر طبيعية',
            's2.1-pro'=>'S2.1 Pro',
            's2.1-pro-free'=>'S2.1 Pro Free'
        ],(string)$state['fish_model']).'</div>'.
        '<p class="dim">سيتم اختبار PCM Streaming مع Fish Audio قبل الحفظ والتفعيل. اترك المفتاح فارغًا للاحتفاظ بالقديم.</p>'.
        submit_btn('اختبار وحفظ إعدادات IO Premium');
    $policy=io_premium_access_policy();
    $count=(int)(one("SELECT COUNT(*) n FROM accounts WHERE kind!='platform' AND status='active'")['n']??0);
    $access=form_start('save_io_premium_access','/?tab=voices').
        '<p class="info">إعدادات الصوت دي على مستوى المنصة؛ حساب المستخدم لا يحتاج Fish API Key خاص طالما الصوت متاح له. لا تعرض المفتاح أو تنسخه للحسابات.</p>'.
        select_html('إتاحة IO Premium للحسابات','io_access_mode',[
          'all'=>'متاح لكل حساب نشط','limited'=>'متاح لأول عدد محدد من الحسابات النشطة',
          'off'=>'غير متاح لأي حساب'],(string)$policy['mode']).
        '<label class="field">الحد الأقصى للحسابات عند اختيار عدد محدد<input name="io_access_limit" type="number" min="1" max="100000" value="'.($policy['limit']?:1).'"></label>'.
        '<p class="dim">الحسابات النشطة غير التابعة للمنصة: '.$count.'. عند الحد المحدد يتم الاختيار بترتيب إنشاء الحساب (ID)، وليس إدخال مفاتيح عند المستخدم. حساب الأدمن لا يستهلك حصة. عند الإيقاف يتم رفض جلسات IO Premium الجديدة.</p>'.
        submit_btn('حفظ إتاحة IO Premium');
    return card('IO Premium — إعدادات Fish Audio والمفتاح',$content).
        card('توزيع IO Premium من الأدمن',$access);
}
