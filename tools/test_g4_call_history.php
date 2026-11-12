<?php
declare(strict_types=1);
require dirname(__DIR__).'/saas/console/database/Seed.php';
require dirname(__DIR__).'/saas/console/_core/CallHistory.php';
seed_console();
function cg(bool $condition,string $why):void{if(!$condition)throw new RuntimeException('G4_CALL_HISTORY_'.$why);}
$id=insert("INSERT INTO calls(account_id,site_id,phone,name,duration_seconds,status,is_demo,created_at)
 VALUES (4,1,?,'تجربة تصدير',33,'ended',1,?)",['+201055500001',now()]);
try{
    $filter=call_history_filters(4,['q'=>'تجربة تصدير','status'=>'ended',
        'from'=>gmdate('Y-m-d'),'to'=>gmdate('Y-m-d'),'site_id'=>'1','sim_slot'=>'all']);
    cg(call_history_count($filter)>=1,'scoped filtered count');
    $rows=call_history_rows($filter,100,0);
    cg(count(array_filter($rows,fn($row)=>(int)$row['id']===$id))===1,'filtered result exists');
    $other=call_history_filters(5,['q'=>'تجربة تصدير','status'=>'ended']);
    cg(call_history_count($other)===0,'no cross tenant leak');
    $bad=false;try{call_history_filters(4,['from'=>'2026-10-30','to'=>'2026-10-01']);}
    catch(InvalidArgumentException){$bad=true;}
    cg($bad,'invalid range rejected');
    $bad=false;try{call_history_filters(4,['site_id'=>'9999999']);}
    catch(InvalidArgumentException){$bad=true;}
    cg($bad,'unowned site denied');
    cg(str_starts_with(call_history_cell('=HYPERLINK("https://unsafe")'),"'"),'formula injection blocked');
    cg(str_starts_with(call_history_cell('+201055500001'),"'"),'phone Excel formula blocked');
}finally{execute('DELETE FROM calls WHERE id=? AND account_id=?',[$id,4]);}
echo "G4_CALL_HISTORY_PAGINATION_FILTER_EXPORT_PASS\n";
