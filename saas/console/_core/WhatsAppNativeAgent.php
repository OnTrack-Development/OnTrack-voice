<?php
declare(strict_types=1);
require_once __DIR__.'/WhatsAppSupport.php';
require_once __DIR__.'/PublishedFlow.php';
require_once __DIR__.'/FlowAi.php';

/**
 * OnTrack's OWN WhatsApp support agent. WHMCS, the WHMCS addon and any
 * third-party CRM are NOT prerequisites. No external actions are performed.
 * Disabled per channel until an owner explicitly enables a published agent.
 */
function wa_native_snapshot(array $connection):?array {
    $agentId=(int)($connection['ai_agent_id']??0);
    if($agentId<=0)return null;
    return published_agent_flow((int)$connection['account_id'],(int)$connection['site_id'],$agentId);
}
function wa_native_toggle(int $accountId,int $connectionId,bool $enabled):void {
    $connection=wa_connection($connectionId,$accountId);
    if($enabled){
        if($connection['state']!=='verified')throw new RuntimeException('اختبر رقم Meta أولاً قبل تفعيل الرد الآلي');
        if(!wa_native_snapshot($connection))throw new RuntimeException('لازم تختار إيجنت منشور لنفس الموقع أولاً');
        // Fail closed if no platform AI provider credential is configured.
        tenant_voice_provider();
    }
    execute('UPDATE wa_connections SET ai_enabled=?,updated_at=? WHERE id=? AND account_id=?',
        [$enabled?1:0,now(),$connectionId,$accountId]);
    wa_audit($accountId,null,$enabled?'whatsapp.native.enabled':'whatsapp.native.disabled',
        'owner-controlled standalone agent');
}

