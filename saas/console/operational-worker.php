<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/_core/Bootstrap.php';
require __DIR__.'/_core/OperationalOutbox.php';
require __DIR__.'/_core/EmailDelivery.php';
init_schema();
$handle=@fopen(private_path().'/.operational-outbox-v68.lock','c');
if($handle===false)throw new RuntimeException('operational_worker_lock_failed');
if(!flock($handle,LOCK_EX|LOCK_NB)){fclose($handle);exit(0);}
try{
    $result=['outbox'=>operational_outbox_batch(80),
        'email'=>email_queue_batch(20)];
    operational_worker_beat('operational_email',
        (int)$result['email']['scanned'],(int)$result['email']['accepted'],
        (int)$result['email']['failed']+(int)$result['email']['needs_review']);
    echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}finally{
    flock($handle,LOCK_UN);fclose($handle);
}
