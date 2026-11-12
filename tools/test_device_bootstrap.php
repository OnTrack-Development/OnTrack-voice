<?php
declare(strict_types=1);
/* Runtime regression: PHP syntax checking cannot detect duplicate include
 * function declarations. Run the entry point with no auth/DB/side effects.
 */
$entry=realpath(__DIR__.'/../saas/console/device/v1/index.php');
if(!$entry)throw new RuntimeException('device_entry_not_found');
$command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($entry);
$p=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname($entry));
if(!is_resource($p))throw new RuntimeException('device_bootstrap_spawn_failed');
fclose($pipes[0]);
$stdout=(string)stream_get_contents($pipes[1]);
$stderr=(string)stream_get_contents($pipes[2]);
fclose($pipes[1]);fclose($pipes[2]);
$status=proc_close($p);
$parsed=json_decode($stdout,true);
if($status!==0||!is_array($parsed)||($parsed['error']??'')!=='method_not_allowed'
   ||stripos($stderr,'Fatal error')!==false||$stderr!=='')
   throw new RuntimeException('DEVICE_BOOTSTRAP_FAIL '.json_encode([
    'exit'=>$status,'output'=>mb_substr($stdout,0,250),
    'stderr'=>mb_substr($stderr,0,500)
   ],JSON_UNESCAPED_UNICODE));
echo "DEVICE_V1_BOOTSTRAP_NO_REDECLARE_PASS\n";