/** Gemini is an OnTrack platform provider, not a WHMCS service. */
function wa_native_generate(string $instruction,array $history,?callable $transport=null):string {
    $payload=['systemInstruction'=>['parts'=>[['text'=>$instruction]]],
        'contents'=>[['role'=>'user','parts'=>[['text'=>json_encode($history,
            JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]]]],
        'generationConfig'=>['responseMimeType'=>'application/json','maxOutputTokens'=>1300]];
    if($transport){
        $result=$transport($payload);
    }else{
        $provider=tenant_voice_provider();
        $model=flow_ai_model();
        if(!function_exists('curl_init'))throw new RuntimeException('native_ai_curl_unavailable');
        $url='https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent';
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-goog-api-key: '.$provider['key']],
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>17,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        if(!is_string($raw)||$status!==200||strlen($raw)>150000)
            throw new RuntimeException('native_ai_provider_unavailable');
        $result=json_decode($raw,true);
        $result=(string)($result['candidates'][0]['content']['parts'][0]['text']??'');
    }
    if(!is_string($result)||strlen($result)>16000)
        throw new RuntimeException('native_ai_output_invalid');
    return $result;
}
function wa_native_parse(string $json):array {
    // Providers return a JSON object or a JSON-serialized text response.
    $v=json_decode(trim($json),true,20);
    if(!is_array($v)||array_is_list($v)||!in_array(($v['decision']??null),['reply','handoff','no_reply'],true))
        throw new RuntimeException('native_ai_decision_invalid');
    $reply=trim((string)($v['reply_text']??''));
    if($v['decision']==='reply'&&($reply===''||mb_strlen($reply)>4096))
        throw new RuntimeException('native_ai_reply_invalid');
    return ['decision'=>$v['decision'],'reply_text'=>$reply,'approved'=>($v['approved']??false)===true];
}
function wa_native_reviewed_draft(array $connection,array $conversation,?callable $transport=null):?string {
    $published=wa_native_snapshot($connection);
    if(!$published)throw new RuntimeException('native_agent_not_published');
    $messages=all('SELECT role,body FROM wa_messages WHERE account_id=? AND conversation_id=? ORDER BY id DESC LIMIT 12',
        [(int)$connection['account_id'],(int)$conversation['id']]);
    $history=[];
    foreach(array_reverse($messages) as $m){
        $history[]=['role'=>in_array($m['role'],['customer','staff','ai'],true)?$m['role']:'customer',
            'text'=>mb_substr((string)$m['body'],0,1500)];
    }
    $rules="أنت مساعد خدمة عملاء مستقل تابع لمنصة OnTrack Voice. رد بالمصري الطبيعي.\n".
      "استخدم تعليمات الوكيل المنشور ومعرفته فقط، ولا تعتبر رقم واتساب إثبات هوية.\n".
      "ممنوع ادعاء الاطلاع على بيانات العميل أو تنفيذ طلبات أو حجوزات أو تعديلات أو مدفوعات من غير نتيجة تكامل موثقة.\n".
      "لا تُنفذ أي إجراء خارجي. أي بيانات شخصية/فواتير أو تغيير حساس أو معلومة غير مؤكدة = handoff.\n".
      "تعليمات الوكيل: ".mb_substr((string)$published['instructions'],0,3800)."\n".
      "قاعدة المعرفة: ".mb_substr((string)$published['knowledge'],0,6000)."\n".
      'ارجع JSON فقط: {"decision":"reply|handoff|no_reply","reply_text":"..."}';
    $draft=wa_native_parse(wa_native_generate($rules,$history,$transport));
    if($draft['decision']!=='reply')return null;
    // Distinct reviewing call: no LLM draft may be dispatched merely because
    // the writer produced it. Fail closed unless the reviewer approves EXACT text.
    $review="أنت مراجع مستقل لردود OnTrack Voice. راجع الرد مقارنةً بسياق المحادثة والمعرفة. ".
      "ارفض أي ادعاء وصول إلى WHMCS أو CRM أو حجز أو حالة خدمة غير موثقة، وأي طلب بيانات حساسة. ".
      'ارجع JSON فقط: {"decision":"reply|handoff|no_reply","reply_text":"نفس النص الأصلي تمامًا","approved":true|false}. '.
      "لا تعدل النص أثناء المراجعة. أي شك = handoff.";
    $evaluation=wa_native_parse(wa_native_generate($review,
        ['history'=>$history,'draft'=>$draft['reply_text'],'knowledge'=>mb_substr((string)$published['knowledge'],0,3000)],$transport));
    if($evaluation['decision']!=='reply'||!$evaluation['approved']||
        !hash_equals($draft['reply_text'],$evaluation['reply_text']))return null;
    return $draft['reply_text'];
}
function wa_native_run_job(int $jobId,?callable $aiTransport=null,?callable $metaTransport=null):array {
    $job=one("SELECT * FROM wa_ai_jobs WHERE id=? AND status='pending'",[$jobId]);
    if(!$job)return ['status'=>'not_pending'];
    $accountId=(int)$job['account_id'];
    $conv=wa_conversation((int)$job['conversation_id'],$accountId);
    $conn=wa_connection((int)$job['connection_id'],$accountId);
    if((int)($conn['ai_enabled']??0)!==1||$conn['state']!=='verified'||
        !wa_native_snapshot($conn)||(int)$conv['generation']!==(int)$job['generation']||
        !wa_ai_allowed($conv)){
        execute("UPDATE wa_ai_jobs SET status='skipped',updated_at=? WHERE id=? AND status='pending'",[now(),$jobId]);
        return ['status'=>'skipped'];
    }
    if(execute("UPDATE wa_ai_jobs SET status='processing',attempts=attempts+1,updated_at=?
        WHERE id=? AND status='pending'",[now(),$jobId])!==1)return ['status'=>'claimed_elsewhere'];
    try{
        $text=wa_native_reviewed_draft($conn,$conv,$aiTransport);
        if($text===null){
            execute("UPDATE wa_ai_jobs SET status='skipped',updated_at=? WHERE id=?",[now(),$jobId]);
            return ['status'=>'handoff_or_no_reply'];
        }
        db()->beginTransaction();
        try{
            $sql='SELECT * FROM wa_conversations WHERE id=? AND account_id=?';
            if(driver()==='mysql')$sql.=' FOR UPDATE';
            $current=one($sql,[(int)$job['conversation_id'],$accountId]);
            $latest=wa_connection((int)$job['connection_id'],$accountId);
            if(!$current||(int)($latest['ai_enabled']??0)!==1||
                (int)$current['generation']!==(int)$job['generation']||!wa_ai_allowed($current)||
                empty($current['last_customer_at'])||
                strtotime((string)$current['last_customer_at'])<time()-86400){
                execute("UPDATE wa_ai_jobs SET status='skipped',updated_at=? WHERE id=?",[now(),$jobId]);
                db()->commit();return ['status'=>'stale_or_human'];
            }
            $sent=wa_meta_send($latest,(string)$current['contact'],$text,$metaTransport);
            $mid=insert("INSERT INTO wa_messages(account_id,conversation_id,connection_id,
                provider_message_id,role,body,delivery_status,created_at)
                VALUES (?,?,?,?,'ai',?,'accepted',?)",[
                $accountId,(int)$current['id'],(int)$latest['id'],
                $sent['provider_message_id'],$text,now()]);
            execute('UPDATE wa_conversations SET last_message_at=? WHERE id=? AND account_id=?',
                [now(),(int)$current['id'],$accountId]);
            execute("UPDATE wa_ai_jobs SET status='sent',updated_at=? WHERE id=?",[now(),$jobId]);
            wa_audit($accountId,(int)$current['id'],'whatsapp.native.sent','reviewed; Meta accepted not delivered');
            db()->commit();return ['status'=>'accepted_not_delivered','message_id'=>$mid];
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    }catch(Throwable $e){
        execute("UPDATE wa_ai_jobs SET status='failed',updated_at=? WHERE id=? AND status='processing'",
            [now(),$jobId]);
        wa_audit($accountId,(int)$job['conversation_id'],'whatsapp.native.failed',get_class($e));
        return ['status'=>'failed'];
    }
}
function wa_native_run_pending(array $connection,array $conversationIds):void {
    if(!$conversationIds||empty($connection['ai_enabled'])||$connection['state']!=='verified')return;
    foreach(array_slice(array_values(array_unique($conversationIds)),0,4) as $cid){
        $job=one("SELECT id FROM wa_ai_jobs WHERE account_id=? AND connection_id=?
            AND conversation_id=? AND status='pending' ORDER BY id DESC LIMIT 1",
            [(int)$connection['account_id'],(int)$connection['id'],(int)$cid]);
        if($job)wa_native_run_job((int)$job['id']);
    }
}
