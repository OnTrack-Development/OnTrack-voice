<?php
declare(strict_types=1);
/** Exactly one manually reviewed payment receipt can be claimed by one order. */
function manual_payment_init():void{
    $mark=private_path().'/.schema.manual-payment-receipts.v1';
    if(is_file($mark))return;
    $int=driver()==='mysql'?'BIGINT':'INTEGER';
    $engine=driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    db()->exec("CREATE TABLE IF NOT EXISTS manual_payment_receipts(
       payment_reference VARCHAR(160) PRIMARY KEY,
       order_id $int NOT NULL,account_id $int NOT NULL,
       actor_user_id $int NOT NULL,claimed_at VARCHAR(19) NOT NULL
    )".$engine);
    file_put_contents($mark,now(),LOCK_EX);@chmod($mark,0600);
}
function manual_payment_claim(int $order,int $account,int $actor,string $reference):void{
    if($order<=0||$account<=0||$actor<=0||
       !preg_match('/^[A-Za-z0-9_.:-]{8,160}$/D',$reference))
        throw new InvalidArgumentException('manual_receipt_invalid');
    manual_payment_init();
    $used=one('SELECT order_id FROM manual_payment_receipts WHERE payment_reference=?',[$reference]);
    if($used)throw new InvalidArgumentException('manual_receipt_already_claimed');
    try{
        insert('INSERT INTO manual_payment_receipts(payment_reference,order_id,account_id,actor_user_id,claimed_at)
            VALUES(?,?,?,?,?)',[$reference,$order,$account,$actor,now()]);
    }catch(PDOException $e){
        if(one('SELECT order_id FROM manual_payment_receipts WHERE payment_reference=?',[$reference]))
            throw new InvalidArgumentException('manual_receipt_already_claimed');
        throw $e;
    }
}
