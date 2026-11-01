<?php
declare(strict_types=1);
require __DIR__.'/_core/Bootstrap.php';
require __DIR__.'/_core/AdminMfa.php';
require __DIR__.'/_core/Signup.php';
require __DIR__.'/_core/Actions.php';
require __DIR__.'/_core/Views.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(),microphone=(),geolocation=()');
startsession();
try {init_schema();} catch(Throwable $e){
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<html lang="ar" dir="rtl"><body style="font-family:system-ui;padding:48px"><h2>تهيئة قاعدة بيانات لوحة OnTrack</h2><p>تحتاج قاعدة بيانات MySQL أو SQLite مفعلة على السيرفر.</p><code>'.e($e->getMessage()).'</code></body></html>';
    exit;
}
$path=route();
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
            $_SESSION['mfa_ok']=false;
            unset($_SESSION['mfa_pending'],$_SESSION['mfa_pending_uid'],$_SESSION['mfa_pending_at'],$_SESSION['mfa_failures'],$_SESSION['mfa_block_until']);
            $m=memberships((int)$u['id']);
            $_SESSION['account_id']=$m[0]['account_id']??null;
            $_SESSION['bad_logins']=0;
            unset($_SESSION['support']);
            audit('login.success',$_SESSION['account_id']);
            go($m?'/':'/invitations');
        }
        $_SESSION['bad_logins']=$bad+1;
        usleep(250000);
        login_page('البريد أو كلمة المرور غير صحيحة');exit;
    }
    if(me())go(memberships((int)me()['id'])?'/':'/invitations');
    login_page();exit;
}
if($path==='/mfa')mfa_route();
if(!in_array($path,['/login','/logout'],true) && me()
   && mfa_privileged((int)me()['id']) && empty($_SESSION['mfa_ok']))go('/mfa');
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
        if(ini_get('session.use_cookies'))setcookie(session_name(),'',time()-3600,'/saas/console','',true,true);
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
