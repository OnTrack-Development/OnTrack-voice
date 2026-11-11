<?php
declare(strict_types=1);
const GAP_PRIVATE='/home/appontrack/.ontrack-gap-map';
function gap_map_headers():void {
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; img-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
}
function gap_map_session():void {
    if(session_status()===PHP_SESSION_ACTIVE)return;
    ini_set('session.use_strict_mode','1');
    session_name('ONTRACKGAPMAP');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/architecture-map/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    session_start();
}
function gap_map_csrf():string {
    if(empty($_SESSION['gap_csrf']))$_SESSION['gap_csrf']=bin2hex(random_bytes(24));
    return (string)$_SESSION['gap_csrf'];
}
function gap_map_valid_csrf(mixed $value):bool {
    return is_string($value) && hash_equals(gap_map_csrf(),$value);
}
function gap_map_verified():bool {
    return isset($_SESSION['gap_verified'])&&is_int($_SESSION['gap_verified'])
        &&$_SESSION['gap_verified']>=time()-21600;
}
function gap_map_throttled(string $ip,bool $increment=false):bool {
    // Atomic rate counters independent of the browser session.
    $file=GAP_PRIVATE.'/rate-limit.json';
    if(is_link($file)||!is_dir(GAP_PRIVATE))return true;
    $f=@fopen($file,'c+'); if(!is_resource($f))return true;
    try{
        @chmod($file,0600);
        if(!flock($f,LOCK_EX))return true;
        $raw=stream_get_contents($f);$state=json_decode(is_string($raw)?$raw:'',true);
        if(!is_array($state))$state=['global'=>[],'ips'=>[]];
        $now=time();$start=$now-900;
        $global=array_values(array_filter($state['global']??[],static fn($v)=>is_int($v)&&$v>$start));
        $key=hash('sha256',$ip);
        $ips=is_array($state['ips']??null)?$state['ips']:[];
        $local=array_values(array_filter($ips[$key]??[],static fn($v)=>is_int($v)&&$v>$start));
        $blocked=count($local)>=8||count($global)>=100;
        if($increment&&!$blocked){$local[]=$now;$global[]=$now;}
        $ips[$key]=$local;
        if(count($ips)>1500)$ips=[$key=>$local];
        $next=json_encode(['global'=>$global,'ips'=>$ips],JSON_THROW_ON_ERROR);
        rewind($f);ftruncate($f,0);fwrite($f,$next);fflush($f);
        return $blocked;
    }finally{flock($f,LOCK_UN);fclose($f);}
}
function gap_map_check_pin(string $pin):bool {
    if(!preg_match('/\A[0-9]{6}\z/D',$pin))return false;
    $file=GAP_PRIVATE.'/pin.hash';
    if(!is_file($file)||is_link($file)||!is_readable($file))return false;
    $hash=trim((string)@file_get_contents($file,false,null,0,255));
    return strlen($hash)>=40 && password_verify($pin,$hash);
}
function gap_map_login_form(string $error=''):never {
    gap_map_headers();header('Content-Type: text/html; charset=utf-8');
    $e=htmlspecialchars($error,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $csrf=htmlspecialchars(gap_map_csrf(),ENT_QUOTES,'UTF-8');
    echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OnTrack | Architecture Map</title><link rel="stylesheet" href="style.css"><body class="login-body"><main class="login-card"><div class="login-logo">OT</div><h1>Architecture Map</h1><p>متابعة فجوات OnTrack Voice — أدخل رمز الدخول المكوّن من 6 أرقام</p><form method="post" autocomplete="off"><label for="pin">PIN</label><input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" minlength="6" required autofocus autocomplete="off"><input type="hidden" name="csrf" value="'.$csrf.'"><button class="btn primary" type="submit">فتح الخريطة</button></form><div class="login-error">'.$e.'</div><small>الجلسة مؤقتة، والمحاولات المحدودة تحمي الماب من التخمين.</small></main></body></html>';
    exit;
}
function gap_map_enter():void {
    gap_map_session();
    if($_SERVER['REQUEST_METHOD']!=='POST')return;
    $pin=(string)($_POST['pin']??'');
    if(!gap_map_valid_csrf($_POST['csrf']??null))gap_map_login_form('انتهت صلاحية النموذج. أعد المحاولة.');
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
    if(gap_map_throttled($ip,true)){http_response_code(429);header('Retry-After: 900');gap_map_login_form('محاولات كثيرة، جرّب لاحقًا.');}
    if(!gap_map_check_pin($pin)){http_response_code(401);gap_map_login_form('رمز الدخول غير صحيح.');}
    session_regenerate_id(true);
    $_SESSION['gap_verified']=time();
    $_SESSION['gap_csrf']=bin2hex(random_bytes(24));
    header('Location: /architecture-map/',true,303);
    exit;
}
function gap_map_authorize(bool $json=false):void {
    gap_map_headers();
    gap_map_session();
    if(gap_map_verified())return;
    if($json){
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo '{"ok":false,"error":"pin_required"}';
        exit;
    }
    gap_map_login_form();
}
function gap_map_json(array $data,int $status=200):never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    exit;
}
