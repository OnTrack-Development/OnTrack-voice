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
    if(!preg_match('/^[A-Za-z0-9_-]{12,256}$/D',$apiKey))
        throw new InvalidArgumentException('مفتاح Google غير صالح');
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
        if(!is_string($body)||$status!==200)throw new RuntimeException('تعذر جلب الكتالوج من Google (HTTP '.$status.')');
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
