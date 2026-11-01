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
