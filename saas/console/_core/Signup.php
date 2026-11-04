<?php
declare(strict_types=1);
/** Tenant-scoped web registration. No user, membership or plan before email proof. */
function signup_scope(string $kind='customer',?string $resellerSlug=null):array {
    if(!in_array($kind,['customer','reseller'],true))throw new InvalidArgumentException('نوع التسجيل غير صالح');
    $platform=one("SELECT id,name FROM accounts WHERE kind='platform' AND status='active' ORDER BY id LIMIT 1");
    if(!$platform)throw new RuntimeException('تسجيل الحسابات غير متاح حاليًا');
    if($resellerSlug!==null && $resellerSlug!==''){
        if($kind!=='customer'||!preg_match('/^[a-zA-Z0-9-]{1,100}$/D',$resellerSlug))
            throw new InvalidArgumentException('رابط الريسيلر غير صالح');
        $reseller=one("SELECT id,name,brand_name,slug FROM accounts WHERE kind='reseller' AND slug=? AND status='active'",[$resellerSlug]);
        if(!$reseller)throw new InvalidArgumentException('رابط تسجيل الريسيلر غير متاح');
        return ['kind'=>'customer','parent_id'=>(int)$reseller['id'],'slug'=>(string)$reseller['slug'],
            'brand'=>(string)($reseller['brand_name']?:$reseller['name'])];
    }
    return ['kind'=>$kind,'parent_id'=>(int)$platform['id'],'slug'=>'',
        'brand'=>'OnTrack Voice'];
}
function auth_password_validate(string $password):void {
    if(strlen($password)<12||strlen($password)>128
       ||!preg_match('/[a-zA-Z]/',$password)||!preg_match('/[0-9]/',$password))
        throw new InvalidArgumentException('كلمة المرور تتطلب 12 حرفًا على الأقل مع حروف وأرقام');
}

