<?php
declare(strict_types=1);
/** Scoped history and spreadsheet-safe export. Never includes audio/complete transcript. */
function call_history_filters(int $accountId,array $input):array {
    if($accountId<=0)throw new InvalidArgumentException('invalid_account');
    $q=trim((string)($input['q']??''));
    if(mb_strlen($q)>80)throw new InvalidArgumentException('invalid_search');
    $status=(string)($input['status']??'all');
    $valid=['all','active','ended','ringing','answered','missed','failed','completed','busy','no_answer'];
    if(!in_array($status,$valid,true))throw new InvalidArgumentException('invalid_status');
    $sim=$input['sim_slot']??'all';
    if(!in_array((string)$sim,['all','0','1'],true))throw new InvalidArgumentException('invalid_sim');
    $site=(string)($input['site_id']??'0');
    if(!preg_match('/^(0|[1-9][0-9]{0,9})$/D',$site))
        throw new InvalidArgumentException('invalid_site');
    if($site!=='0' && !one('SELECT id FROM sites WHERE id=? AND account_id=?',
        [(int)$site,$accountId]))throw new InvalidArgumentException('site_not_owned');
    $start=(string)($input['from']??'');
    $end=(string)($input['to']??'');
    foreach([$start,$end] as $date)
        if($date!=='' && (!preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',$date)
          || date('Y-m-d',strtotime($date)?:0)!==$date))
            throw new InvalidArgumentException('invalid_history_date');
    if($start!==''&&$end!==''&&$start>$end)
        throw new InvalidArgumentException('invalid_history_range');
    $clauses=['c.account_id=?'];$params=[$accountId];
    if($q!==''){
        $term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';
        $clauses[]="(c.name LIKE ? ESCAPE '!' OR c.phone LIKE ? ESCAPE '!' OR c.external_call_id LIKE ? ESCAPE '!')";
        array_push($params,$term,$term,$term);
    }
    if($status!=='all'){$clauses[]='c.status=?';$params[]=$status;}
    if((string)$sim!=='all'){$clauses[]='c.sim_slot=?';$params[]=(int)$sim;}
    if($site!=='0'){$clauses[]='c.site_id=?';$params[]=(int)$site;}
    if($start!==''){$clauses[]='c.created_at>=?';$params[]=$start.' 00:00:00';}
    if($end!==''){$clauses[]='c.created_at<?';$params[]=gmdate('Y-m-d H:i:s',strtotime($end.' 00:00:00 UTC')+86400);}
    return ['where'=>implode(' AND ',$clauses),'params'=>$params,
        'q'=>$q,'status'=>$status,'sim'=>(string)$sim,'site'=>$site,'from'=>$start,'to'=>$end];
}
function call_history_rows(array $filter,int $limit=100,int $offset=0):array {
    require_once __DIR__.'/HumanEscalations.php';
    he_init();
    if($limit<1||$limit>5000||$offset<0||$offset>1000000)
        throw new InvalidArgumentException('history_page_invalid');
    return all("SELECT c.id,c.external_call_id,c.name,c.phone,c.status,c.duration_seconds,
        c.is_demo,c.device_binding_id,c.sim_slot,c.site_id,c.created_at,
        r.summary,r.customer_notes,r.outcome,r.handoff_status,r.handoff_reason,
        r.source AS report_source,r.updated_at AS report_updated_at,
        h.status AS manager_notification_status,h.result AS manager_notification_result,
        h.due_epoch AS manager_notification_due,co.name AS manager_notification_name
        FROM calls c LEFT JOIN tenant_call_reports r
        ON r.account_id=c.account_id AND r.site_id=c.site_id
        AND r.device_binding_id=c.device_binding_id AND r.call_id=c.external_call_id
        LEFT JOIN human_escalation_jobs h ON h.account_id=c.account_id
        AND h.device_binding_id=c.device_binding_id AND h.call_id=c.external_call_id
        LEFT JOIN human_escalation_contacts co ON co.id=h.contact_id AND co.account_id=c.account_id
        WHERE ".$filter['where']." ORDER BY c.id DESC LIMIT ".(int)$limit." OFFSET ".(int)$offset,
        $filter['params']);
}
function call_history_count(array $filter):int{
    return (int)(one("SELECT COUNT(*) n FROM calls c WHERE ".$filter['where'],$filter['params'])['n']??0);
}
function call_history_cell(mixed $value):string{
    $v=(string)($value??'');
    // Prevent spreadsheet-formula execution, including phone numbers starting +.
    if(preg_match('/^[\\x00-\\x20]*[=+@\\-]/u',$v))$v="'".$v;
    return str_replace(["\0","\r"],['', ' '],$v);
}
function call_history_csv(int $accountId,array $request):void {
    $filter=call_history_filters($accountId,$request);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ontrack-calls-'.gmdate('Ymd-His').'.csv"');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    $out=fopen('php://output','wb');
    if(!$out)throw new RuntimeException('csv_unavailable');
    fwrite($out,"\xEF\xBB\xBF");
    fputcsv($out,['Call ID','Name','Phone','Status','Duration seconds','SIM','Site ID',
        'Started UTC','Outcome','Summary','Customer Notes','Handoff Status','Handoff Reason',
        'Manager Notification Status','Manager Name']);
    // Strict bound and pagination; no unbounded memory or cross-tenant export.
    $written=0;
    for($offset=0;$offset<5000;$offset+=200){
        $batch=call_history_rows($filter,min(200,5000-$offset),$offset);
        foreach($batch as $row){
            $cells=[$row['external_call_id'],$row['name'],$row['phone'],$row['status'],
                $row['duration_seconds'],(int)$row['sim_slot']+1,$row['site_id'],
                $row['created_at'],$row['outcome'],$row['summary'],$row['customer_notes'],
                $row['handoff_status'],$row['handoff_reason'],
                $row['manager_notification_status'],$row['manager_notification_name']];
            fputcsv($out,array_map('call_history_cell',$cells));
            $written++;
        }
        if(count($batch)<200)break;
    }
    fclose($out);
}
