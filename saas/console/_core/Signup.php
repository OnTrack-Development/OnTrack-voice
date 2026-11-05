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
        $reseller=one("SELECT id,name,brand_name,slug,logo_path FROM accounts WHERE kind='reseller' AND slug=? AND status='active'",[$resellerSlug]);
        if(!$reseller)throw new InvalidArgumentException('رابط تسجيل الريسيلر غير متاح');
        return ['kind'=>'customer','parent_id'=>(int)$reseller['id'],'slug'=>(string)$reseller['slug'],
            'brand'=>(string)($reseller['brand_name']?:$reseller['name']),
            'logo'=>(string)($reseller['logo_path']??'')];
    }
    return ['kind'=>$kind,'parent_id'=>(int)$platform['id'],'slug'=>'',
        'brand'=>'OnTrack Voice','logo'=>''];
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
    $config=is_file($configFile)?json_decode((string)file_get_contents($configFile),true):[];
    // Existing owner domain is the default while private email.json is not installed.
    $from=is_array($config)?($config['from']??'no-reply@ontrackegy.com'):'no-reply@ontrackegy.com';
    if(!is_string($from)||strlen($from)>190||!filter_var($from,FILTER_VALIDATE_EMAIL))return false;
    $url='https://app.ontrackegy.com/verify-email?token='.$token;
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
    $brandIcon=$scope && $scope['slug']!=='' && $scope['logo']!==''
        ?'<img src="/register-logo?reseller='.rawurlencode((string)$scope['slug']).'" alt="" style="width:56px;height:56px;object-fit:contain;border-radius:12px">'
        :'<span class="brand-icon">OT</span>';
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).'</title><link rel="stylesheet" href="/assets/css/app.css"></head><body class="login-body"><div class="login-backdrop"></div><main class="login-panel" style="max-width:540px;margin:5vh auto"><div class="login-brand">'.$brandIcon.'<strong>'.e($brand).'</strong></div><h1>'.e($title).'</h1><p>إنشاء حسابك يتطلب تأكيد البريد مرة واحدة. الباقات المدفوعة لا تتفعل تلقائيًا.</p>';
    if($warning)echo '<div class="alert danger">'.e($warning).'</div>';
    if($msg=flash('success'))echo '<div class="alert success">'.e($msg).'</div>';
    if($scope){
        echo '<form method="post" action="/register" class="stack"><input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="kind" value="'.e($scope['kind']).'"><input type="hidden" name="reseller" value="'.e($scope['slug']).'">';
        echo '<label class="field">اسمك<input name="name" required maxlength="120" autocomplete="name"></label>';
        echo '<label class="field">اسم الشركة<input name="company" required maxlength="120" autocomplete="organization"></label>';
        echo '<label class="field">البريد الإلكتروني<input name="email" type="email" maxlength="190" required autocomplete="email"></label>';
        echo '<label class="field">كلمة المرور (12 حرفًا على الأقل مع حروف وأرقام)<input name="password" type="password" minlength="12" maxlength="128" required autocomplete="new-password"></label>';
        echo '<label class="field">تأكيد كلمة المرور<input name="password_confirm" type="password" minlength="12" maxlength="128" required autocomplete="new-password"></label>';
        echo '<button class="btn primary wide" type="submit">إرسال رابط تأكيد البريد</button></form>';
    }
    echo '<p><a href="/login">عندي حساب بالفعل — تسجيل الدخول</a></p></main></body></html>';exit;
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
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تأكيد البريد | OnTrack</title><link rel="stylesheet" href="/assets/css/app.css"></head><body class="login-body"><main class="login-panel" style="max-width:520px;margin:6vh auto"><h1>تأكيد البريد الإلكتروني</h1>';
    if($error)echo '<div class="alert danger">'.e($error).'</div>';
    echo '<p>اضغط تأكيد لإنشاء الحساب بعد إثبات وصول رسالة البريد. الرابط صالح لمدة 30 دقيقة ولمرة واحدة.</p>';
    echo '<form method="post" action="/verify-email"><input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="token" value="'.e($token).'"><button type="submit" class="btn primary wide">تأكيد البريد وإنشاء حسابي</button></form></main></body></html>';exit;
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


