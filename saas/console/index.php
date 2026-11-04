<?php
declare(strict_types=1);
require __DIR__.'/_core/Bootstrap.php';
require __DIR__.'/_core/Signup.php';
require __DIR__.'/_core/ExternalSso.php';
require __DIR__.'/_core/Actions.php';
require __DIR__.'/_core/Views.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(),microphone=(),geolocation=()');
startsession();
try {init_schema();} catch(Throwable $e){
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<html lang="ar" dir="rtl"><body style="font-family:system-ui;padding:48px"><h2>تهيئة قاعدة بيانات لوحة OnTrack</h2><p>تحتاج قاعدة بيانات MySQL أو SQLite مفعلة على السيرفر.</p><code>'.e($e->getMessage()).'</code></body></html>';
    exit;
}
$path=route();
// Source applications should POST the signed assertion as a hidden form field,
// avoiding bearer credentials in URLs and web-server query logs. GET is accepted
// only as a compatibility fallback and is immediately redirected to a clean URL.
if($path==='/sso'){
    header('Cache-Control: no-store');
    if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','POST'],true))deny(405);
    try {
        $ticket=(string)($_POST['ticket']??$_GET['ticket']??'');
        $verified=verify_external_sso_ticket($ticket);
        external_sso_preview($verified);
        $_SESSION['sso_pending']=$ticket;
        session_regenerate_id(true);
        go('/sso/confirm');
    }catch(Throwable $e){
        http_response_code(400);
        echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>OnTrack SSO</title><body style="font-family:system-ui;padding:40px"><h2>تعذر التحقق من تسجيل الدخول الخارجي</h2><p>الرابط منتهي أو غير صالح. ارجع للموقع المصدر وأعد محاولة الدخول.</p><a href="/saas/console/login">الدخول المحلي</a></body></html>';
        exit;
    }
}
if($path==='/sso/confirm'){
    header('Cache-Control: private,no-store');
    $ticket=(string)($_SESSION['sso_pending']??'');
    try{
        $verified=verify_external_sso_ticket($ticket);
        $preview=external_sso_preview($verified);
    }catch(Throwable $e){
        unset($_SESSION['sso_pending']);
        http_response_code(400);
        echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><body style="font-family:system-ui;padding:40px"><h2>انتهت صلاحية جلسة الدخول الخارجي</h2><p>ابدأ تسجيل الدخول مرة أخرى من موقعك الأصلي.</p><a href="/saas/console/login">الدخول المحلي</a></body></html>';
        exit;
    }
    $local=me();
    $linkedUid=$preview['linked_user_id'];
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        require_csrf();
        try{
            if($linkedUid!==null && $local && (int)$local['id']!==$linkedUid)
                throw new InvalidArgumentException('sso_account_mismatch');
            $result=finish_external_sso($ticket,$local?(int)$local['id']:null);
            unset($_SESSION['sso_pending'],$_SESSION['support']);
            session_regenerate_id(true);
            $_SESSION['uid']=$result['user_id'];
            $_SESSION['account_id']=$result['account_id'];
            // Verified SSO still requires signed assertion and explicit consent.
            // Owner decision 2026-11-04: no second-factor or OTP login challenge.
            unset($_SESSION['mfa_ok'],$_SESSION['mfa_pending'],$_SESSION['mfa_pending_uid'],
                $_SESSION['mfa_pending_at'],$_SESSION['mfa_failures'],$_SESSION['mfa_block_until']);
            audit($result['new_link']?'sso.identity.linked':'sso.login',
                $result['account_id'],['integration_id'=>$result['integration_id']]);
            go('/');
        }catch(Throwable $e){
            // No ticket or signature detail is ever reflected to the browser.
            http_response_code(403);
            echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><body style="font-family:system-ui;padding:40px"><h2>تعذر إكمال ربط الهوية</h2><p>تأكد من الحساب وصلاحياته ثم ابدأ من موقع المصدر مجددًا.</p><a href="/saas/console/login">الدخول المحلي</a></body></html>';
            exit;
        }
    }
    if($linkedUid===null && !$local)go('/login');
    $mismatch=$linkedUid!==null && $local && (int)$local['id']!==$linkedUid;
    $label=$linkedUid!==null?'الدخول بالحساب المرتبط':'ربط هويتي الخارجية بحساب OnTrack الحالي';
    $info=$linkedUid!==null?'الهوية الخارجية مرتبطة بحساب OnTrack موجود.':'الربط يتطلب موافقتك وتسجيل دخولك المحلي، ولا ينشئ عضوية أو اشتراكًا.';
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تأكيد الهوية | OnTrack Voice</title><link rel="stylesheet" href="/saas/console/assets/css/app.css"></head><body><main class="content"><section class="card"><h2>تأكيد الدخول الخارجي</h2><p>'.e($info).'</p><p>المصدر: '.e($preview['issuer']).'</p>';
    if($mismatch){
        echo '<p>أنت مسجل بحساب مختلف عن الحساب المرتبط. سجّل الخروج وابدأ الربط من موقع المصدر مرة أخرى.</p>';
    }else{
        echo '<form method="post" action="/saas/console/sso/confirm"><input type="hidden" name="csrf" value="'.e(csrf()).'"><button class="btn primary" type="submit">'.e($label).'</button></form>';
    }
    echo '<p><a href="/saas/console/login">العودة إلى تسجيل الدخول</a></p></section></main></body></html>';
    exit;
}

