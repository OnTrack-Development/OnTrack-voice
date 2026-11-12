<?php
declare(strict_types=1);
/**
 * Voice catalogue & zero-token permanent sample cache (Google Gemini Live + Fish Audio).
 * Catalogued != tested on customer's configured provider/model.
 * Explicit administrator grant still required before customers can select a voice.
 */
function google_live_voices():array {
    return [
      'Zephyr'=>'Bright','Kore'=>'Firm','Orus'=>'Firm','Autonoe'=>'Bright',
      'Umbriel'=>'Easy-going','Erinome'=>'Clear','Laomedeia'=>'Upbeat','Schedar'=>'Even',
      'Achird'=>'Friendly','Sadachbia'=>'Lively','Puck'=>'Upbeat','Fenrir'=>'Excitable',
      'Aoede'=>'Breezy','Enceladus'=>'Breathy','Algieba'=>'Smooth','Algenib'=>'Gravelly',
      'Achernar'=>'Soft','Gacrux'=>'Mature','Zubenelgenubi'=>'Casual','Sadaltager'=>'Knowledgeable',
      'Charon'=>'Informative','Leda'=>'Youthful','Callirrhoe'=>'Easy-going','Iapetus'=>'Clear',
      'Despina'=>'Smooth','Rasalgethi'=>'Informative','Alnilam'=>'Firm','Pulcherrima'=>'Forward',
      'Vindemiatrix'=>'Gentle','Sulafat'=>'Warm'
    ];
}
function voice_infer_gender(string $hint):string {
    $lower=mb_strtolower($hint,'UTF-8');
    foreach(['aoede','kore','leda','zephyr','autonoe','erinome','laomedeia','despina',
        'callirrhoe','pulcherrima','vindemiatrix','sulafat','sadachbia',
        'female','woman','girl','lady','she/her','أنثى','بنت','فتاة','سيدة','نسائي'] as $needle){
        if(str_contains($lower,$needle))return 'female';
    }
    foreach(['orus','puck','fenrir','charon','umbriel','schedar','achird','enceladus',
        'algieba','algenib','achernar','gacrux','zubenelgenubi','sadaltager','iapetus',
        'rasalgethi','alnilam','male','man','boy','gentleman','he/him','ذكر','رجل','شاب','رجالي'] as $needle){
        if(str_contains($lower,$needle))return 'male';
    }
    return 'neutral';
}
function voice_normalize_dialect(array $item):string {
    $langs=array_map(static fn($v)=>strtolower(trim((string)$v)),
        is_array($item['languages']??null)?$item['languages']:[]);
    $blob=mb_strtolower(
        implode(' ',array_map('strval',is_array($item['tags']??null)?$item['tags']:[])).' '.
        (string)($item['title']??$item['name']??'').' '.
        (string)($item['description']??''),'UTF-8'
    );
    foreach([
        'لبناني'=>['leban','لبنان','بيروت','beirut'],
        'مصري'=>['egypt','مصر','قاهر','صعيد'],
        'خليجي'=>['gulf','saudi','emirati','kuwait','qatar','خليج','سعود','إمارات','كويت'],
        'سوري'=>['syria','شام','سوري','دمشق'],
        'أردني'=>['jordan','أردن'],
        'مغربي'=>['morocc','maghreb','مغرب'],
    ] as $label=>$needles){
        foreach($needles as $n){if(str_contains($blob,$n))return $label;}
    }
    if(in_array('ar',$langs,true))return count($langs)>1?'عربي / متعدد':'عربي';
    if(in_array('en',$langs,true))return count($langs)>1?'إنجليزي / متعدد':'إنجليزي';
    return $langs?'متعدد اللغات':'';
}
function sync_google_live_voices():int{
    $existing=all("SELECT id,code,ready,gender,dialect FROM voices WHERE provider='gemini_live'");
    $map=[];foreach($existing as $row)$map[strtolower((string)$row['code'])]=$row;
    $count=0;
    foreach(google_live_voices() as $name=>$description){
        $code='google.'.strtolower($name);
        $gender=voice_infer_gender($name);
        // A voice is a catalog option, not automatically certified active for real calls.
        if(!isset($map[$code])){
            insert("INSERT INTO voices(code,name,provider,tier,ready,status,gender,dialect) VALUES (?,?,?,'basic',0,'active',?,?)",
                  [$code,$name.' · '.$description,'gemini_live',$gender,'متعدد اللغات']);
        }else{
            execute("UPDATE voices SET name=?,gender=CASE WHEN gender IS NULL OR gender='' OR gender='neutral' THEN ? ELSE gender END,dialect=CASE WHEN dialect IS NULL OR dialect='' THEN 'متعدد اللغات' ELSE dialect END WHERE id=?",
                [$name.' · '.$description,$gender,$map[$code]['id']]);
        }
        $count++;
    }
    return $count;
}

