<?php
declare(strict_types=1);
/**
 * Narrow, real adapter for the EXISTING single-device pilot only.
 * No global pilot state is exposed to a reseller/customer tenant.
 */
function pilot_root():string{
    // Fixture-only override. Never accept a path or root from HTTP.
    if(PHP_SAPI==='cli' && preg_match('/^saasall_[0-9_]+$/D',
        (string)(getenv('ONTRACK_CI_NAMESPACE')?:'')) &&
        ($requested=getenv('ONTRACK_CI_PILOT_ROOT'))!==false){
        $base=realpath(sys_get_temp_dir());$resolved=realpath($requested);
        if(!$base||!$resolved||!is_dir($resolved)||is_link($requested)||
           !str_starts_with($resolved,$base.DIRECTORY_SEPARATOR))
            throw new RuntimeException('unsafe_test_pilot_root');
        return $resolved;
    }
    return '/home/appontrack/.ontrack-mobile';
}
function pilot_json(string $name):array {
    if(!in_array($name,['settings.json','call-status.json'],true))throw new InvalidArgumentException('Invalid pilot file');
    $path=pilot_root().'/'.$name;
    if(!file_exists($path) && !is_link($path))return [];
    if(!is_file($path)||is_link($path)||!is_readable($path)||
       filesize($path)>32768)
        throw new RuntimeException('pilot_file_unavailable');
    $raw=@file_get_contents($path);
    if(!is_string($raw))throw new RuntimeException('pilot_file_unavailable');
    $data=json_decode($raw,true);
    if(!is_array($data)||array_is_list($data)||
       !str_starts_with(ltrim($raw),'{'))
        throw new RuntimeException('pilot_file_corrupt');
    return $data;
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
    // The legacy mobile reader expects flat settings.json, not a nested
    // SaaS-only snapshot. Reject unknown fields without dropping any
    // recognized or forward-compatible fields already stored by the device.
    $allowed=['agent_name','voice','instruction','auto_answer','enabled',
              'answer_delay_seconds'];
    foreach($changes as $key=>$value)
        if(!is_string($key)||!in_array($key,$allowed,true))
            throw new InvalidArgumentException('pilot_policy_unknown_field');
    if(isset($changes['voice']) && (!is_string($changes['voice']) ||
       !preg_match('/^[A-Za-z0-9_-]{2,40}$/D',$changes['voice'])))
        throw new InvalidArgumentException('pilot_voice_invalid');
    if(isset($changes['agent_name']) && (!is_string($changes['agent_name']) ||
       mb_strlen(trim($changes['agent_name']))<1||
       mb_strlen($changes['agent_name'])>120))
        throw new InvalidArgumentException('pilot_agent_name_invalid');
    if(isset($changes['instruction'])&&(!is_string($changes['instruction'])||
       mb_strlen($changes['instruction'])>5000))
        throw new InvalidArgumentException('pilot_instruction_invalid');
    foreach(['enabled','auto_answer'] as $flag)
        if(array_key_exists($flag,$changes)&&!is_bool($changes[$flag]))
            throw new InvalidArgumentException('pilot_policy_flag_invalid');
    if(array_key_exists('answer_delay_seconds',$changes) &&
      (!is_int($changes['answer_delay_seconds']) ||
       $changes['answer_delay_seconds']<0 ||
       $changes['answer_delay_seconds']>30))
        throw new InvalidArgumentException('pilot_answer_delay_invalid');
    $root=pilot_root();
    if(!is_dir($root)||is_link($root))throw new RuntimeException('هاتف التجربة غير مجهز');
    $lock=fopen($root.'/.console-policy.lock','c');
    if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('تعذر حجز حالة الهاتف');
    try{
        $base=pilot_settings();
        $next=array_replace($base,$changes);
        if(!is_int($base['revision'])||$base['revision']<0||
           $base['revision']>PHP_INT_MAX-2)
            throw new RuntimeException('pilot_revision_invalid');
        $next['revision']=max(1,$base['revision']+1);
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