if($path==='/register')signup_route();
if($path==='/verify-email')signup_verify_route();
if($path==='/login') {
    if($_SERVER['REQUEST_METHOD']==='POST'){
        require_csrf();
        $email=strtolower(trim((string)($_POST['email']??'')));
        $pass=(string)($_POST['password']??'');
        $bad=(int)($_SESSION['bad_logins']??0);
        $oldest=(int)($_SESSION['bad_start']??0);
        if(time()-$oldest>900){$bad=0;$_SESSION['bad_logins']=0;$_SESSION['bad_start']=time();}
        if($bad>=8){login_page('محاولات كثيرة. حاول بعد 15 دقيقة.');exit;}
        $u=filter_var($email,FILTER_VALIDATE_EMAIL)?one("SELECT * FROM users WHERE email=? AND status='active'",[$email]):null;
        if($u && password_verify($pass,$u['password_hash'])){
            session_regenerate_id(true);
            $_SESSION['uid']=(int)$u['id'];
            // Password + CSRF + rate limiting remain mandatory. An old
            // administrator TOTP enrollment must never challenge this login.
            unset($_SESSION['mfa_ok'],$_SESSION['mfa_pending'],$_SESSION['mfa_pending_uid'],
                $_SESSION['mfa_pending_at'],$_SESSION['mfa_failures'],$_SESSION['mfa_block_until']);
            $m=memberships((int)$u['id']);
            $_SESSION['account_id']=$m[0]['account_id']??null;
            $_SESSION['bad_logins']=0;
            unset($_SESSION['support']);
            audit('login.success',$_SESSION['account_id']);
            go(!empty($_SESSION['sso_pending'])?'/sso/confirm':($m?'/':'/invitations'));
        }
        $_SESSION['bad_logins']=$bad+1;
        usleep(250000);
        login_page('البريد أو كلمة المرور غير صحيحة');exit;
    }
    if(me())go(!empty($_SESSION['sso_pending'])?'/sso/confirm':(memberships((int)me()['id'])?'/':'/invitations'));
    login_page();exit;
}
// MFA enrollment and 6-digit login codes are disabled by owner decision
// 2026-11-04. Redirect old bookmarks without exposing an OTP form;
// unauthenticated browsers still must pass the normal password login.
if(in_array($path,['/mfa','/mfa/setup'],true)){
    require_login();
    go('/');
}
if($path==='/brand-logo'){
    $a=mine();if(!$a)deny(404);
    $brand=brand($a);
    $logo=(string)($brand['logo']??'');
    if(!$logo || $logo!==basename($logo) || !preg_match('/^logo-[0-9]+-[a-f0-9]{12}\.(png|jpg|webp)$/D',$logo))deny(404);
    $full=private_path().'/'.$logo;
    if(!is_file($full))deny(404);
    $ext=strtolower(pathinfo($full,PATHINFO_EXTENSION));
    header('Content-Type: '.(['png'=>'image/png','jpg'=>'image/jpeg','webp'=>'image/webp'][$ext]));
    header('Content-Length: '.filesize($full));
    header('Cache-Control: private,max-age=60');
    readfile($full);exit;
}
require_login();
if($path==='/invitations'){
    if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
        require_csrf();
        if(($_POST['action']??'')!=='accept_invitation')deny(400,'عملية غير معروفة');
        try {
            $newAccount=accept_staff_invitation((int)($_POST['invitation_id']??0),(int)me()['id']);
            audit('staff.invitation.accepted',$newAccount,['invitation_id'=>(int)$_POST['invitation_id']]);
            $_SESSION['account_id']=$newAccount;
            session_regenerate_id(true);
            flash('success','تم قبول الدعوة وإضافة عضويتك بعد موافقتك.');
            go('/');
        }catch(Throwable $e){
            flash('error',$e instanceof InvalidArgumentException?$e->getMessage():'تعذر قبول الدعوة');
            go('/invitations');
        }
    }
    $current=mine();
    if($current)layout_open($current,'overview');
    else {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private,no-store');
        echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>الدعوات | OnTrack Voice</title><link rel="stylesheet" href="/saas/console/assets/css/app.css"></head><body><main class="content">';
        if($message=flash('error'))echo '<div class="alert danger">'.e($message).'</div>';
    }
    render_invitations();
    if($current)layout_close();else echo '</main></body></html>';
    exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    if($path==='/logout'||($_POST['action']??'')==='logout'){
        require_csrf();audit('logout',mine()?(int)mine()['account_id']:null);
        $_SESSION=[];
        if(ini_get('session.use_cookies'))setcookie(session_name(),'',time()-3600,'/','',true,true);
        session_destroy();go('/login');
    }
    act();
}
if($path!=='/' && !in_array($path,['/admin','/reseller','/workspace'],true))deny(404,'الصفحة غير موجودة');
$a=mine();if(!$a)go('/login');
if($path==='/admin'&&$a['kind']!=='platform')deny();
if($path==='/reseller'&&$a['kind']!=='reseller')deny();
if($path==='/workspace'&&$a['kind']!=='customer'&&$a['kind']!=='platform')deny();
$tab=(string)($_GET['tab']??'overview');
if(!array_key_exists($tab,nav_entries($a['kind'])))$tab='overview';
layout_open($a,$tab);
try {
    switch($tab){
        case 'accounts':render_accounts($a);break;
        case 'plans':render_plans($a);break;
        case 'staff':render_staff($a);break;
        case 'sites':render_sites($a);break;
        case 'agents':render_agents($a);break;
        case 'whatsapp':render_whatsapp($a);break;
        case 'whatsapp_admin':render_whatsapp_platform_settings($a);break;
        case 'flows':render_flows($a);break;
        case 'answering':render_answering($a);break;
        case 'campaigns':render_campaigns($a);break;
        case 'calls':render_calls($a);break;
        case 'contacts':render_contacts($a);break;
        case 'devices':render_devices($a);break;
        case 'voices':render_voices($a);break;
        case 'keys':render_keys($a);break;
        case 'integrations':render_integrations($a);break;
        case 'domains':render_domains($a);break;
        case 'billing':render_billing($a);break;
        case 'releases':render_releases($a);break;
        case 'settings':render_settings($a);break;
        default:render_overview($a);
    }
}catch(Throwable $e){
    echo '<section class="card"><h3>تعذر عرض البيانات</h3><p>'.e($e->getMessage()).'</p></section>';
}
layout_close();
