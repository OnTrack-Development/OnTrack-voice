<?php
declare(strict_types=1);
/**
 * Google Gemini Live prebuilt voices from Google's published Live/TTS catalogue.
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
function sync_google_live_voices():int{
    $existing=all("SELECT id,code,ready FROM voices WHERE provider='gemini_live'");
    $map=[];foreach($existing as $row)$map[strtolower((string)$row['code'])]=$row;
    $count=0;
    foreach(google_live_voices() as $name=>$description){
        $code='google.'.strtolower($name);
        // A voice is a catalog option, not automatically certified active for real calls.
        if(!isset($map[$code])){
            insert("INSERT INTO voices(code,name,provider,tier,ready,status) VALUES (?,?,?,'basic',0,'active')",
                  [$code,$name.' · '.$description,'gemini_live']);
        }else{
            execute('UPDATE voices SET name=? WHERE id=?',[$name.' · '.$description,$map[$code]['id']]);
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
        throw new InvalidArgumentException('أدخل Gemini API Key كاملًا بدل بيانات حساب Google أو OAuth.');
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
            $existing=one('SELECT id FROM voices WHERE code=?',[$code]);
            if(!$existing)
                insert("INSERT INTO voices(code,name,provider,tier,ready,status) VALUES (?,?,?,'premium',0,'active')",
                   [$code,mb_substr($label,0,120),'gemini_tts']);
            else execute('UPDATE voices SET name=? WHERE id=?',[mb_substr($label,0,120),$existing['id']]);
            $count++;
        }
        $next=(string)($response['nextPageToken']??$response['next_page_token']??'');
        if($next===''||$next===$pageToken)break;
        $pageToken=$next;
    }
    return $count;
}


/** Distinguish valid Gemini API credentials from separate Live API capabilities. */
function google_key_looks_valid(string $key):bool{
    return preg_match('/^[A-Za-z0-9_-]{16,256}$/D',trim($key))===1;
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
function google_api_probe(string $key):void{
    if(!google_key_looks_valid($key))throw new InvalidArgumentException('صيغة Google Gemini API Key غير مكتملة؛ لا تضع ملف JSON أو مفتاح OAuth.');
    if(!function_exists('curl_init'))throw new RuntimeException('مكتبة cURL غير متاحة على الاستضافة.');
    $h=curl_init('https://generativelanguage.googleapis.com/v1beta/models?pageSize=1');
    if(!$h)throw new RuntimeException('تعذر بدء اتصال Google.');
    curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,
        CURLOPT_HTTPHEADER=>['x-goog-api-key: '.$key,'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    try{$body=curl_exec($h);$status=(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE);}
    finally{curl_close($h);}
    if(!is_string($body)||$status!==200)throw new RuntimeException(google_api_failure($status));
    $json=json_decode($body,true);
    if(!is_array($json)||!is_array($json['models']??null))
        throw new RuntimeException('Google ردّ لكن قائمة الموديلات غير مفهومة. مش هنعتبر المفتاح غلط.');
}
