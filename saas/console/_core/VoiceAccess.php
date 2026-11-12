<?php
declare(strict_types=1);

/**
 * Caller-ID is a hint, not authentication. Only an owner-enrolled number and a
 * fresh, privately collected PIN can unlock voice access to tenant data.
 * Do not send PINs, hashes, verification attempts or raw secrets to an LLM.
 */
function voice_phone_normalize(string $raw):string {
    $phone=preg_replace('/[\s().-]+/u','',trim($raw));
    if(!is_string($phone)||!preg_match('/^\+[1-9][0-9]{7,14}$/D',$phone))
        throw new InvalidArgumentException('أدخل رقمًا دوليًا بصيغة +201xxxxxxxxx');
    return $phone;
}
function voice_role_scopes(string $role):array {
    return match($role) {
        'owner','admin'=>['general','tickets','bookings','finance','websites','operations'],
        'manager'=>['general','tickets','bookings','finance','websites','operations'],
        'operator','reception'=>['general','bookings','tickets'],
        'finance'=>['general','finance'],
        'support'=>['general','tickets'],
        'viewer'=>['general'],
        default=>[]
    };
}
function voice_scopes_subset(string $role,array $requested):array {
    $allowed=voice_role_scopes($role);
    $requested=array_values(array_unique(array_filter($requested,'is_string')));
    if($requested===[]||array_diff($requested,$allowed))
        throw new InvalidArgumentException('صلاحيات الصوت المطلوبة غير مسموحة للدور');
    return $requested;
}
function voice_staff_enroll(int $accountId,int $userId,string $phone,string $pin,array $requested):int {
    $phone=voice_phone_normalize($phone);
    if(!preg_match('/^[0-9]{6,10}$/D',$pin))
        throw new InvalidArgumentException('رمز تحقق خاص من 6 إلى 10 أرقام مطلوب');
    $membership=one("SELECT m.role FROM memberships m
         JOIN users u ON u.id=m.user_id AND u.status='active'
         JOIN accounts a ON a.id=m.account_id AND a.status='active'
         WHERE m.user_id=? AND m.account_id=?",[$userId,$accountId]);
    if(!$membership)throw new InvalidArgumentException('الموظف ليس عضوًا نشطًا في الحساب');
    $scopes=voice_scopes_subset((string)$membership['role'],$requested);
    $collision=one('SELECT user_id FROM voice_staff_numbers WHERE account_id=? AND phone_e164=?',[$accountId,$phone]);
    if($collision && (int)$collision['user_id']!==$userId)
        throw new InvalidArgumentException('الرقم مرتبط بموظف آخر في نفس الحساب');
    $hash=password_hash($pin,PASSWORD_DEFAULT);
    $scopesJson=json_encode($scopes,JSON_THROW_ON_ERROR);
    $existing=one('SELECT id FROM voice_staff_numbers WHERE account_id=? AND user_id=? AND phone_e164=?',[$accountId,$userId,$phone]);
    if($existing){
        execute("UPDATE voice_staff_numbers SET pin_hash=?,scopes_json=?,status='active',
            failed_attempts=0,locked_until=NULL,updated_at=? WHERE id=? AND account_id=?",
            [$hash,$scopesJson,now(),$existing['id'],$accountId]);
        return (int)$existing['id'];
    }
    return insert("INSERT INTO voice_staff_numbers
        (account_id,user_id,phone_e164,pin_hash,scopes_json,status,failed_attempts,created_at,updated_at)
        VALUES (?,?,?,?,?,'active',0,?,?)",
        [$accountId,$userId,$phone,$hash,$scopesJson,now(),now()]);
}
function voice_staff_revoke(int $accountId,int $numberId):bool {
    return execute("UPDATE voice_staff_numbers SET status='revoked',updated_at=?
        WHERE account_id=? AND id=? AND status='active'",[now(),$accountId,$numberId])===1;
}
/**
 * Called only from a trusted SIM/call session after DTMF PIN collection.
 * The voice agent itself must NEVER be given the PIN to interpret.
 */
function voice_staff_authenticate(int $accountId,string $phone,string $pin):?array {
    $phone=voice_phone_normalize($phone);
    if(!preg_match('/^[0-9]{6,10}$/D',$pin))return null;
    $row=one("SELECT v.*,m.role,u.name FROM voice_staff_numbers v
        JOIN memberships m ON m.account_id=v.account_id AND m.user_id=v.user_id
        JOIN users u ON u.id=v.user_id AND u.status='active'
        JOIN accounts a ON a.id=v.account_id AND a.status='active'
        WHERE v.account_id=? AND v.phone_e164=? AND v.status='active'",[$accountId,$phone]);
    if(!$row || ($row['locked_until']!==null && (string)$row['locked_until']>now()))return null;
    if(!password_verify($pin,(string)$row['pin_hash'])){
        $attempts=(int)$row['failed_attempts']+1;
        $lock=$attempts>=5?gmdate('Y-m-d H:i:s',time()+900):null;
        execute("UPDATE voice_staff_numbers SET failed_attempts=?,locked_until=?,updated_at=? WHERE id=?",
            [$attempts,$lock,now(),$row['id']]);
        return null;
    }
    execute('UPDATE voice_staff_numbers SET failed_attempts=0,locked_until=NULL,updated_at=? WHERE id=?',
        [now(),$row['id']]);
    $stored=json_decode((string)$row['scopes_json'],true);
    $scopes=array_values(array_intersect(is_array($stored)?$stored:[],voice_role_scopes((string)$row['role'])));
    return ['account_id'=>$accountId,'user_id'=>(int)$row['user_id'],
        'number_id'=>(int)$row['id'],
        'role'=>(string)$row['role'],'name'=>(string)$row['name'],'scopes'=>$scopes];
}
/** Consent to read facts is category-specific and cannot be widened by a prompt. */
function voice_visible_facts(array $principal,int $limit=60):array {
    $accountId=(int)($principal['account_id']??0);
    $userId=(int)($principal['user_id']??0);
    $numberId=(int)($principal['number_id']??0);
    // A successful PIN check is not a permanent authorization grant. Recheck
    // the exact enrolled number, user, membership, tenant and role per read.
    if($accountId<1||$userId<1||$numberId<1)return [];
    $live=one("SELECT v.scopes_json,m.role FROM voice_staff_numbers v
        JOIN memberships m ON m.account_id=v.account_id AND m.user_id=v.user_id
        JOIN users u ON u.id=v.user_id AND u.status='active'
        JOIN accounts a ON a.id=v.account_id AND a.status='active'
        WHERE v.id=? AND v.account_id=? AND v.user_id=? AND v.status='active'",
        [$numberId,$accountId,$userId]);
    if(!$live)return [];
    $stored=json_decode((string)$live['scopes_json'],true);
    if(!is_array($stored))return [];
    $scopes=array_values(array_unique(array_intersect(
        array_filter((array)($principal['scopes']??[]),'is_string'),$stored,
        voice_role_scopes((string)$live['role']),
        voice_role_scopes((string)($principal['role']??'')))));
    if($scopes===[])return [];
    $placeholders=implode(',',array_fill(0,count($scopes),'?'));
    $args=array_merge([$accountId],$scopes);
    $limit=max(1,min($limit,60));
    return all("SELECT f.category,f.fact_key,f.fact_value,f.updated_at,i.name integration_name
        FROM integration_facts f JOIN integrations i ON i.id=f.integration_id
        WHERE f.account_id=? AND i.account_id=f.account_id
          AND f.category IN ($placeholders) AND i.status='active'
        ORDER BY f.updated_at DESC LIMIT $limit",$args);
}
/** Authenticated website sessions use current membership, never a phone PIN grant. */
function voice_visible_facts_for_member(int $accountId,int $userId,int $limit=60):array {
    $membership=one("SELECT m.role FROM memberships m
        JOIN users u ON u.id=m.user_id AND u.status='active'
        JOIN accounts a ON a.id=m.account_id AND a.status='active'
        WHERE m.account_id=? AND m.user_id=?",[$accountId,$userId]);
    if(!$membership)return [];
    $scopes=voice_role_scopes((string)$membership['role']);
    if($scopes===[])return [];
    $placeholders=implode(',',array_fill(0,count($scopes),'?'));
    $limit=max(1,min($limit,60));
    return all("SELECT f.category,f.fact_key,f.fact_value,f.updated_at,i.name integration_name
        FROM integration_facts f JOIN integrations i ON i.id=f.integration_id
        WHERE f.account_id=? AND i.account_id=f.account_id
          AND f.category IN ($placeholders) AND i.status='active'
        ORDER BY f.updated_at DESC LIMIT $limit",array_merge([$accountId],$scopes));
}
/** External systems send signed, curated aggregate facts. No arbitrary remote URLs. */
function voice_ingest_facts(array $integration,array $payload):int {
    $accountId=(int)($integration['account_id']??0);
    $integrationId=(int)($integration['id']??0);
    if($accountId<1||$integrationId<1||!one("SELECT i.id FROM integrations i
        JOIN accounts a ON a.id=i.account_id AND a.status='active'
        WHERE i.id=? AND i.account_id=? AND i.status='active'",
        [$integrationId,$accountId]))
        throw new InvalidArgumentException('integration_inactive_or_unverified');
    $category=(string)($payload['category']??'');
    if(!in_array($category,['general','tickets','bookings','finance','websites','operations'],true))
        throw new InvalidArgumentException('invalid_knowledge_category');
    $facts=$payload['facts']??null;
    if(!is_array($facts)||!array_is_list($facts)||count($facts)<1||count($facts)>30)
        throw new InvalidArgumentException('invalid_knowledge_facts');
    // Validate the complete snapshot before the first write. Duplicate keys are
    // ambiguous and must not silently become last-write-wins.
    $validated=[];
    foreach($facts as $fact){
        if(!is_array($fact))throw new InvalidArgumentException('invalid_knowledge_entry');
        $key=(string)($fact['key']??'');
        $value=$fact['value']??null;
        if(!preg_match('/^[A-Za-z][A-Za-z0-9_.-]{1,79}$/D',$key)||
           !(is_string($value)||is_int($value)||is_float($value)||is_bool($value))||
           mb_strlen((string)$value)>500)
            throw new InvalidArgumentException('invalid_knowledge_value');
        if(array_key_exists($key,$validated))
            throw new InvalidArgumentException('duplicate_knowledge_key');
        $validated[$key]=(string)$value;
    }
    $pdo=db();
    $ownsTransaction=!$pdo->inTransaction();
    $savepoint=$ownsTransaction?null:'voice_facts_'.bin2hex(random_bytes(4));
    if($ownsTransaction)$pdo->beginTransaction();
    else $pdo->exec('SAVEPOINT '.$savepoint);
    try {
        foreach($validated as $key=>$value){
            $previous=one('SELECT id FROM integration_facts
                WHERE account_id=? AND integration_id=? AND category=? AND fact_key=?',
                [$accountId,$integrationId,$category,$key]);
            if($previous){
                execute('UPDATE integration_facts SET fact_value=?,updated_at=?
                    WHERE id=? AND account_id=? AND integration_id=?',
                    [$value,now(),$previous['id'],$accountId,$integrationId]);
            }else{
                insert('INSERT INTO integration_facts
                    (account_id,integration_id,category,fact_key,fact_value,updated_at)
                    VALUES (?,?,?,?,?,?)',
                    [$accountId,$integrationId,$category,$key,$value,now()]);
            }
        }
        if($ownsTransaction)$pdo->commit();
        else $pdo->exec('RELEASE SAVEPOINT '.$savepoint);
    }catch(Throwable $error){
        if($ownsTransaction){
            if($pdo->inTransaction())$pdo->rollBack();
        }else{
            $pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint);
            $pdo->exec('RELEASE SAVEPOINT '.$savepoint);
        }
        throw $error;
    }
    return count($validated);
}
/** A separate approved action runner is required; the caller's words never execute SQL/HTTP. */
function voice_action_ready(array $principal,string $action):bool {
    return false;
}