/**
 * Optional live provider discovery: Google Gemini Voices API, not a hardcoded demo.
 * Caller supplies an API key for this one operation. Do not save or log it.
 */
function fetch_google_provider_voices(string $apiKey):int {
    if(!google_key_looks_valid($apiKey))
        throw new InvalidArgumentException('أدخل مفتاح API كنص واحد بدون رموز تحكم أو ملف JSON.');
    if(!function_exists('curl_init'))throw new RuntimeException('cURL غير متاح على الاستضافة');
    $seen=[];$pageToken='';$count=0;
    for($page=0;$page<15;$page++){
        $url='https://generativelanguage.googleapis.com/v1beta/voices?page_size=100';
        if($pageToken!=='')$url.='&page_token='.rawurlencode($pageToken);
        $h=curl_init($url);
        curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,
            CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_HTTPHEADER=>['x-goog-api-key: '.$apiKey,'Accept: application/json'],
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
        try{
            $body=curl_exec($h);$status=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);
        }finally{curl_close($h);}
        if(!is_string($body)||$status!==200)throw new RuntimeException(google_api_failure($status));
        $response=json_decode($body,true);
        if(!is_array($response)||!is_array($response['voices']??null))
            throw new RuntimeException('استجابة Google لا تحتوي على كتالوج أصوات');
        foreach($response['voices'] as $item){
            if(!is_array($item))continue;
            $voiceId=(string)($item['id']??'');
            $label=(string)($item['displayName']??$item['display_name']??$voiceId);
            $type=(string)($item['type']??'prebuilt');
            if(!preg_match('/^[A-Za-z0-9_-]{2,140}$/D',$voiceId)||!in_array($type,['prebuilt','prompted','replicated'],true))continue;
            $code='googleapi.'.$voiceId;
            if(isset($seen[$code]))continue;$seen[$code]=true;
            $gender=voice_infer_gender($label.' '.$voiceId.' '.(string)($item['gender']??''));
            $existing=one('SELECT id FROM voices WHERE code=?',[$code]);
            if(!$existing)
                insert("INSERT INTO voices(code,name,provider,tier,ready,status,gender,dialect) VALUES (?,?,?,'premium',0,'active',?,?)",
                   [$code,mb_substr($label,0,120),'gemini_tts',$gender,'متعدد اللغات']);
            else execute('UPDATE voices SET name=? WHERE id=?',[mb_substr($label,0,120),$existing['id']]);
            $count++;
        }
        $next=(string)($response['nextPageToken']??$response['next_page_token']??'');
        if($next===''||$next===$pageToken)break;
        $pageToken=$next;
    }
    return $count;
}

/** Resolve Fish Audio key from optional override input or platform vault (never leaked). */
function fish_audio_saved_key(?string $overrideKey=null):string {
    $raw=trim((string)$overrideKey);
    if($raw!==''){
        if(strlen($raw)>2048||preg_match('/[\x00-\x1f\x7f]/',$raw))
            throw new InvalidArgumentException('مفتاح Fish Audio يحتوي على رموز غير مسموحة');
        return $raw;
    }
    require_once __DIR__.'/IoPremiumProvider.php';
    $profile=io_premium_admin_profile();
    $sealed=$profile['keys']['fish']??null;
    if(!is_string($sealed)||$sealed==='')return '';
    return trim((string)(unseal_secret($sealed)??''));
}

/**
 * Sync real Fish Audio model catalogue (up to 50 voices) into voices table.
 * Preserves existing sample_path caches so previews never spend duplicate tokens.
 */
