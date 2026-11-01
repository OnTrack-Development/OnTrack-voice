<?php
declare(strict_types=1);
/**
 * Narrow, real adapter for the EXISTING single-device pilot only.
 * No global pilot state is exposed to a reseller/customer tenant.
 */
function pilot_root():string{return '/home/appontrack/.ontrack-mobile';}
function pilot_json(string $name):array {
    if(!in_array($name,['settings.json','call-status.json'],true))throw new InvalidArgumentException('Invalid pilot file');
    $path=pilot_root().'/'.$name;
    if(!is_file($path)||is_link($path)||filesize($path)>32768)return [];
    $data=json_decode((string)file_get_contents($path),true);
    return is_array($data)?$data:[];
}
function pilot_settings():array {
    return array_replace([
        'revision'=>1,'enabled'=>false,'auto_answer'=>true,'voice'=>'Aoede',
        'input_channel'=>'mix','answer_delay_seconds'=>5,
        'response_style'=>'short_ar_egyptian','agent_name'=>'OnTrack',
        'instruction'=>'اتكلم بالمصري بجمل قصيرة.'
    ],pilot_json('settings.json'));
}
function pilot_write_policy(array $changes):int {
    $root=pilot_root();
    if(!is_dir($root)||is_link($root))throw new RuntimeException('هاتف التجربة غير مجهز');
    $lock=fopen($root.'/.console-policy.lock','c');
    if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('تعذر حجز حالة الهاتف');
    try{
        $base=pilot_settings();
        $next=array_replace($base,$changes);
        $next['revision']=max(1,(int)($base['revision']??0)+1);
        $temp=tempnam($root,'console-');
        if(!$temp)throw new RuntimeException('تعذر حفظ سياسة الهاتف');
        try{
           $encoded=json_encode($next,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
           if(file_put_contents($temp,$encoded,LOCK_EX)===false)throw new RuntimeException('تعذر الكتابة');
           chmod($temp,0600);
           if(!rename($temp,$root.'/settings.json'))throw new RuntimeException('تعذر تحديث الهاتف');
        }finally{if(is_file($temp))unlink($temp);}
        return $next['revision'];
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
