<?php
declare(strict_types=1);
require __DIR__.'/../../_core/Bootstrap.php';
require __DIR__.'/../../_core/DncGuard.php';
require __DIR__.'/../../_core/ApiWriteGuard.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function answer(int $status,array $data):never {
    http_response_code($status);
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
$authorization=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
if(!str_starts_with($authorization,'Bearer '))answer(401,['ok'=>false,'error'=>'missing_api_key']);
$key=substr($authorization,7);
if(!preg_match('/^otv_[0-9a-f]{60}$/D',$key))answer(401,['ok'=>false,'error'=>'key_format']);
try{
    init_schema();
    $grant=one("SELECT * FROM api_keys WHERE token_hash=? AND status='active'",[hash('sha256',$key)]);
    if(!$grant)answer(401,['ok'=>false,'error'=>'invalid_api_key']);
    $a=account((int)$grant['account_id']);
    if(!$a||$a['status']!=='active')answer(403,['ok'=>false,'error'=>'account_unavailable']);
    $scopes=array_filter(explode(',',(string)$grant['scopes']));
    $site=(int)($grant['site_id']??0);
    // An API key scoped to a retired site must not retain access to that site.
    if($site && !one("SELECT id FROM sites WHERE id=? AND account_id=? AND status='active'",[$site,$a['id']]))
        answer(403,['ok'=>false,'error'=>'site_unavailable']);
    $method=(string)($_SERVER['REQUEST_METHOD']??'GET');
    $operation=(string)($_GET['op']??'sites');
    $required=match($operation){
        'sites','agents'=>'knowledge:read',
        'knowledge'=>'knowledge:read',
        'lead.create'=>'leads:write',
        default=>null
    };
    if($required===null)answer(404,['ok'=>false,'error'=>'unknown_api_operation']);
    if(!in_array($required,$scopes,true))answer(403,['ok'=>false,'error'=>'scope_denied']);
    execute('UPDATE api_keys SET last_used_at=? WHERE id=?',[now(),$grant['id']]);
    if($method==='GET'){
        if($operation==='sites'){
            $rows=$site?all("SELECT id,name,domain,status FROM sites WHERE account_id=? AND id=? AND status='active'",[$a['id'],$site]):
                all("SELECT id,name,domain,status FROM sites WHERE account_id=? AND status='active'",[$a['id']]);
            answer(200,['ok'=>true,'sites'=>$rows]);
        }
        if($operation==='agents'){
            $rows=$site?all("SELECT g.id,g.site_id,g.name,g.voice,g.status FROM agents g JOIN sites s ON s.id=g.site_id AND s.account_id=g.account_id WHERE g.account_id=? AND g.site_id=? AND g.status!='archived' AND s.status='active'",[$a['id'],$site]):
                all("SELECT g.id,g.site_id,g.name,g.voice,g.status FROM agents g JOIN sites s ON s.id=g.site_id AND s.account_id=g.account_id WHERE g.account_id=? AND g.status!='archived' AND s.status='active'",[$a['id']]);
            answer(200,['ok'=>true,'agents'=>$rows]);
        }
        if($operation==='knowledge'){
            $agent=(int)($_GET['agent_id']??0);
            $row=one("SELECT g.id,g.site_id,g.name,g.knowledge FROM agents g JOIN sites s ON s.id=g.site_id AND s.account_id=g.account_id WHERE g.id=? AND g.account_id=? AND g.status!='archived' AND s.status='active'",[$agent,$a['id']]);
            if(!$row || ($site&&$site!==(int)$row['site_id']))answer(404,['ok'=>false,'error'=>'agent_unavailable']);
            answer(200,['ok'=>true,'agent'=>$row]);
        }
        answer(405,['ok'=>false,'error'=>'method']);
    }
    if($method==='POST'&&$operation==='lead.create'){
        // Bound memory before decoding: a malicious Content-Length or chunked
        // request must not force PHP to allocate an unbounded JSON body.
        if(isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH']>2048)
            answer(413,['ok'=>false,'error'=>'body_too_large']);
        $raw=(string)file_get_contents('php://input',false,null,0,2049);
        if(strlen($raw)>2048)answer(413,['ok'=>false,'error'=>'body_too_large']);
        $p=json_decode($raw,true);
        if(!is_array($p))answer(422,['ok'=>false,'error'=>'json']);
        $requested=(int)($p['site_id']??0);
        if($site && $site!==$requested)answer(403,['ok'=>false,'error'=>'cross_site']);
        $s=owned_site($requested,(int)$a['id']);
        if(!$s)answer(403,['ok'=>false,'error'=>'cross_account']);
        if($s['status']!=='active')answer(403,['ok'=>false,'error'=>'site_unavailable']);
        $name=trim((string)($p['name']??''));
        $phone=trim((string)($p['phone']??''));
        $consent=$p['consent']??false;
        if($name===''||mb_strlen($name)>120||!preg_match('/^\+?[0-9 -]{8,25}$/D',$phone)||$consent!==true)
            answer(422,['ok'=>false,'error'=>'invalid_contact_or_consent']);
        // Prevent authenticated API keys from being used as an unbounded
        // lead-spam channel. Only successful, validated write attempts count.
        if(!saas_api_write_allowed((int)$a['id'],(int)$grant['id'],'lead.create')){
            header('Retry-After: 60');
            answer(429,['ok'=>false,'error'=>'api_write_rate_limited']);
        }
        $dnc=dnc_effective_flag((int)$a['id'],$phone,false);
        $id=insert('INSERT INTO contacts(account_id,site_id,name,phone,dnc,created_at) VALUES (?,?,?,?,?,?)',[$a['id'],$requested,$name,$phone,$dnc,now()]);
        answer(201,['ok'=>true,'contact_id'=>$id,'status'=>'created','dnc'=>$dnc===1,'dialed'=>false]);
    }
    answer(405,['ok'=>false,'error'=>'method_not_allowed']);
}catch(Throwable $e){answer(503,['ok'=>false,'error'=>'database_error']);}