function fish_audio_sync_catalog(?string $overrideKey=null,string $query='',int $limit=30,?callable $transport=null,bool $markReady=false):array {
    $apiKey=fish_audio_saved_key($overrideKey);
    if($apiKey==='')
        throw new InvalidArgumentException('أدخل مفتاح Fish Audio أو احفظه أولًا في إعدادات IO Premium');
    $limit=max(1,min(50,$limit));
    $cleanQuery=trim(mb_substr($query,0,80));
    $url='https://api.fish.audio/model?page_size='.$limit.'&page_number=1&sort_by=task_count';
    if($cleanQuery!=='')$url.='&title='.rawurlencode($cleanQuery);
    if($transport!==null){
        $res=$transport($url,$apiKey);
        if(!is_array($res))throw new RuntimeException('تعذر قراءة استجابة Fish Audio');
        $status=(int)($res['status']??0);
        $body=$res['body']??false;
    }else{
        if(!function_exists('curl_init'))throw new RuntimeException('cURL غير متاح على الاستضافة');
        $h=curl_init($url);
        if(!$h)throw new RuntimeException('تعذر بدء اتصال Fish Audio');
        curl_setopt_array($h,[
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$apiKey,'Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2
        ]);
        try{$body=curl_exec($h);$status=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);}
        finally{curl_close($h);}
    }
    if(!is_string($body)||$status!==200)
        throw new RuntimeException('تعذر جلب كتالوج Fish Audio (HTTP '.$status.')');
    $json=json_decode($body,true);
    $items=is_array($json)?($json['items']??$json['data']??(array_is_list($json)?$json:null)):null;
    if(!is_array($items))throw new RuntimeException('استجابة Fish Audio لا تحتوي على قائمة أصوات صالحة');
    $inserted=0;$updated=0;$seen=[];
    foreach($items as $item){
        if(!is_array($item))continue;
        $refId=strtolower(trim((string)($item['_id']??$item['id']??'')));
        if(preg_match('/^[0-9a-f]{32}$/D',$refId)!==1)continue;
        $code='fish.'.$refId;
        if(isset($seen[$code]))continue;$seen[$code]=true;
        $title=trim((string)($item['title']??$item['name']??'Fish '.$refId));
        if($title==='')$title='Fish '.substr($refId,0,8);
        $title=mb_substr($title,0,120);
        $desc=trim((string)($item['description']??''));
        $tags=is_array($item['tags']??null)?implode(' ',array_map('strval',$item['tags'])):'';
        $gender=voice_infer_gender($title.' '.$desc.' '.$tags.' '.(string)($item['gender']??''));
        $dialect=voice_normalize_dialect($item);
        $sampleUrl='';
        if(is_array($item['samples']??null)&&isset($item['samples'][0])&&is_array($item['samples'][0])){
            $cand=trim((string)($item['samples'][0]['audio']??$item['samples'][0]['url']??''));
            if($cand!==''&&filter_var($cand,FILTER_VALIDATE_URL)!==false
                &&strtolower((string)parse_url($cand,PHP_URL_SCHEME))==='https'
                &&strlen($cand)<=500)$sampleUrl=$cand;
        }
        $meta=json_encode([
            'description'=>mb_substr($desc,0,300),
            'languages'=>is_array($item['languages']??null)?array_values($item['languages']):[],
            'tags'=>is_array($item['tags']??null)?array_slice(array_values($item['tags']),0,8):[],
        ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $existing=one('SELECT id,ready,sample_path FROM voices WHERE code=?',[$code]);
        if(!$existing){
            insert("INSERT INTO voices(code,name,provider,tier,ready,status,gender,dialect,source_ref_id,sample_url,meta_json) VALUES (?,?,?,'premium',?,'active',?,?,?,?,?)",
                [$code,$title,'fish_audio',$markReady?1:0,$gender,$dialect,$refId,$sampleUrl,$meta]);
            $inserted++;
        }else{
            $nextReady=$markReady?1:(int)$existing['ready'];
            execute("UPDATE voices SET name=?,provider='fish_audio',tier='premium',gender=?,dialect=CASE WHEN ?<>'' THEN ? ELSE dialect END,source_ref_id=?,sample_url=CASE WHEN ?<>'' THEN ? ELSE sample_url END,meta_json=?,ready=? WHERE id=?",
                [$title,$gender,$dialect,$dialect,$refId,$sampleUrl,$sampleUrl,$meta,$nextReady,(int)$existing['id']]);
            $updated++;
        }
    }
    return ['synced'=>$inserted+$updated,'inserted'=>$inserted,'updated'=>$updated];
}

function fish_audio_add_custom(string $name,string $refId,string $gender='neutral',string $dialect='',bool $ready=false):int {
    $name=trim($name);
    if($name===''||mb_strlen($name)>120)throw new InvalidArgumentException('اكتب اسمًا مناسبًا للصوت (حتى 120 حرفًا)');
    $refId=strtolower(trim($refId));
    if(preg_match('/^[0-9a-f]{32}$/D',$refId)!==1)
        throw new InvalidArgumentException('Reference ID يجب أن يكون 32 حرف hex من Fish Audio');
    if(!in_array($gender,['female','male','neutral'],true))$gender='neutral';
    $dialect=trim(mb_substr($dialect,0,80));
    $code='fish.'.$refId;
    $existing=one('SELECT id,ready FROM voices WHERE code=?',[$code]);
    if($existing){
        $nextReady=$ready?1:(int)$existing['ready'];
        execute("UPDATE voices SET name=?,provider='fish_audio',tier='premium',gender=?,dialect=?,source_ref_id=?,ready=?,status='active' WHERE id=?",
            [$name,$gender,$dialect,$refId,$nextReady,(int)$existing['id']]);
        return (int)$existing['id'];
    }
    return insert("INSERT INTO voices(code,name,provider,tier,ready,status,gender,dialect,source_ref_id) VALUES (?,?,?,'premium',?,'active',?,?,?)",
        [$code,$name,'fish_audio',$ready?1:0,$gender,$dialect,$refId]);
}

function voice_sample_cache_dir():string {
    return dirname(__DIR__).'/assets/voice-samples';
}
function voice_sample_private_dir():string {
    return private_path().'/voice-samples';
}
function voice_sample_detect_ext(string $bytes):?string {
    if(strlen($bytes)<12)return null;
    if(str_starts_with($bytes,'RIFF')&&substr($bytes,8,4)==='WAVE')return 'wav';
    if(str_starts_with($bytes,'OggS'))return 'ogg';
    if(str_starts_with($bytes,'ID3'))return 'mp3';
    $b0=ord($bytes[0]);$b1=ord($bytes[1]);
    if($b0===0xFF&&($b1&0xE0)===0xE0)return 'mp3';
    return null;
}
function voice_sample_write_atomic(string $dest,string $bytes,int $mode):void {
    $dir=dirname($dest);
    if(!is_dir($dir)&&!mkdir($dir,$mode===0600?0700:0755,true)&&!is_dir($dir))
        throw new RuntimeException('تعذر إنشاء مجلد حفظ عينات الصوت');
    $tmp=$dest.'.'.bin2hex(random_bytes(6)).'.tmp';
    try{
        if(file_put_contents($tmp,$bytes,LOCK_EX)!==strlen($bytes))
            throw new RuntimeException('تعذر كتابة ملف عينة الصوت');
        @chmod($tmp,$mode);
        if(!rename($tmp,$dest))throw new RuntimeException('تعذر اعتماد ملف عينة الصوت');
        @chmod($dest,$mode);
    }finally{if(is_file($tmp))@unlink($tmp);}
}
function voice_sample_url_allowed(string $url):bool {
    $parts=parse_url($url);
    if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https')return false;
    $host=strtolower((string)($parts['host']??''));
    if($host===''||$host==='localhost'||str_ends_with($host,'.local')
        ||filter_var($host,FILTER_VALIDATE_IP)!==false)return false;
    foreach(['fish.audio','.fish.audio','.r2.dev','.cloudfront.net','.amazonaws.com','.googleapis.com'] as $suffix){
        if($host===$suffix||str_ends_with($host,$suffix))return true;
    }
    return false;
}
function voice_sample_pcm_to_wav(string $pcm,int $sampleRate=24000,int $channels=1,int $bits=16):string {
    $byteRate=$sampleRate*$channels*intdiv($bits,8);
    $blockAlign=$channels*intdiv($bits,8);
    $dataSize=strlen($pcm);
    return 'RIFF'.pack('V',36+$dataSize).'WAVEfmt '.pack('VvvVVvv',16,1,$channels,$sampleRate,$byteRate,$blockAlign,$bits)
        .'data'.pack('V',$dataSize).$pcm;
}

/**
 * Permanent zero-token sample cache for all voices.
 * Cache hits in public assets/ or private mirror NEVER spend provider tokens.
 */
function voice_sample_cache_ensure(int $voiceId,?callable $transport=null):array {
    $voice=one('SELECT id,code,name,provider,source_ref_id,sample_path,sample_url FROM voices WHERE id=?',[$voiceId]);
    if(!$voice)throw new InvalidArgumentException('الصوت المطلوب غير موجود');
    $code=(string)$voice['code'];
    $provider=(string)$voice['provider'];
    $refTag='';
    if($code==='io.premium'){
        require_once __DIR__.'/IoPremiumProvider.php';
        $st=io_premium_admin_state();
        if($st['voice_id']!=='')$refTag='-'.substr((string)$st['voice_id'],0,8);
    }
    $pubDir=voice_sample_cache_dir();
    $privDir=voice_sample_private_dir();
    $existingRel=trim((string)($voice['sample_path']??''));
    $existingBase=$existingRel!==''?basename($existingRel):'';
    if($existingBase!==''&&preg_match('/^[A-Za-z0-9._-]{4,100}\.(mp3|wav|ogg)$/D',$existingBase)===1
        &&($refTag===''||str_contains($existingBase,$refTag))){
        $pubFile=$pubDir.'/'.$existingBase;
        $privFile=$privDir.'/'.$existingBase;
        if(is_file($pubFile)&&filesize($pubFile)>64){
            if(!is_file($privFile))@voice_sample_write_atomic($privFile,(string)file_get_contents($pubFile),0600);
            return ['ok'=>true,'cached'=>true,'tokens_used'=>0,'voice_id'=>$voiceId,
                'url'=>'/saas/console/assets/voice-samples/'.$existingBase];
        }
        if(is_file($privFile)&&filesize($privFile)>64){
            $bytes=(string)file_get_contents($privFile);
            voice_sample_write_atomic($pubFile,$bytes,0644);
            return ['ok'=>true,'cached'=>true,'tokens_used'=>0,'voice_id'=>$voiceId,
                'url'=>'/saas/console/assets/voice-samples/'.$existingBase];
        }
    }
    $slug=preg_replace('/[^a-z0-9._-]+/','-',strtolower($code));
    $slug=trim((string)$slug,'-')?:'voice';
    foreach(['mp3','wav','ogg'] as $extTry){
        $cand='v'.$voiceId.'-'.substr($slug,0,48).$refTag.'.'.$extTry;
        $pubCand=$pubDir.'/'.$cand;
        $privCand=$privDir.'/'.$cand;
        if(is_file($pubCand)&&filesize($pubCand)>64){
            execute('UPDATE voices SET sample_path=? WHERE id=?',['assets/voice-samples/'.$cand,$voiceId]);
            if(!is_file($privCand))@voice_sample_write_atomic($privCand,(string)file_get_contents($pubCand),0600);
            return ['ok'=>true,'cached'=>true,'tokens_used'=>0,'voice_id'=>$voiceId,
                'url'=>'/saas/console/assets/voice-samples/'.$cand];
        }
        if(is_file($privCand)&&filesize($privCand)>64){
            $bytes=(string)file_get_contents($privCand);
            voice_sample_write_atomic($pubCand,$bytes,0644);
            execute('UPDATE voices SET sample_path=? WHERE id=?',['assets/voice-samples/'.$cand,$voiceId]);
            return ['ok'=>true,'cached'=>true,'tokens_used'=>0,'voice_id'=>$voiceId,
                'url'=>'/saas/console/assets/voice-samples/'.$cand];
        }
    }

    // Cache miss — fetch remote sample_url first (0 TTS tokens) or synthesize ONCE.
    $bytes='';$tokensUsed=0;$ext=null;
    $sampleUrl=trim((string)($voice['sample_url']??''));
    if($sampleUrl!==''&&voice_sample_url_allowed($sampleUrl)){
        $dlStatus=0;$dlBody='';
        if($transport!==null){
            $res=$transport('download',$sampleUrl,null,[]);
            $dlStatus=(int)($res['status']??0);
            $dlBody=(string)($res['body']??'');
        }elseif(function_exists('curl_init')){
            $h=curl_init($sampleUrl);
            if($h){
                curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,
                    CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,
                    CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
                $raw=curl_exec($h);$dlStatus=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);
                if(is_string($raw))$dlBody=$raw;
            }
        }
        if($dlStatus===200&&strlen($dlBody)>=128&&strlen($dlBody)<=2097152){
            $det=voice_sample_detect_ext($dlBody);
            if($det!==null){$bytes=$dlBody;$ext=$det;}
        }
    }

    if($ext===null && ($provider==='fish_audio'||$code==='io.premium'||str_starts_with($code,'fish.'))){
        require_once __DIR__.'/IoPremiumProvider.php';
        $state=io_premium_admin_state();
        $apiKey=fish_audio_saved_key();
        if($apiKey==='')throw new InvalidArgumentException('احفظ مفتاح Fish Audio في إعدادات IO Premium أولًا لتجهيز العينة الصوتية');
        $refId=strtolower(trim((string)($voice['source_ref_id']??'')));
        if($refId===''&&str_starts_with($code,'fish.'))$refId=strtolower(substr($code,5));
        if($refId===''&&$code==='io.premium')$refId=strtolower((string)$state['voice_id']);
        if(preg_match('/^[0-9a-f]{32}$/D',$refId)!==1)
            throw new InvalidArgumentException('معرف Fish Audio Voice ID غير مكتمل لهذا الصوت');
        $model=in_array((string)$state['fish_model'],['drama-3','s2.1-pro-free','s2.1-pro'],true)?(string)$state['fish_model']:'drama-3';
        $payload=json_encode([
            'text'=>'أهلاً بيك في IO Voice، ده صوت تجريبي للمعاينة.',
            'reference_id'=>$refId,'format'=>'mp3','streaming'=>false,'latency'=>'normal'
        ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $ttsStatus=0;$ttsBody='';
        if($transport!==null){
            $res=$transport('fish_tts','https://api.fish.audio/v1/tts',$payload,['model'=>$model,'key'=>$apiKey]);
            $ttsStatus=(int)($res['status']??0);
            $ttsBody=(string)($res['body']??'');
        }else{
            if(!function_exists('curl_init'))throw new RuntimeException('cURL غير متاح على الاستضافة');
            $h=curl_init('https://api.fish.audio/v1/tts');
            if(!$h)throw new RuntimeException('تعذر بدء طلب المعاينة');
            curl_setopt_array($h,[
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,
                CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$apiKey,'Content-Type: application/json','Accept: audio/mpeg','model: '.$model],
                CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2
            ]);
            $raw=curl_exec($h);$ttsStatus=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);
            if(is_string($raw))$ttsBody=$raw;
        }
        if($ttsStatus!==200||strlen($ttsBody)<128||strlen($ttsBody)>2097152)
            throw new RuntimeException('تعذر توليد عينة الصوت من Fish Audio (HTTP '.$ttsStatus.')');
        $det=voice_sample_detect_ext($ttsBody);
        if($det===null)throw new RuntimeException('ملف العينة المستلم من Fish Audio غير صالح');
        $bytes=$ttsBody;$ext=$det;$tokensUsed=1;
    }

    if($ext===null && in_array($provider,['gemini_live','gemini_tts'],true)){
        require_once __DIR__.'/TenantVoiceSession.php';
        $voiceName=ucfirst(preg_replace('/^(?:google|googleapi)\./','',$code));
        $gKey='';
        try{$prov=tenant_voice_provider();$gKey=(string)($prov['key']??'');}catch(Throwable){$gKey='';}
        if($gKey===''&&$transport===null)
            throw new InvalidArgumentException('احفظ مفتاح Google API لتوليد عينة لهذا الصوت أو استخدم أصوات Fish Audio');
        $gUrl='https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-tts:generateContent';
        $gPayload=json_encode([
            'contents'=>[['parts'=>[['text'=>'أهلاً بيك، ده صوت تجريبي للمعاينة داخل IO Voice.']]]],
            'generationConfig'=>[
                'responseModalities'=>['AUDIO'],
                'speechConfig'=>['voiceConfig'=>['prebuiltVoiceConfig'=>['voiceName'=>$voiceName]]]
            ]
        ],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $gStatus=0;$gBody='';
        if($transport!==null){
            $res=$transport('google_tts',$gUrl,$gPayload,['key'=>$gKey]);
            $gStatus=(int)($res['status']??0);
            $gBody=(string)($res['body']??'');
        }else{
            if(!function_exists('curl_init'))throw new RuntimeException('cURL غير متاح على الاستضافة');
            $h=curl_init($gUrl);
            if(!$h)throw new RuntimeException('تعذر بدء طلب معاينة Google');
            curl_setopt_array($h,[
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$gPayload,
                CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-goog-api-key: '.$gKey],
                CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2
            ]);
            $raw=curl_exec($h);$gStatus=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);
            if(is_string($raw))$gBody=$raw;
        }
        $gJson=is_string($gBody)?json_decode($gBody,true):null;
        $b64=is_array($gJson)?(string)($gJson['candidates'][0]['content']['parts'][0]['inlineData']['data']??''):'';
        $pcm=$b64!==''?base64_decode($b64,true):false;
        if($gStatus!==200||$pcm===false||strlen($pcm)<64)
            throw new RuntimeException('المعاينة الصوتية غير متاحة حاليًا لهذا الصوت على Google (HTTP '.$gStatus.')');
        $bytes=voice_sample_pcm_to_wav($pcm);$ext='wav';$tokensUsed=1;
    }

    if($ext===null||$bytes==='')
        throw new InvalidArgumentException('هذا الصوت لا يدعم توليد عينة معاينة حاليًا');

    $filename='v'.$voiceId.'-'.substr($slug,0,48).$refTag.'.'.$ext;
    voice_sample_write_atomic($privDir.'/'.$filename,$bytes,0600);
    voice_sample_write_atomic($pubDir.'/'.$filename,$bytes,0644);
    execute('UPDATE voices SET sample_path=? WHERE id=?',['assets/voice-samples/'.$filename,$voiceId]);
    return ['ok'=>true,'cached'=>false,'tokens_used'=>$tokensUsed,'voice_id'=>$voiceId,
        'url'=>'/saas/console/assets/voice-samples/'.$filename];
}

