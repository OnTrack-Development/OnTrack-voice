<?php
declare(strict_types=1);
/** Recoverable archive metadata; financial and call-history records are never purged here. */
function recycle_init():void {
    $mark=private_path().'/.schema.recycle-v1';
    if(is_file($mark))return;
    $pk=driver()==='mysql'?'BIGINT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $int=driver()==='mysql'?'BIGINT':'INTEGER';
    $suffix=driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    db()->exec("CREATE TABLE IF NOT EXISTS recycle_items(
       id $pk,account_id $int NOT NULL,kind VARCHAR(12) NOT NULL,
       item_id $int NOT NULL,archived_at VARCHAR(19) NOT NULL,
       restored_at VARCHAR(19) NULL,
       UNIQUE(account_id,kind,item_id)
    )".$suffix);
    db()->exec("CREATE TABLE IF NOT EXISTS recycle_settings(
       account_id $int PRIMARY KEY,retention_days INTEGER NOT NULL DEFAULT 30
    )".$suffix);
    file_put_contents($mark,now(),LOCK_EX);@chmod($mark,0600);
}
function recycle_table(string $kind):string {
    return match($kind){'site'=>'sites','agent'=>'agents',
        default=>throw new InvalidArgumentException('recycle_unsupported_entity')};
}
function recycle_archive_note(int $account,string $kind,int $item):void{
    recycle_init();recycle_table($kind);
    $row=one('SELECT id FROM recycle_items WHERE account_id=? AND kind=? AND item_id=?',
        [$account,$kind,$item]);
    if($row)execute('UPDATE recycle_items SET archived_at=?,restored_at=NULL WHERE id=?',
        [now(),$row['id']]);
    else insert('INSERT INTO recycle_items(account_id,kind,item_id,archived_at)
        VALUES(?,?,?,?)',[$account,$kind,$item,now()]);
}
function recycle_retention(int $account):int{
    recycle_init();
    return (int)(one('SELECT retention_days FROM recycle_settings WHERE account_id=?',
        [$account])['retention_days']??30);
}
function recycle_update_retention(int $account,int $days):void{
    recycle_init();
    if($days<7||$days>3650)throw new InvalidArgumentException('retention_days_out_of_range');
    if(one('SELECT account_id FROM recycle_settings WHERE account_id=?',[$account]))
        execute('UPDATE recycle_settings SET retention_days=? WHERE account_id=?',[$days,$account]);
    else insert('INSERT INTO recycle_settings(account_id,retention_days) VALUES(?,?)',[$account,$days]);
}
function recycle_restore(int $account,string $kind,int $item):bool{
    $table=recycle_table($kind);recycle_init();
    $row=one("SELECT id,status".($kind==='agent'?',site_id':'').
        " FROM $table WHERE account_id=? AND id=?",[$account,$item]);
    if(!$row||$row['status']!=='archived')return false;
    if($kind==='agent'){
        $site=one("SELECT id FROM sites WHERE id=? AND account_id=? AND status='active'",
            [(int)$row['site_id'],$account]);
        if(!$site)throw new InvalidArgumentException('restore_site_before_agent');
    }
    $status=$kind==='site'?'active':'draft';
    if(execute("UPDATE $table SET status=? WHERE id=? AND account_id=? AND status='archived'",
        [$status,$item,$account])!==1)return false;
    execute('UPDATE recycle_items SET restored_at=? WHERE account_id=? AND kind=? AND item_id=?',
        [now(),$account,$kind,$item]);
    return true;
}
function recycle_entries(int $account):array {
    recycle_init();
    $rows=[];
    foreach(['site'=>'sites','agent'=>'agents'] as $kind=>$table){
        foreach(all("SELECT t.id,t.name,r.archived_at FROM $table t LEFT JOIN recycle_items r
           ON r.account_id=t.account_id AND r.kind=? AND r.item_id=t.id
           WHERE t.account_id=? AND t.status='archived' ORDER BY t.id DESC LIMIT 100",
           [$kind,$account]) as $row)
            $rows[]=['kind'=>$kind,'id'=>(int)$row['id'],
                'name'=>(string)$row['name'],'archived_at'=>$row['archived_at']??null];
    }
    return $rows;
}
