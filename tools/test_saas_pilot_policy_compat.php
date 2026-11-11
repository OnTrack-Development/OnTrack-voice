<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!preg_match('/^saasall_[0-9_]+$/D',(string)(getenv('ONTRACK_CI_NAMESPACE')?:'')))
    throw new RuntimeException('isolated_pilot_policy_test_only');
$root=sys_get_temp_dir().'/ontrack-pilot-policy-'.bin2hex(random_bytes(7));
if(!mkdir($root,0700))throw new RuntimeException('fixture_directory_failed');
putenv('ONTRACK_CI_PILOT_ROOT='.$root);
require __DIR__.'/../saas/console/_core/MobilePilot.php';
function pilot_check(bool $ok,string $reason):void {
  if(!$ok)throw new RuntimeException('PILOT_COMPAT_FAIL '.$reason);
}
try {
  pilot_check(pilot_settings()['answer_delay_seconds']===5,'safe five second default');
  $old=['revision'=>'7','enabled'=>true,'auto_answer'=>true,'voice'=>'Aoede',
    'agent_name'=>'Legacy voice','input_channel'=>'mix',
    'answer_delay_seconds'=>5,'instruction'=>'مرحبا','legacy_device_flag'=>'keep_me'];
  file_put_contents($root.'/settings.json',json_encode($old,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
  $newRevision=pilot_write_policy(['agent_name'=>'SaaS policy','answer_delay_seconds'=>5,
    'enabled'=>true,'auto_answer'=>true,'voice'=>'Aoede','instruction'=>'أهلاً']);
  pilot_check($newRevision===8,'legacy string revision increments without reset');
  $saved=json_decode((string)file_get_contents($root.'/settings.json'),true);
  pilot_check($saved['revision']===8&&$saved['legacy_device_flag']==='keep_me'
    &&$saved['input_channel']==='mix','preserve unrecognized legacy properties');
  pilot_check($saved['answer_delay_seconds']===5&&$saved['agent_name']==='SaaS policy',
    'SaaS updates existing flat policy and device retains old schema');
  $previous=(string)file_get_contents($root.'/settings.json');
  foreach([
    ['wrong_field'=>true],
    ['voice'=>'!bad-voice'],
    ['enabled'=>'true'],
    ['auto_answer'=>1],
    ['answer_delay_seconds'=>31],
    ['instruction'=>str_repeat('X',5001)]
  ] as $change){
    $denied=false;try{pilot_write_policy($change);}catch(InvalidArgumentException $e){$denied=true;}
    pilot_check($denied,'invalid change rejected without writing');
    pilot_check(file_get_contents($root.'/settings.json')===$previous,
       'invalid payload leaves policy bytes unchanged');
  }
  // Malformed settings must never be replaced by defaults on an admin write.
  file_put_contents($root.'/settings.json','{bad json');
  $corrupt=false;
  try{pilot_write_policy(['enabled'=>false]);}catch(RuntimeException $e){
    $corrupt=$e->getMessage()==='pilot_file_corrupt';
  }
  pilot_check($corrupt && file_get_contents($root.'/settings.json')==='{bad json',
      'corrupt legacy policy fails closed rather than erasing device configuration');
  file_put_contents($root.'/settings.json','{}');
  pilot_check(pilot_write_policy(['enabled'=>false])===2,
      'empty JSON object remains a compatible policy');
  unlink($root.'/settings.json');
  file_put_contents($root.'/secret.txt','private');
  symlink($root.'/secret.txt',$root.'/settings.json');
  $refused=false;try{pilot_write_policy(['enabled'=>false]);}
  catch(RuntimeException $e){$refused=$e->getMessage()==='pilot_file_unavailable';}
  pilot_check($refused&&file_get_contents($root.'/secret.txt')==='private',
       'symlink file rejected without following or clobbering target');
  // The lock file is also attacker-controlled filesystem input. Never follow
  // a symlink when updating the shared legacy policy.
  unlink($root.'/settings.json');
  file_put_contents($root.'/settings.json','{}');
  $lockPath=$root.'/.console-policy.lock';
  if(is_file($lockPath))unlink($lockPath);
  symlink($root.'/secret.txt',$lockPath);
  $lockRejected=false;
  try{pilot_write_policy(['enabled'=>false]);}
  catch(RuntimeException $e){$lockRejected=$e->getMessage()==='pilot_policy_lock_unavailable';}
  pilot_check($lockRejected && file_get_contents($root.'/secret.txt')==='private'
    && file_get_contents($root.'/settings.json')==='{}',
    'symlinked pilot lock rejected without changing settings or secret');
  unlink($lockPath);
  pilot_check(pilot_write_policy(['enabled'=>false])===2,
    'safe policy update still works after rejecting symlink lock');
  echo "SAAS_PILOT_FLAT_POLICY_BACKWARD_COMPATIBILITY_FAIL_CLOSED_PASS\n";
} finally {
   putenv('ONTRACK_CI_PILOT_ROOT');
   foreach(glob($root.'/*')?:[] as $name)@unlink($name);
   foreach(glob($root.'/.*')?:[] as $name){if(!in_array(basename($name),['.','..']))@unlink($name);}
   @rmdir($root);
}
