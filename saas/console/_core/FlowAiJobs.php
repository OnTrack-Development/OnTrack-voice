<?php
declare(strict_types=1);
/**
 * Durable, account/user/agent-scoped Gemini Live editor proposal jobs.
 * This is a draft advisor, NOT a telephone job or an executable tool.
 * A CLI cron can run pending jobs; PHP-FPM may run a job AFTER returning HTTP.
 */
require_once __DIR__.'/FlowAiChat.php';
require_once __DIR__.'/FlowAiProfile.php';

function flow_ai_jobs_init():void {
    $mark=private_path().'/.schema.flow-ai-jobs.v1';
    if(is_file($mark))return;
    $mysql=driver()==='mysql';
    $int=$mysql?'BIGINT':'INTEGER';
    $body=$mysql?'MEDIUMTEXT':'TEXT';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS flow_ai_jobs (
        job_id CHAR(32) NOT NULL PRIMARY KEY,
        account_id $int NOT NULL,user_id $int NOT NULL,agent_id $int NOT NULL,
        kind VARCHAR(10) NOT NULL,status VARCHAR(12) NOT NULL,
        payload_json $body NOT NULL,result_json $body NULL,error_text TEXT NULL,
        attempts INTEGER NOT NULL DEFAULT 0,
        created_at VARCHAR(19) NOT NULL,started_at VARCHAR(19) NULL,
        completed_at VARCHAR(19) NULL
    )".$engine);
    if($mysql) {
        try{db()->exec('CREATE INDEX ix_flow_ai_jobs_scope ON flow_ai_jobs(account_id,user_id,agent_id,status)');}
        catch(PDOException $e){if(!str_contains($e->getMessage(),'Duplicate key name'))throw $e;}
    }else db()->exec('CREATE INDEX IF NOT EXISTS ix_flow_ai_jobs_scope ON flow_ai_jobs(account_id,user_id,agent_id,status)');
    file_put_contents($mark,now(),LOCK_EX);@chmod($mark,0600);
}
function flow_ai_job_active(int $accountId,int $userId,int $agentId):?array {
    flow_ai_jobs_init();
    $row=one("SELECT job_id,kind,status FROM flow_ai_jobs
        WHERE account_id=? AND user_id=? AND agent_id=?
          AND status IN ('pending','running') ORDER BY created_at DESC LIMIT 1",
        [$accountId,$userId,$agentId]);
    return $row?:null;
}
function flow_ai_job_enqueue(int $accountId,int $userId,int $agentId,string $kind,string $idea,array $current,array $history):string {
    flow_ai_jobs_init();
    if(!in_array($kind,['graph','profile'],true))throw new InvalidArgumentException('نوع Job غير صالح');
    if(mb_strlen(trim($idea))<3||mb_strlen($idea)>2500)throw new InvalidArgumentException('وصف التعديل غير صالح');
    if(!flow_preview_agent_for_account($accountId,$agentId))throw new InvalidArgumentException('agent_not_available');
    if(flow_ai_job_active($accountId,$userId,$agentId))
        throw new InvalidArgumentException('في طلب Live جاري لهذا الإيجنت؛ انتظر نتيجته قبل إرسال طلب جديد.');
    $payload=json_encode(['idea'=>$idea,'current'=>$current,'history'=>array_slice($history,-8)],
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if(strlen($payload)>50000)throw new InvalidArgumentException('بيانات المقترح كبيرة جدًا');
    $id=bin2hex(random_bytes(16));
    insert("INSERT INTO flow_ai_jobs(job_id,account_id,user_id,agent_id,kind,status,payload_json,created_at)
        VALUES(?,?,?,?,?,'pending',?,?)",[$id,$accountId,$userId,$agentId,$kind,$payload,now()]);
    return $id;
}
function flow_ai_job_status(string $jobId,int $accountId,int $userId,int $agentId):array {
    flow_ai_jobs_init();
    if(!preg_match('/\A[a-f0-9]{32}\z/D',$jobId))throw new InvalidArgumentException('رقم Job غير صالح');
    $row=one('SELECT status,kind,result_json,error_text FROM flow_ai_jobs
        WHERE job_id=? AND account_id=? AND user_id=? AND agent_id=?',
        [$jobId,$accountId,$userId,$agentId]);
    if(!$row)throw new InvalidArgumentException('المهمة غير موجودة في مساحة الإيجنت الحالية');
    $result=is_string($row['result_json']??null)?json_decode($row['result_json'],true):null;
    return ['ok'=>true,'status'=>$row['status'],'kind'=>$row['kind'],
        'result'=>is_array($result)?$result:null,'error'=>$row['error_text']??null];
}
function flow_ai_job_cancel_scope(int $accountId,int $userId,int $agentId):void {
    flow_ai_jobs_init();
    execute("UPDATE flow_ai_jobs SET status='cancelled',completed_at=?
        WHERE account_id=? AND user_id=? AND agent_id=? AND status IN ('pending','running')",
        [now(),$accountId,$userId,$agentId]);
}
function flow_ai_job_claim(string $id):?array {
    $count=execute("UPDATE flow_ai_jobs SET status='running',attempts=attempts+1,started_at=?
        WHERE job_id=? AND status='pending' AND attempts<2",[now(),$id]);
    return $count===1?one('SELECT * FROM flow_ai_jobs WHERE job_id=?',[$id]):null;
}
/** Jobs abandoned by a killed PHP worker are retried once, not indefinitely. */
function flow_ai_job_recover():void {
    flow_ai_jobs_init();
    $before=gmdate('Y-m-d H:i:s',time()-220);
    execute("UPDATE flow_ai_jobs SET status='pending',started_at=NULL
        WHERE status='running' AND started_at<? AND attempts<2",[$before]);
    execute("UPDATE flow_ai_jobs SET status='failed',error_text='جلسة Live انقطعت قبل اكتمال المقترح. حاول مرة أخرى.',completed_at=?
        WHERE status='running' AND started_at<? AND attempts>=2",[now(),$before]);
}
function flow_ai_job_run(string $id,?callable $liveTransport=null):array {
    flow_ai_jobs_init();
    $job=flow_ai_job_claim($id);
    if(!$job)return ['status'=>'skipped'];
    try {
        $payload=json_decode((string)$job['payload_json'],true,32,JSON_THROW_ON_ERROR);
        if(!is_array($payload)||!is_array($payload['current']??null)||
           !is_array($payload['history']??null))throw new RuntimeException('مهمة Live غير مكتملة');
        $account=(int)$job['account_id'];$user=(int)$job['user_id'];$agent=(int)$job['agent_id'];
        $idea=(string)$payload['idea'];$current=$payload['current'];
        $history=$payload['history'];
        if($job['kind']==='graph'){
            $graph=flow_ai_generate($account,$agent,$idea,$history,$current,$liveTransport);
            $explanation=flow_ai_explain_graph($graph);
            $result=['ok'=>true,'graph'=>$graph,'explanation'=>$explanation,'model'=>flow_ai_model(),
                'metrics'=>flow_ai_graph_quality($graph),'saved'=>false,'published'=>false];
        }else{
            $profile=flow_ai_profile_generate($account,$agent,$idea,$current,$history,$liveTransport);
            $result=['ok'=>true,'profile'=>$profile,'explanation'=>$profile['explanation'],
                'model'=>flow_ai_model(),'saved'=>false,'published'=>false];
        }
        db()->beginTransaction();
        try {
            $lock=driver()==='mysql'?' FOR UPDATE':'';
            $state=one('SELECT status FROM flow_ai_jobs WHERE job_id=?'.$lock,[$id]);
            if(($state['status']??null)!=='running'){
                db()->rollBack();return ['status'=>'cancelled'];
            }
            $chat=flow_ai_chat_read($account,$user,$agent);
            $messages=$chat['messages'];
            $messages[]=['role'=>'user','text'=>$idea];
            $messages[]=['role'=>'assistant','text'=>$result['explanation']];
            flow_ai_chat_write($account,$user,$agent,$messages,
                $result['graph']??$chat['pending_graph'],
                $result['profile']??$chat['pending_profile']);
            execute("UPDATE flow_ai_jobs SET status='done',result_json=?,completed_at=?
                WHERE job_id=? AND status='running'",
                [json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),now(),$id]);
            db()->commit();
        }catch(Throwable $e){
            if(db()->inTransaction())db()->rollBack();
            throw $e;
        }
        return ['status'=>'done'];
    }catch(Throwable $error){
        $message=$error instanceof RuntimeException||$error instanceof InvalidArgumentException
            ?mb_substr($error->getMessage(),0,350)
            :'تعذر إنشاء مقترح Live؛ لم تتغير المسودة.';
        execute("UPDATE flow_ai_jobs SET status='failed',error_text=?,completed_at=?
            WHERE job_id=? AND status='running'",[$message,now(),$id]);
        return ['status'=>'failed'];
    }
}
/** Finish response before doing slow Live work. CLI cron still recovers failed FPM workers. */
function flow_ai_job_after_http(string $id):void {
    if(!function_exists('fastcgi_finish_request'))return;
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    ignore_user_abort(true);
    fastcgi_finish_request();
    @set_time_limit(190);
    flow_ai_job_run($id);
}
