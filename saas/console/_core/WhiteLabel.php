<?php
declare(strict_types=1);
/** Public white-label portal identity. Never trust a user-supplied reseller/host
 * for authorization; only an active account and an active verified hostname
 * may establish a custom-domain tenant context.
 */
const WHITE_LABEL_PLATFORM_HOST='app.ontrackegy.com';
function white_label_host():string {
    $raw=strtolower(trim((string)($_SERVER['HTTP_HOST']??'')));
    if($raw==='' && PHP_SAPI==='cli')return WHITE_LABEL_PLATFORM_HOST;
    if($raw===''||strlen($raw)>190||!preg_match('/^[a-z0-9.-]+$/D',$raw))
        throw new InvalidArgumentException('invalid_portal_host');
    return $raw;
}
function white_label_portal_slug():?string {
    $path=(string)(parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH)?:'/');
    if(!preg_match('#^/portal/([a-z0-9-]{1,100})(?:/|$)#D',$path,$match)){
        // A terminal /portal/slug path is also a valid dashboard route.
        if(preg_match('#^/portal/([a-z0-9-]{1,100})$#D',$path,$match))return $match[1];
        return null;
    }
    return $match[1];
}
function white_label_context():?array {
    $host=white_label_host();
    $slug=white_label_portal_slug();
    if($host!==WHITE_LABEL_PLATFORM_HOST) {
        if($slug!==null)throw new InvalidArgumentException('custom_host_portal_mismatch');
        $row=one("SELECT a.* FROM domains d JOIN accounts a ON a.id=d.account_id
            WHERE d.hostname=? AND d.status='active' AND a.status='active'
              AND a.kind='reseller' LIMIT 1",[$host]);
        if(!$row)throw new InvalidArgumentException('unmapped_portal_host');
        $row['_portal_host']=$host;
        $row['_portal_mode']='domain';
        return $row;
    }
    if($slug===null)return null;
    $row=one("SELECT * FROM accounts WHERE kind='reseller' AND slug=? AND status='active'",[$slug]);
    if(!$row)throw new InvalidArgumentException('unknown_portal');
    $row['_portal_host']=$host;
    $row['_portal_mode']='path';
    return $row;
}
function white_label_guard():void {
    try{white_label_context();}
    catch(InvalidArgumentException){
        http_response_code(421);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        exit('بوابة الخدمة غير متاحة على هذا النطاق.');
    }
}
function white_label_url(string $path='/'):string {
    $ctx=white_label_context();
    if(!$ctx)return $path;
    if(($ctx['_portal_mode']??'')==='domain')return $path;
    return '/portal/'.rawurlencode((string)$ctx['slug']).($path==='/'?'':$path);
}
function white_label_absolute_url(string $path):string {
    $ctx=white_label_context();
    $base=($ctx && $ctx['_portal_mode']==='domain')
        ?'https://'.$ctx['_portal_host']:'https://'.WHITE_LABEL_PLATFORM_HOST;
    return $base.white_label_url($path);
}
function white_label_identity():array {
    $ctx=white_label_context();
    if(!$ctx)return ['name'=>'OnTrack Voice','logo'=>'','slug'=>''];
    return ['name'=>(string)($ctx['brand_name']?:$ctx['name']),
        'logo'=>(string)($ctx['logo_path']??''),'slug'=>(string)$ctx['slug']];
}
/** On a white-label host/path, all authenticated accounts MUST belong to
 * its reseller or its direct customers, regardless of session/account_id.
 */
function white_label_account_allowed(int $accountId):bool {
    $ctx=white_label_context();
    if(!$ctx)return true;
    $a=account($accountId);
    return $a && $a['status']==='active' &&
        (($a['kind']==='reseller' && (int)$a['id']===(int)$ctx['id']) ||
        ($a['kind']==='customer' && (int)$a['parent_id']===(int)$ctx['id']));
}
function white_label_memberships(int $userId):array {
    return array_values(array_filter(memberships($userId),
        static fn(array $m):bool=>white_label_account_allowed((int)$m['account_id'])));
}
function white_label_logo_file():?array {
    $ctx=white_label_context();
    if(!$ctx)return null;
    $logo=(string)($ctx['logo_path']??'');
    if($logo==='' || $logo!==basename($logo) ||
       !preg_match('/^logo-[0-9]+-[a-f0-9]{12}\.(png|jpg|webp)$/D',$logo))return null;
    $file=private_path().'/'.$logo;
    if(!is_file($file)||is_link($file))return null;
    $extension=strtolower(pathinfo($file,PATHINFO_EXTENSION));
    return ['file'=>$file,'type'=>['png'=>'image/png','jpg'=>'image/jpeg','webp'=>'image/webp'][$extension]];
}
function white_label_logo_route():never {
    $logo=white_label_logo_file();
    if(!$logo)deny(404,'الشعار غير متاح');
    header('Content-Type: '.$logo['type']);
    header('Cache-Control: public,max-age=300');
    header('X-Content-Type-Options: nosniff');
    readfile($logo['file']);
    exit;
}
function white_label_logo_html(int $size=64):string {
    $brand=white_label_identity();
    $name=e((string)$brand['name']);
    $asset=white_label_logo_file();
    if($asset)
        return '<img class="wl-mark" src="'.e(white_label_url('/portal-logo')).'" alt="'.e($brand['name']).'" width="'.$size.'" height="'.$size.'">';
    return '<span class="wl-fallback" aria-hidden="true">'.e(mb_substr((string)$brand['name'],0,1)).'</span>';
}