function signup_validate(string $name,string $company,string $email,string $password):array {
    $name=trim($name);$company=trim($company);$email=strtolower(trim($email));
    if($name===''||mb_strlen($name)>120||$company===''||mb_strlen($company)>120)
        throw new InvalidArgumentException('الاسم واسم الشركة مطلوبان (120 حرفًا كحد أقصى)');
    if(strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL))
        throw new InvalidArgumentException('البريد الإلكتروني غير صالح');
    auth_password_validate($password);
    return [$name,$company,$email];
}
function signup_send_email(string $email,string $token):bool {
    // Email sender lives in a private config, not a public repo or query parameter.
    $configFile=private_path().'/email.json';
    if(!is_file($configFile))return false;
    $config=json_decode((string)file_get_contents($configFile),true);
    $from=is_array($config)?($config['from']??null):null;
    if(!is_string($from)||strlen($from)>190||!filter_var($from,FILTER_VALIDATE_EMAIL))return false;
    $url='https://app.ontrackegy.com/saas/console/verify-email?token='.$token;
    $body="Confirm your OnTrack Voice account using this secure link within 30 minutes:\n\n".$url."\n\nIf you did not request an account, ignore this email.\n";
    return mail($email,'OnTrack Voice - Verify your email',$body,
        ['From'=>$from,'Content-Type'=>'text/plain; charset=UTF-8']);
}
/** Optional in-process mail transport is only for isolated regression tests. */
function signup_stage(string $name,string $company,string $email,string $password,?callable $send=null,
    string $kind='customer',?string $resellerSlug=null):bool {
    [$name,$company,$email]=signup_validate($name,$company,$email,$password);
    $scope=signup_scope($kind,$resellerSlug);
    if(one('SELECT id FROM users WHERE email=?',[$email]))
        return false; // no account enumeration in the public response
    $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
    $expires=gmdate('Y-m-d H:i:s',time()+1800);
    $createdAt=now();
    $row=one('SELECT id,status FROM signup_requests WHERE email=?',[$email]);
    if($row){
        execute("UPDATE signup_requests SET name=?,company_name=?,password_hash=?,token_hash=?,
            registration_kind=?,parent_account_id=?,status='pending',expires_at=?,created_at=? WHERE id=?",
            [$name,$company,hash_password($password),$hash,$scope['kind'],$scope['parent_id'],$expires,$createdAt,$row['id']]);
    }else{
        insert("INSERT INTO signup_requests(email,name,company_name,password_hash,token_hash,registration_kind,parent_account_id,status,expires_at,created_at)
            VALUES (?,?,?,?,?,?,?,'pending',?,?)",
            [$email,$name,$company,hash_password($password),$hash,$scope['kind'],$scope['parent_id'],$expires,$createdAt]);
    }
    try{$sent=($send??'signup_send_email')($email,$token);}
    catch(Throwable $e){$sent=false;}
    if($sent===true)return true;
    // Never leave an apparently verifiable account request if mail failed.
    execute("DELETE FROM signup_requests WHERE email=? AND token_hash=? AND status='pending'",[$email,$hash]);
    throw new RuntimeException('تعذر إرسال رسالة التحقق؛ راجع إعدادات البريد');
}
/** POST-only confirmation, rejects scans/replays, never claims existing identity by email. */
function signup_confirm(string $token):int {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))
        throw new InvalidArgumentException('رابط التحقق غير صحيح');
    $hash=hash('sha256',$token);
    db()->beginTransaction();
    try{
        $request=one("SELECT * FROM signup_requests WHERE token_hash=? AND status='pending' AND expires_at>?",
            [$hash,now()]);
        if(!$request)throw new InvalidArgumentException('رابط التحقق منتهي أو مستخدم');
        if(one('SELECT id FROM users WHERE email=?',[$request['email']]))
            throw new InvalidArgumentException('البريد مستخدم بالفعل');
        $updated=execute("UPDATE signup_requests SET status='verified',token_hash=NULL
            WHERE id=? AND token_hash=? AND status='pending' AND expires_at>?",
            [$request['id'],$hash,now()]);
        if($updated!==1)throw new RuntimeException('signup_claim_race');
        // Revalidate the owner tenant at activation, not just at public form display.
        $kind=(string)($request['registration_kind']??'customer');
        $parentId=(int)($request['parent_account_id']??0);
        if($parentId===0){$scope=signup_scope('customer');$parentId=$scope['parent_id'];}
        $parent=account($parentId);
        if(!$parent||$parent['status']!=='active'||
           ($kind==='reseller' && $parent['kind']!=='platform')||
           ($kind==='customer' && !in_array($parent['kind'],['platform','reseller'],true)))
            throw new InvalidArgumentException('الجهة المسؤولة عن التسجيل غير متاحة');
        $accountId=insert("INSERT INTO accounts(kind,parent_id,name,slug,status,created_at)
            VALUES (?,?,?,?,'active',?)",
            [$kind,$parentId,$request['company_name'],'acc-'.bin2hex(random_bytes(9)),now()]);
        $userId=insert("INSERT INTO users(name,email,password_hash,status,created_at)
            VALUES (?,?,?,'active',?)",
            [$request['name'],$request['email'],$request['password_hash'],now()]);
        insert('INSERT INTO memberships(user_id,account_id,role,created_at) VALUES (?,? ,?,?)',
            [$userId,$accountId,'owner',now()]);
        // No trial or subscription is automatically granted. Plans require owner checkout or admin grant.
        db()->commit();
        return $accountId;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function signup_page(?string $warning=null):never {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private,no-store');
    $kind=(string)($_POST['kind']??$_GET['type']??'customer');
    $slug=trim((string)($_POST['reseller']??$_GET['reseller']??''));
    try{$scope=signup_scope($kind,$slug);}
    catch(InvalidArgumentException|RuntimeException $e){$scope=null;$warning=$e->getMessage();}
    $brand=$scope['brand']??'OnTrack Voice';
    $title=$kind==='reseller'?'إنشاء حساب ريسيلر':'إنشاء حساب شركة';
    if($scope && $scope['slug']!=='')$title='سجّل شركتك لدى '.(string)$scope['brand'];
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).'</title><link rel="stylesheet" href="/saas/console/assets/css/app.css"></head><body class="login-body"><div class="login-backdrop"></div><main class="login-panel" style="max-width:540px;margin:5vh auto"><div class="login-brand"><span class="brand-icon">OT</span><strong>'.e($brand).'</strong></div><h1>'.e($title).'</h1><p>إنشاء حسابك يتطلب تأكيد البريد مرة واحدة. الباقات المدفوعة لا تتفعل تلقائيًا.</p>';
    if($warning)echo '<div class="alert danger">'.e($warning).'</div>';
    if($msg=flash('success'))echo '<div class="alert success">'.e($msg).'</div>';
    if($scope){
        echo '<form method="post" action="/saas/console/register" class="stack"><input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="kind" value="'.e($scope['kind']).'"><input type="hidden" name="reseller" value="'.e($scope['slug']).'">';
        echo '<label class="field">اسمك<input name="name" required maxlength="120" autocomplete="name"></label>';
        echo '<label class="field">اسم الشركة<input name="company" required maxlength="120" autocomplete="organization"></label>';
        echo '<label class="field">البريد الإلكتروني<input name="email" type="email" maxlength="190" required autocomplete="email"></label>';
        echo '<label class="field">كلمة المرور (12 حرفًا على الأقل مع حروف وأرقام)<input name="password" type="password" minlength="12" maxlength="128" required autocomplete="new-password"></label>';
        echo '<label class="field">تأكيد كلمة المرور<input name="password_confirm" type="password" minlength="12" maxlength="128" required autocomplete="new-password"></label>';
        echo '<button class="btn primary wide" type="submit">إرسال رابط تأكيد البريد</button></form>';
    }
    echo '<p><a href="/saas/console/login">عندي حساب بالفعل — تسجيل الدخول</a></p></main></body></html>';exit;
}
function signup_route():never {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')signup_page();
    require_csrf();
    $count=(int)($_SESSION['signup_count']??0);
    $started=(int)($_SESSION['signup_started']??0);
    if(time()-$started>3600){$count=0;$_SESSION['signup_started']=time();}
    if($count>=5)signup_page('وصلت للحد الأقصى من محاولات التسجيل. حاول لاحقًا.');
    $_SESSION['signup_count']=$count+1;
    try{
        $password=(string)($_POST['password']??'');
        if(!hash_equals($password,(string)($_POST['password_confirm']??'')))
            throw new InvalidArgumentException('تأكيد كلمة المرور غير مطابق');
        signup_stage((string)($_POST['name']??''),(string)($_POST['company']??''),
            (string)($_POST['email']??''),$password,null,
            (string)($_POST['kind']??'customer'),(string)($_POST['reseller']??''));
        flash('success','إذا كان البريد جديدًا ومتاحًا، ستصلك رسالة لتأكيده.');
        $type=(string)($_POST['kind']??'customer');
        $reseller=(string)($_POST['reseller']??'');
        $qs=$reseller!==''?'?reseller='.rawurlencode($reseller):($type==='reseller'?'?type=reseller':'');
        go('/register'.$qs);
    }catch(InvalidArgumentException|RuntimeException $e){signup_page($e->getMessage());}
}
function signup_verify_page(?string $error=null):never {
    header('Content-Type: text/html; charset=utf-8');header('Cache-Control: private,no-store');
    $token=(string)($_POST['token']??$_GET['token']??'');
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تأكيد البريد | OnTrack</title><link rel="stylesheet" href="/saas/console/assets/css/app.css"></head><body class="login-body"><main class="login-panel" style="max-width:520px;margin:6vh auto"><h1>تأكيد البريد الإلكتروني</h1>';
    if($error)echo '<div class="alert danger">'.e($error).'</div>';
    echo '<p>اضغط تأكيد لإنشاء الحساب بعد إثبات وصول رسالة البريد. الرابط صالح لمدة 30 دقيقة ولمرة واحدة.</p>';
    echo '<form method="post" action="/saas/console/verify-email"><input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="token" value="'.e($token).'"><button type="submit" class="btn primary wide">تأكيد البريد وإنشاء حسابي</button></form></main></body></html>';exit;
}
function signup_verify_route():never {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')signup_verify_page();
    require_csrf();
    try{
        signup_confirm((string)($_POST['token']??''));
        flash('success','تم تأكيد البريد وإنشاء حساب شركتك. سجّل الدخول ببريدك وكلمة المرور.');
        go('/login');
    }catch(InvalidArgumentException|RuntimeException $e){signup_verify_page($e->getMessage());}
}
