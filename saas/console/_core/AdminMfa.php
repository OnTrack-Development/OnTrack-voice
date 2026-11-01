<?php
declare(strict_types=1);
/** Authenticator-app TOTP. Encrypted server-side; never use email as MFA proof. */
function mfa_secret():string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bytes=random_bytes(20);$out='';$buffer=0;$bits=0;
    foreach(str_split($bytes) as $c){$buffer=($buffer<<8)|ord($c);$bits+=8;
        while($bits>=5){$bits-=5;$out.=$alphabet[($buffer>>$bits)&31];}
        $buffer&=(1<<$bits)-1;
    }
    return $out;
}
function mfa_totp(string $secret,int $step):string {
    if(!preg_match('/^[A-Z2-7]{32}$/D',$secret)||$step<0)throw new InvalidArgumentException('invalid_totp');
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$binary='';$buffer=0;$bits=0;
    foreach(str_split($secret) as $c){
        $buffer=($buffer<<5)|strpos($alphabet,$c);$bits+=5;
        if($bits>=8){$bits-=8;$binary.=chr(($buffer>>$bits)&255);$buffer&=(1<<$bits)-1;}
    }
    $digest=hash_hmac('sha1',pack('NN',intdiv($step,4294967296),$step%4294967296),$binary,true);
    $i=ord($digest[19])&15;
    $value=((ord($digest[$i])&127)<<24)|(ord($digest[$i+1])<<16)|(ord($digest[$i+2])<<8)|ord($digest[$i+3]);
    return str_pad((string)($value%1000000),6,'0',STR_PAD_LEFT);
}
function mfa_step(string $secret,string $code,int $timestamp,int $last=-1):?int {
    if(!preg_match('/^[0-9]{6}$/D',$code))return null;
    $current=intdiv($timestamp,30);
    for($i=$current-1;$i<=$current+1;$i++){
        if($i>$last && $i>=0 && hash_equals(mfa_totp($secret,$i),$code))return $i;
    }
    return null;
}
function mfa_privileged(int $uid):bool {
    return one("SELECT m.user_id FROM memberships m JOIN accounts a ON a.id=m.account_id
        WHERE m.user_id=? AND a.status='active' AND m.role IN ('owner','admin') LIMIT 1",[$uid])!==null;
}
function mfa_record(int $uid):?array {
    return one('SELECT secret_enc,last_step FROM admin_mfa WHERE user_id=?',[$uid]);
}
function mfa_check(int $uid,string $code):bool {
    $row=mfa_record($uid);if(!$row)return false;
    $secret=unseal_secret($row['secret_enc']??null);
    if(!is_string($secret))return false;
    $counter=mfa_step($secret,$code,time(),(int)$row['last_step']);
    if($counter===null)return false;
    return execute('UPDATE admin_mfa SET last_step=? WHERE user_id=? AND last_step<?',[$counter,$uid,$counter])===1;
}
function mfa_page(?string $problem=null):never {
    $uid=(int)me()['id'];$existing=mfa_record($uid);
    header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store,private');
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>التحقق بخطوتين | OnTrack</title><link rel="stylesheet" href="/saas/console/assets/css/app.css"></head>';
    echo '<body><main class="content" style="max-width:540px;margin:6vh auto"><section class="card"><h1>حماية حساب الإدارة</h1>';
    echo '<p>المصادقة بخطوتين مطلوبة لحسابات الإدارة. استخدم تطبيق Authenticator.</p>';
    if($problem)echo '<div class="alert danger">'.e($problem).'</div>';
    echo '<form method="post" action="/saas/console/mfa"><input type="hidden" name="csrf" value="'.e(csrf()).'">';
    if(!$existing){
        $secret=$_SESSION['mfa_pending']??null;
        $valid=is_string($secret)&&($_SESSION['mfa_pending_uid']??null)===$uid
            && time()-(int)($_SESSION['mfa_pending_at']??0)<600;
        if($valid){
            echo '<p>أضف المفتاح التالي داخل تطبيق المصادقة (TOTP، كل 30 ثانية):</p><p dir="ltr" style="font:600 16px monospace;overflow-wrap:anywhere">'.e(implode(' ',str_split($secret,4))).'</p>';
            echo '<input type="hidden" name="mfa_action" value="activate">';
        }else{
            echo '<input type="hidden" name="mfa_action" value="begin">';
            echo '<label class="field">تأكيد كلمة المرور <input type="password" name="password" autocomplete="current-password" required></label>';
        }
    }else echo '<input type="hidden" name="mfa_action" value="verify">';
    if($existing || ($valid ?? false))
        echo '<label class="field">رمز المصادقة من 6 أرقام <input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>';
    echo '<button class="btn primary" type="submit">'.(!$existing&&!($valid??false)?'إعداد المصادقة':'تحقق ومتابعة').'</button></form>';
    echo '<form method="post" action="/saas/console/logout"><input type="hidden" name="csrf" value="'.e(csrf()).'"><button class="btn" type="submit">تسجيل الخروج</button></form></section></main></body></html>';exit;
}
function mfa_route():never {
    $user=require_login();$uid=(int)$user['id'];
    if(!mfa_privileged($uid)){$_SESSION['mfa_ok']=true;go('/');}
    if(!empty($_SESSION['mfa_ok']))go('/');
    $error=null;
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        require_csrf();
        if(($_SESSION['mfa_block_until']??0)>time())mfa_page('محاولات كثيرة، حاول بعد 15 دقيقة.');
        $action=(string)($_POST['mfa_action']??'');$row=mfa_record($uid);$ok=false;
        if(!$row&&$action==='begin'){
            $hash=one("SELECT password_hash FROM users WHERE id=? AND status='active'",[$uid]);
            if($hash&&password_verify((string)($_POST['password']??''),(string)$hash['password_hash'])){
                $_SESSION['mfa_pending']=mfa_secret();$_SESSION['mfa_pending_uid']=$uid;
                $_SESSION['mfa_pending_at']=time();$_SESSION['mfa_failures']=0;
                mfa_page();
            }
            $error='كلمة المرور غير صحيحة';
        }elseif(!$row&&$action==='activate'){
            $secret=$_SESSION['mfa_pending']??null;
            if(is_string($secret)&&($_SESSION['mfa_pending_uid']??null)===$uid
                && time()-(int)($_SESSION['mfa_pending_at']??0)<600){
                $step=mfa_step($secret,(string)($_POST['code']??''),time());
                if($step!==null){
                    try{insert('INSERT INTO admin_mfa(user_id,secret_enc,last_step,created_at) VALUES (?,?,?,?)',
                        [$uid,seal_secret($secret),$step,now()]);$ok=true;}
                    catch(PDOException $e){$error='الحماية مفعلة، أعد تسجيل الدخول';}
                }
            }
            if(!$ok&&!$error)$error='الرمز غير صحيح أو انتهت صلاحية الإعداد';
        }elseif($row&&$action==='verify'){
            $ok=mfa_check($uid,(string)($_POST['code']??''));
            if(!$ok)$error='الرمز خاطئ أو استُخدم من قبل';
        }else $error='العملية غير متاحة';
        if($ok){
            unset($_SESSION['mfa_pending'],$_SESSION['mfa_pending_uid'],$_SESSION['mfa_pending_at'],
                $_SESSION['mfa_failures'],$_SESSION['mfa_block_until']);
            $_SESSION['mfa_ok']=true;session_regenerate_id(true);
            audit('mfa.verified',$_SESSION['account_id']??null);
            go(!empty($_SESSION['sso_pending'])?'/sso/confirm':'/');
        }
        $_SESSION['mfa_failures']=(int)($_SESSION['mfa_failures']??0)+1;
        if($_SESSION['mfa_failures']>=8){$_SESSION['mfa_block_until']=time()+900;$error='محاولات كثيرة، حاول بعد 15 دقيقة.';}
    }
    mfa_page($error);
}