/** Distinguish valid Gemini API credentials from separate Live API capabilities. */
function google_key_looks_valid(string $key):bool{
    return preg_match('/\A[A-Za-z0-9._~-]{4,256}\z/D',trim($key))===1;
}
function google_api_failure(int $status):string{
    return match($status){
        400=>'Google رفض صيغة الطلب. مطلوب Gemini API Key وليس OAuth أو ملف خدمة JSON.',
        401=>'Google رفض اعتماد المفتاح HTTP 401. راجع مفتاح Gemini API.',
        403=>'Google رفض الصلاحية HTTP 403: تحقق من قيود المفتاح والمشروع وتفعيل Generative Language API. لا يعني أن المفتاح نفسه خاطئ.',
        404=>'خدمة Google أو إصدار API غير متاح لهذا المفتاح حاليًا (404)، وليس دليلًا على خطأ المفتاح.',
        429=>'الحد المسموح أو حصة Google انتهت مؤقتًا (429).',
        0=>'السيرفر تعذر عليه الاتصال بخوادم Google؛ راجع DNS والاتصال وTLS.',
        default=>'اتصال Google أعاد HTTP '.$status.'، راجع قيود المشروع والموديل.'
    };
}
function google_api_probe(string $key, ?callable $transport=null):void{
    // Only Google can determine whether an API key is authenticated and permitted.
    $key=trim($key);
    if(!google_key_looks_valid($key))
        throw new InvalidArgumentException('المفتاح فارغ أو يحتوي على رموز غير صالحة للإرسال. الصق قيمة API Key فقط.');
    if($transport!==null){
        // Offline tests supply an explicit response; production always contacts Google.
        $result=$transport($key);
        if(!is_array($result))throw new RuntimeException('تعذر قراءة نتيجة اتصال Google.');
        $status=(int)($result['status']??0);
        $body=$result['body']??false;
    }else{
        if(!function_exists('curl_init'))throw new RuntimeException('مكتبة cURL غير متاحة على الاستضافة.');
        $h=curl_init('https://generativelanguage.googleapis.com/v1beta/models?pageSize=1');
        if(!$h)throw new RuntimeException('تعذر بدء اتصال Google.');
        curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,
            CURLOPT_HTTPHEADER=>['x-goog-api-key: '.$key,'Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        try{$body=curl_exec($h);$status=(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE);}
        finally{curl_close($h);}
    }
    if(!is_string($body)||$status!==200)throw new RuntimeException(google_api_failure($status));
    $json=json_decode($body,true);
    if(!is_array($json)||!is_array($json['models']??null))
        throw new RuntimeException('Google ردّ لكن قائمة الموديلات غير مفهومة. مش هنعتبر المفتاح غلط.');
}