/** Password recovery is single-use email proof, never a login MFA prompt. */
function password_reset_send_email(string $email,string $token):bool {
    $configFile=private_path().'/email.json';
    $config=is_file($configFile)?json_decode((string)file_get_contents($configFile),true):[];
    // Existing owner domain is the default while private email.json is not installed.
    $from=is_array($config)?($config['from']??'no-reply@ontrackegy.com'):'no-reply@ontrackegy.com';
    if(!is_string($from)||!filter_var($from,FILTER_VALIDATE_EMAIL))return false;
    $url='https://app.ontrackegy.com/reset-password?token='.$token;
    $body="To reset your OnTrack Voice password, use this link within 30 minutes:\n\n".$url.
        "\n\nIf you did not request a reset, ignore this email.\n";
    return mail($email,'OnTrack Voice - Reset your password',$body,
        ['From'=>$from,'Content-Type'=>'text/plain; charset=UTF-8']);
}
/** Returns no account existence information to the public endpoint. */
function password_reset_stage(string $email,?callable $send=null):void {
    $email=strtolower(trim($email));
    if(strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL))
        throw new InvalidArgumentException('البريد الإلكتروني غير صالح');
    $user=one("SELECT id,email FROM users WHERE email=? AND status='active'",[$email]);
    if(!$user)return;
    // Limit mail per account, independently of a browser's session throttling.
    $recent=one("SELECT id FROM password_reset_requests WHERE user_id=? AND created_at>? ORDER BY id DESC LIMIT 1",
        [$user['id'],gmdate('Y-m-d H:i:s',time()-60)]);
    if($recent)return;
    $token=bin2hex(random_bytes(32));
    $hash=hash('sha256',$token);
    $id=insert("INSERT INTO password_reset_requests(user_id,token_hash,expires_at,status,created_at)
        VALUES (?,?,?,'pending',?)",
        [$user['id'],$hash,gmdate('Y-m-d H:i:s',time()+1800),now()]);
    try{$sent=($send??'password_reset_send_email')($email,$token);}
    catch(Throwable){$sent=false;}
    if($sent!==true)execute("DELETE FROM password_reset_requests WHERE id=? AND status='pending'",[$id]);
}
/** Changes the password only after claiming an unexpired token atomically. */
function password_reset_confirm(string $token,string $password):int {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))
        throw new InvalidArgumentException('رابط الاسترجاع غير صالح');
    auth_password_validate($password);
    $hash=hash('sha256',$token);
    db()->beginTransaction();
    try{
        $reset=one("SELECT r.id,r.user_id FROM password_reset_requests r
            JOIN users u ON u.id=r.user_id
            WHERE r.token_hash=? AND r.status='pending' AND r.expires_at>?
              AND u.status='active'",[$hash,now()]);
        if(!$reset)throw new InvalidArgumentException('رابط الاسترجاع منتهي أو مستخدم');
        $claimed=execute("UPDATE password_reset_requests SET status='used'
            WHERE id=? AND token_hash=? AND status='pending' AND expires_at>?",
            [$reset['id'],$hash,now()]);
        if($claimed!==1)throw new InvalidArgumentException('رابط الاسترجاع مستخدم بالفعل');
        execute("UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE id=? AND status='active'",
            [hash_password($password),$reset['user_id']]);
        execute("UPDATE password_reset_requests SET status='used'
            WHERE user_id=? AND status='pending'",[$reset['user_id']]);
        db()->commit();
        return (int)$reset['user_id'];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function password_change(int $userId,string $current,string $next):int {
    auth_password_validate($next);
    $u=one("SELECT password_hash,auth_version FROM users WHERE id=? AND status='active'",[$userId]);
    if(!$u||!password_verify($current,(string)$u['password_hash']))
        throw new InvalidArgumentException('كلمة المرور الحالية غير صحيحة');
    if(password_verify($next,(string)$u['password_hash']))
        throw new InvalidArgumentException('اختر كلمة مرور جديدة مختلفة');
    $changed=execute("UPDATE users SET password_hash=?,auth_version=auth_version+1
        WHERE id=? AND auth_version=? AND status='active'",
        [hash_password($next),$userId,(int)$u['auth_version']]);
    if($changed!==1)throw new RuntimeException('تغيرت بيانات الجلسة؛ سجّل الدخول مجددًا');
    execute("UPDATE password_reset_requests SET status='used'
        WHERE user_id=? AND status='pending'",[$userId]);
    return (int)$u['auth_version']+1;
}
function auth_public_page(string $title,string $body,?string $warning=null):never {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private,no-store');
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' | OnTrack Voice</title><link rel="stylesheet" href="/assets/css/app.css"></head><body class="login-body"><div class="login-backdrop"></div><main class="login-panel" style="max-width:550px;margin:6vh auto"><div class="login-brand"><span class="brand-icon">OT</span><strong>OnTrack Voice</strong></div><h1>'.e($title).'</h1>';
    if($warning)echo '<div class="alert danger">'.e($warning).'</div>';
    if($notice=flash('success'))echo '<div class="alert success">'.e($notice).'</div>';
    echo $body.'<p><a href="/login">العودة إلى تسجيل الدخول</a></p></main></body></html>';
    exit;
}
function password_forgot_route():never {
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        require_csrf();
        $started=(int)($_SESSION['reset_started']??0);
        if(time()-$started>3600){$_SESSION['reset_started']=time();$_SESSION['reset_count']=0;}
        $count=(int)($_SESSION['reset_count']??0);
        if($count>=5)password_forgot_page('محاولات كثيرة؛ حاول مرة أخرى لاحقًا.');
        $_SESSION['reset_count']=$count+1;
        try{
            password_reset_stage((string)($_POST['email']??''));
            flash('success','لو البريد موجود عندنا، هيوصلك رابط استرجاع كلمة المرور.');
            go('/forgot-password');
        }catch(InvalidArgumentException $e){password_forgot_page($e->getMessage());}
    }
    password_forgot_page();
}
function password_forgot_page(?string $warning=null):never {
    $body='<p>اكتب البريد المرتبط بحسابك لاستلام رابط تغيير كلمة المرور. مش مطلوب كود مصادقة ثنائية.</p>'.
        '<form method="post" action="/forgot-password" class="stack">'.
        '<input type="hidden" name="csrf" value="'.e(csrf()).'">'.
        '<label class="field">البريد الإلكتروني<input name="email" type="email" maxlength="190" autocomplete="email" required></label>'.
        '<button class="btn primary wide" type="submit">إرسال رابط الاسترجاع</button></form>';
    auth_public_page('نسيت كلمة المرور',$body,$warning);
}
function password_reset_route():never {
    $token=(string)($_POST['token']??$_GET['token']??'');
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        require_csrf();
        $password=(string)($_POST['password']??'');
        if(!hash_equals($password,(string)($_POST['password_confirm']??'')))
            password_reset_page($token,'تأكيد كلمة المرور غير مطابق');
        try{
            password_reset_confirm($token,$password);
            // A previous browser session remains invalid after password version bump.
            unset($_SESSION['uid'],$_SESSION['account_id'],$_SESSION['auth_version'],$_SESSION['support']);
            session_regenerate_id(true);
            flash('success','تم تحديث كلمة المرور. سجّل الدخول بكلمة المرور الجديدة.');
            go('/login');
        }catch(InvalidArgumentException|RuntimeException $e){password_reset_page($token,$e->getMessage());}
    }
    password_reset_page($token);
}
function password_reset_page(string $token,?string $warning=null):never {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))
        auth_public_page('رابط الاسترجاع غير صالح','<p>اطلب رابطًا جديدًا من صفحة استرجاع كلمة المرور.</p><p><a href="/forgot-password">طلب رابط جديد</a></p>');
    $body='<p>حدد كلمة مرور جديدة للحساب. الرابط صالح لمدة 30 دقيقة ولمرة واحدة.</p>'.
        '<form method="post" action="/reset-password" class="stack">'.
        '<input type="hidden" name="csrf" value="'.e(csrf()).'">'.
        '<input type="hidden" name="token" value="'.e($token).'">'.
        '<label class="field">كلمة المرور الجديدة<input name="password" type="password" minlength="12" maxlength="128" autocomplete="new-password" required></label>'.
        '<label class="field">تأكيد كلمة المرور<input name="password_confirm" type="password" minlength="12" maxlength="128" autocomplete="new-password" required></label>'.
        '<button class="btn primary wide" type="submit">حفظ كلمة المرور الجديدة</button></form>';
    auth_public_page('تغيير كلمة المرور',$body,$warning);
}
function password_change_route():never {
    $user=require_login();
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        require_csrf();
        $next=(string)($_POST['new_password']??'');
        if(!hash_equals($next,(string)($_POST['new_password_confirm']??'')))
            password_change_page('تأكيد كلمة المرور غير مطابق');
        try{
            $version=password_change((int)$user['id'],(string)($_POST['current_password']??''),$next);
            $_SESSION['auth_version']=$version;
            session_regenerate_id(true);
            flash('success','تم تغيير كلمة المرور وإغلاق الجلسات القديمة.');
            go('/?tab=settings');
        }catch(InvalidArgumentException|RuntimeException $e){password_change_page($e->getMessage());}
    }
    password_change_page();
}
function password_change_page(?string $warning=null):never {
    $body='<form method="post" action="/change-password" class="stack">'.
        '<input type="hidden" name="csrf" value="'.e(csrf()).'">'.
        '<label class="field">كلمة المرور الحالية<input name="current_password" type="password" autocomplete="current-password" required></label>'.
        '<label class="field">كلمة المرور الجديدة<input name="new_password" type="password" minlength="12" maxlength="128" autocomplete="new-password" required></label>'.
        '<label class="field">تأكيد كلمة المرور<input name="new_password_confirm" type="password" minlength="12" maxlength="128" autocomplete="new-password" required></label>'.
        '<button class="btn primary wide" type="submit">تغيير كلمة المرور</button></form>';
    auth_public_page('تغيير كلمة المرور',$body,$warning);
}

/** Public image is scoped to the active reseller and a locally stored safe logo file. */
function signup_logo_route():never {
    try{$scope=signup_scope('customer',trim((string)($_GET['reseller']??'')));}
    catch(InvalidArgumentException|RuntimeException){deny(404,'الصورة غير متاحة');}
    if($scope['slug']==='')deny(404,'الصورة غير متاحة');
    $logo=(string)$scope['logo'];
    if(!$logo||$logo!==basename($logo)||
       !preg_match('/^logo-[0-9]+-[a-f0-9]{12}\.(png|jpg|webp)$/D',$logo))
        deny(404,'الصورة غير متاحة');
    $path=private_path().'/'.$logo;
    if(!is_file($path)||is_link($path))deny(404,'الصورة غير متاحة');
    $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
    header('Content-Type: '.(['png'=>'image/png','jpg'=>'image/jpeg','webp'=>'image/webp'][$ext]));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public,max-age=300');
    readfile($path);exit;
}
