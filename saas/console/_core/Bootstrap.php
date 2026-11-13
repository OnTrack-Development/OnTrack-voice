<?php
declare(strict_types=1);
const SAAS_ROOT = __DIR__ . '/..';
const SAAS_PRIVATE = '/home/appontrack/.ontrack-saas';
date_default_timezone_set('UTC');
require_once __DIR__.'/WhiteLabel.php';
function private_path(): string {
    // CLI-only isolated host preflight. Never let an HTTP request pick a DB path.
    $isolated=PHP_SAPI==='cli'?(string)(getenv('ONTRACK_TEST_PRIVATE')?:''):'';
    if($isolated!==''){
        if(!preg_match('~^/home/appontrack/\.ontrack-ci-[A-Za-z0-9_-]{8,60}/private$~D',$isolated))
            throw new RuntimeException('unsafe_test_private_path');
        $root=dirname($isolated);
        if(!is_dir($root)||is_link($root))throw new RuntimeException('untrusted_test_root');
        $path=$isolated;
    }else{
        // Isolate runner fixtures; the PHP built-in HTTP test server is loopback-only.
        // Apache/FPM/web production cannot choose a private database via HTTP.
        // The built-in php -S test server has PHP_SAPI=cli-server; Apache/FPM
        // never honor this environment variable. CI runner sets it explicitly.
        $isCi=in_array(PHP_SAPI,['cli','cli-server'],true);
        $tag=$isCi?(string)(getenv('ONTRACK_CI_NAMESPACE')?:''):'';
        if($tag!==''){
            if(!preg_match('/^[A-Za-z0-9_-]{8,60}$/D',$tag))
                throw new RuntimeException('unsafe_ci_namespace');
            $path=sys_get_temp_dir().'/ontrack-saas-ci-'.$tag;
        }else{
            $path=is_dir('/home/appontrack')?SAAS_PRIVATE:sys_get_temp_dir().'/ontrack-saas-demo';
        }
    }
    if (!is_dir($path)) {mkdir($path,0700,true);}
    return $path;
}
function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $configFile=private_path().'/database.json';
    $config=is_file($configFile)?json_decode((string)file_get_contents($configFile),true):[];
    $config=is_array($config)?$config:[];
    if (($config['driver']??'')==='mysql') {
        $pdo = new PDO(
            'mysql:host='.($config['host']??'localhost').';port='.($config['port']??3306).';dbname='.($config['database']??'').';charset=utf8mb4',
            (string)($config['user']??$config['username']??''),(string)($config['password']??''),
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES=>false]
        );
    } else {
        if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
            throw new RuntimeException('DB_NOT_CONFIGURED: set a private MySQL database.json; no SQLite driver available');
        }
        $dbpath=private_path().'/ontrack-console.sqlite';
        $pdo=new PDO('sqlite:'.$dbpath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=4000; PRAGMA journal_mode=WAL');
        @chmod($dbpath,0600);
    }
    return $pdo;
}
function driver():string {return db()->getAttribute(PDO::ATTR_DRIVER_NAME);}
function sql(string $q,array $args=[]):PDOStatement {
    $s=db()->prepare($q);$s->execute($args);return $s;
}
function one(string $q,array $a=[]):?array{$v=sql($q,$a)->fetch();return $v?:null;}
function all(string $q,array $a=[]):array{return sql($q,$a)->fetchAll();}
function execute(string $q,array $a=[]):int{return sql($q,$a)->rowCount();}
function insert(string $q,array $a=[]):int{sql($q,$a);return (int)db()->lastInsertId();}
function now():string{return gmdate('Y-m-d H:i:s');}
function hash_password(string $p):string{return password_hash($p,PASSWORD_DEFAULT);}
function init_schema():void {
    $done=private_path().'/schema.v1';
    if (is_file($done)) { init_console_v2(); init_console_v3(); init_console_v4(); init_console_v5(); init_console_v6(); init_console_v7(); init_console_v8(); init_console_v9(); init_console_v10(); init_console_v11(); init_console_v12(); init_console_v13(); init_console_v14(); init_console_v15(); init_console_v16(); init_console_v17(); init_console_v18(); init_console_v19(); init_console_v20(); init_console_v21(); init_console_v22(); init_console_v23(); init_console_v24(); init_console_v25(); init_console_v26(); init_console_v27(); init_console_v28(); require_once __DIR__.'/VoiceKnowledgeWeb.php'; voice_kb_web_init(); init_voice_agent_assignment_history(); init_console_voice_transcripts_v1(); require_once __DIR__.'/SubscriptionPlans.php';plan_catalog_init(); require_once __DIR__.'/BillingInvoices.php';billing_invoice_init(); return; }
    $path=__DIR__.'/../database/'.(driver()==='mysql'?'schema.mysql.sql':'schema.sqlite.sql');
    $text=(string)file_get_contents($path);
    $segments=array_filter(array_map('trim',explode(";\n",$text)));
    foreach($segments as $q){db()->exec($q);}
    file_put_contents($done,now());
    chmod($done,0600);
    init_console_v2();
    init_console_v3();
    init_console_v4(); init_console_v5(); init_console_v6(); init_console_v7(); init_console_v8(); init_console_v9(); init_console_v10(); init_console_v11(); init_console_v12(); init_console_v13(); init_console_v14(); init_console_v15(); init_console_v16(); init_console_v17(); init_console_v18(); init_console_v19(); init_console_v20(); init_console_v21(); init_console_v22(); init_console_v23(); init_console_v24(); init_console_v25(); init_console_v26(); init_console_v27(); init_console_v28(); require_once __DIR__.'/VoiceKnowledgeWeb.php'; voice_kb_web_init(); init_voice_agent_assignment_history(); init_console_voice_transcripts_v1(); require_once __DIR__.'/SubscriptionPlans.php';plan_catalog_init(); require_once __DIR__.'/BillingInvoices.php';billing_invoice_init();
}
/** Safe incremental migration of the already-live demo DB (both supported PDO drivers). */
function init_console_v2():void{
    $marker=private_path().'/schema.v2';
    if(is_file($marker))return;
    db()->exec("CREATE TABLE IF NOT EXISTS support_policies (account_id BIGINT PRIMARY KEY, access_mode VARCHAR(12) NOT NULL DEFAULT 'full')");
    foreach(['api_keys','integrations'] as $table){
        $existing=driver()==='sqlite'
           ?array_column(all("PRAGMA table_info(".$table.")"),'name')
           :array_column(all("SHOW COLUMNS FROM ".$table),'Field');
        if(!in_array('secret_enc',$existing,true))
            db()->exec("ALTER TABLE ".$table." ADD COLUMN secret_enc TEXT NULL");
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}
/** Add all published Google Live voices to existing database, without enabling untested options. */
function init_console_v3():void {
    $marker=private_path().'/schema.v3';
    if(is_file($marker))return;
    require_once __DIR__.'/VoiceCatalog.php';
    sync_google_live_voices();
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}
/** Additive invitation migration; pending invitations never grant membership. */
function init_console_v4():void {
    $marker=private_path().'/schema.v4';
    if(is_file($marker))return;
    if(driver()==='mysql'){
        db()->exec("CREATE TABLE IF NOT EXISTS staff_invitations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            account_id BIGINT NOT NULL, email VARCHAR(190) NOT NULL,
            name VARCHAR(190) NOT NULL, role VARCHAR(30) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            created_by BIGINT NOT NULL, created_at VARCHAR(190) NOT NULL,
            expires_at VARCHAR(190) NOT NULL,
            accepted_by BIGINT NULL, accepted_at VARCHAR(190) NULL,
            UNIQUE KEY uq_staff_invite_account_email(account_id,email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }else{
        db()->exec("CREATE TABLE IF NOT EXISTS staff_invitations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL, email TEXT NOT NULL,
            name TEXT NOT NULL, role TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            created_by INTEGER NOT NULL, created_at TEXT NOT NULL,
            expires_at TEXT NOT NULL, accepted_by INTEGER, accepted_at TEXT,
            UNIQUE(account_id,email)
        )");
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}
/** New administrative second factor, additive across SQLite and MySQL. */
function init_console_v6():void {
    $marker=private_path().'/schema.v6';
    if(is_file($marker))return;
    if(driver()==='mysql'){
        db()->exec("CREATE TABLE IF NOT EXISTS admin_mfa (
            user_id BIGINT UNSIGNED PRIMARY KEY, secret_enc TEXT NOT NULL,
            last_step BIGINT NOT NULL DEFAULT -1, created_at VARCHAR(190) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }else{
        db()->exec("CREATE TABLE IF NOT EXISTS admin_mfa (
            user_id INTEGER PRIMARY KEY, secret_enc TEXT NOT NULL,
            last_step INTEGER NOT NULL DEFAULT -1, created_at TEXT NOT NULL
        )");
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}
/** Immutable published flow snapshots for SIM-bound version selection. */
function init_console_v7():void {
    $marker=private_path().'/schema.v7';
    if(is_file($marker))return;
    if(driver()==='mysql'){
        db()->exec("CREATE TABLE IF NOT EXISTS agent_flow_versions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            account_id BIGINT NOT NULL,site_id BIGINT NOT NULL,agent_id BIGINT NOT NULL,
            revision INT NOT NULL,flow_hash CHAR(64) NOT NULL,snapshot_hash CHAR(64) NOT NULL,
            flow_json MEDIUMTEXT NOT NULL,voice VARCHAR(190) NOT NULL,
            instructions TEXT NOT NULL,knowledge MEDIUMTEXT NOT NULL,
            published_by BIGINT NOT NULL,published_at VARCHAR(19) NOT NULL,
            UNIQUE KEY uq_agent_flow_revision(agent_id,revision),
            KEY ix_tenant_agent_flow(account_id,site_id,agent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }else{
        db()->exec("CREATE TABLE IF NOT EXISTS agent_flow_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,site_id INTEGER NOT NULL,agent_id INTEGER NOT NULL,
            revision INTEGER NOT NULL,flow_hash TEXT NOT NULL,snapshot_hash TEXT NOT NULL,
            flow_json TEXT NOT NULL,voice TEXT NOT NULL,instructions TEXT NOT NULL,
            knowledge TEXT NOT NULL,published_by INTEGER NOT NULL,published_at TEXT NOT NULL,
            UNIQUE(agent_id,revision)
        )");
        db()->exec('CREATE INDEX IF NOT EXISTS ix_tenant_agent_flow ON agent_flow_versions(account_id,site_id,agent_id)');
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Tenant-scoped public registration and one-time password reset (both database drivers). */
function init_console_v24():void {
    $marker=private_path().'/schema.v24';
    if(is_file($marker))return;
    $existing=driver()==='sqlite'
        ?array_column(all('PRAGMA table_info(signup_requests)'),'name')
        :array_column(all('SHOW COLUMNS FROM signup_requests'),'Field');
    if(!in_array('registration_kind',$existing,true))
        db()->exec("ALTER TABLE signup_requests ADD COLUMN registration_kind VARCHAR(12) NOT NULL DEFAULT 'customer'");
    if(!in_array('parent_account_id',$existing,true))
        db()->exec('ALTER TABLE signup_requests ADD COLUMN parent_account_id BIGINT NULL');
    $users=driver()==='sqlite'
        ?array_column(all('PRAGMA table_info(users)'),'name')
        :array_column(all('SHOW COLUMNS FROM users'),'Field');
    if(!in_array('auth_version',$users,true))
        db()->exec('ALTER TABLE users ADD COLUMN auth_version INTEGER NOT NULL DEFAULT 0');
    if(driver()==='mysql'){
        db()->exec("CREATE TABLE IF NOT EXISTS password_reset_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at VARCHAR(19) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at VARCHAR(19) NOT NULL,
            KEY ix_reset_user(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }else{
        db()->exec("CREATE TABLE IF NOT EXISTS password_reset_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token_hash TEXT NOT NULL UNIQUE,
            expires_at TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            created_at TEXT NOT NULL
        )");
        db()->exec('CREATE INDEX IF NOT EXISTS ix_reset_user ON password_reset_requests(user_id)');
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Email proof pending table; no user exists before verification. */
function init_console_v8():void {
    $marker=private_path().'/schema.v8';
    if(is_file($marker))return;
    if(driver()==='mysql'){
        db()->exec("CREATE TABLE IF NOT EXISTS signup_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,name VARCHAR(120) NOT NULL,
            company_name VARCHAR(120) NOT NULL,password_hash TEXT NOT NULL,
            token_hash CHAR(64) NULL,status VARCHAR(20) NOT NULL,
            expires_at VARCHAR(19) NOT NULL,created_at VARCHAR(19) NOT NULL,
            UNIQUE KEY uq_signup_token(token_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }else{
        db()->exec("CREATE TABLE IF NOT EXISTS signup_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,email TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,company_name TEXT NOT NULL,password_hash TEXT NOT NULL,
            token_hash TEXT UNIQUE,status TEXT NOT NULL,expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL
        )");
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}
/** Signed external identity links. Additive v9, distinct from device v5. */
function init_console_v9():void {
    $marker=private_path().'/schema.v9';
    if(is_file($marker))return;
    if(driver()==='mysql'){
        db()->exec("CREATE TABLE IF NOT EXISTS external_identity_links (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            integration_id BIGINT NOT NULL, external_subject VARCHAR(128) NOT NULL,
            user_id BIGINT NOT NULL, created_at VARCHAR(190) NOT NULL,
            UNIQUE KEY uq_sso_subject(integration_id,external_subject),
            UNIQUE KEY uq_sso_user(integration_id,user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        db()->exec("CREATE TABLE IF NOT EXISTS sso_used_tickets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            integration_id BIGINT NOT NULL, jti_hash VARCHAR(64) NOT NULL,
            expires_at VARCHAR(190) NOT NULL, created_at VARCHAR(190) NOT NULL,
            UNIQUE KEY uq_sso_jti(integration_id,jti_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }else{
        db()->exec("CREATE TABLE IF NOT EXISTS external_identity_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            integration_id INTEGER NOT NULL, external_subject TEXT NOT NULL,
            user_id INTEGER NOT NULL, created_at TEXT NOT NULL,
            UNIQUE(integration_id,external_subject), UNIQUE(integration_id,user_id)
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS sso_used_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            integration_id INTEGER NOT NULL, jti_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL, created_at TEXT NOT NULL,
            UNIQUE(integration_id,jti_hash)
        )");
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}
function support_mode(int $accountId):string{
    $row=one('SELECT access_mode FROM support_policies WHERE account_id=?',[$accountId]);
    if(!$row)$row=one('SELECT access_mode FROM support_policies WHERE account_id=0');
    return ($row['access_mode']??'full')==='read_only'?'read_only':'full';
}
function active_support():?array{
    $sp=$_SESSION['support']??null;
    return is_array($sp)&&($sp['expires']??0)>time()?$sp:null;
}
/** Encrypted secrets can be shown later only by privileged, reauthenticated owners.
 * Never write plaintext secrets to GitHub, an HTML data-attribute or audit logs.
 */
function secret_key():string{
    $path=private_path().'/console-vault.key';
    if(!file_exists($path)){
        $fh=@fopen($path,'x');
        if($fh!==false){chmod($path,0600);fwrite($fh,random_bytes(32));fclose($fh);}
    }
    $key=@file_get_contents($path);
    if(!is_string($key)||strlen($key)!==32)throw new RuntimeException('مفتاح خزنة الأسرار غير متاح');
    return $key;
}
function seal_secret(string $plain):string{
    $nonce=random_bytes(12);$tag='';
    $cipher=openssl_encrypt($plain,'aes-256-gcm',secret_key(),OPENSSL_RAW_DATA,$nonce,$tag);
    if($cipher===false)throw new RuntimeException('تعذر تشفير السر');
    return base64_encode($nonce.$tag.$cipher);
}
function unseal_secret(?string $box):?string{
    if(!$box)return null;
    $raw=base64_decode($box,true);
    if($raw===false||strlen($raw)<29)return null;
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',secret_key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
    return $plain===false?null:$plain;
}
function e(?string $x):string{return htmlspecialchars((string)$x,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function csrf():string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
/** Agent Studio & Voice Preview AJAX always get a typed JSON error even for auth/CSRF preflights. */
function agent_studio_ajax_request():bool {
    return ($_SERVER['REQUEST_METHOD']??'')==='POST'
        && in_array((string)($_POST['action']??''),['update_agent','preview_voice'],true)
        && ($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest';
}
function agent_studio_ajax_error(int $status,string $message):never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private,no-store');
    echo json_encode(['ok'=>false,'error'=>$message],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    exit;
}
function require_csrf():void {
    if (!hash_equals(csrf(),(string)($_POST['csrf']??''))) {
        if(agent_studio_ajax_request())agent_studio_ajax_error(419,'انتهت صلاحية الطلب. حدّث الصفحة وسجّل الدخول مجددًا إذا لزم.');
        http_response_code(419);exit('انتهت صلاحية الطلب');
    }
}
function flash(string $key,?string $value=null):?string{
    if($value!==null){$_SESSION['flash_'.$key]=$value;return null;}
    $v=$_SESSION['flash_'.$key]??null;unset($_SESSION['flash_'.$key]);return $v;
}
function route():string {
    $uri=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
    $prefix='/saas/console';
    if (str_starts_with($uri,$prefix)) $uri=substr($uri,strlen($prefix));
    if(preg_match('#^/portal/[a-z0-9-]{1,100}(?:/|$)#',$uri,$match))
        $uri=substr($uri,strlen(rtrim($match[0],'/')));
    $uri='/'.trim($uri,'/');
    return $uri==='/'?'/':$uri;
}
function go(string $path):never {
    $route=explode('?',$path,2)[0];
    // Public auth routes live at the web root in the published SaaS layout.
    // Other historic console paths keep their existing prefix/redirect behavior.
    $public=['/register','/register-logo','/verify-email','/forgot-password',
        '/reset-password','/change-password'];
    $prefix=in_array($route,$public,true)?'':'/saas/console';
    $portal=white_label_context();
    if($portal && $portal['_portal_mode']==='path')
        $prefix='/portal/'.rawurlencode((string)$portal['slug']);
    header('Location: '.($path==='/'?($prefix===''?'/':$prefix):$prefix.$path),true,303);
    exit;
}
function startsession():void {
    if(PHP_SAPI==='cli')return;
    ini_set('session.use_strict_mode','1');
    session_name('OTVOICESESSION');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
function me():?array {
    if(empty($_SESSION['uid']))return null;
    $u=one("SELECT id,name,email,status,auth_version FROM users WHERE id=? AND status='active'",[(int)$_SESSION['uid']]);
    if(!$u || (int)($u['auth_version']??0)!==(int)($_SESSION['auth_version']??0)){
        unset($_SESSION['uid'],$_SESSION['account_id'],$_SESSION['support'],$_SESSION['auth_version']);
        return null;
    }
    return $u;
}
function memberships(int $uid):array{
    return all("SELECT m.account_id,m.role,a.kind,a.name,a.slug,a.parent_id,a.status
        FROM memberships m JOIN accounts a ON a.id=m.account_id
        WHERE m.user_id=? AND a.status='active' ORDER BY a.id",[$uid]);
}
function account(int $id):?array{return one('SELECT * FROM accounts WHERE id=?',[$id]);}
/** Suspend the reseller's operational services without modifying paid child
 * subscriptions or mutating each child's account status. A parent reopening
 * cannot reactivate an explicitly suspended child. Invalid parent chains fail
 * closed, while billing/audit records remain queryable for review. */
function account_service_active(?array $account):bool {
    $visited=[];
    for($depth=0;$depth<8;$depth++){
        if(!$account||($account['status']??'')!=='active')return false;
        $id=(int)($account['id']??0);
        if($id<1||isset($visited[$id]))return false;
        $visited[$id]=true;
        $parentId=(int)($account['parent_id']??0);
        if($parentId<1)return true;
        $account=account($parentId);
    }
    return false;
}
function mine():?array{
    $u=me();if(!$u)return null;
    $id=(int)($_SESSION['account_id']??0);
    if (!empty($_SESSION['support']) && is_array($_SESSION['support'])) {
        $sp=$_SESSION['support'];
        $originId=(int)($sp['origin_id']??0);
        $target=(int)($sp['account_id']??0);
        $origin=null;
        foreach(memberships((int)$u['id']) as $m)
            if((int)$m['account_id']===$originId)$origin=$m;
        $subject=account($target);
        if((int)($sp['expires']??0)>time() && $origin && $subject &&
            white_label_account_allowed($target) && white_label_account_allowed($originId) &&
            ($origin['kind']==='platform' || ($origin['kind']==='reseller'&&
                is_reseller_child($originId,$target)))) {
            return array_merge($subject,['account_id'=>$target,'role'=>support_mode($originId)==='read_only'?'support_viewer':'owner','support_origin'=>$originId]);
        }
        unset($_SESSION['support']);
    }
    foreach(memberships((int)$u['id']) as $m){
        if((int)$m['account_id']===$id && white_label_account_allowed($id))
            return array_merge(account($id)??[],$m);
    }
    return null;
}
/** View changes are PRESENTATION ONLY; account identity, support policies,
 * metering and real memberships never change. Support users can preview only
 * the reseller they already opened from an authorized platform membership.
 */
function console_can_switch_support_view(?array $context=null):bool {
    $a=$context??mine();
    $sp=active_support();
    if(!$a || !$sp || ($a['kind']??'')!=='reseller' ||
       (int)($a['account_id']??0)!==(int)($sp['account_id']??-1) ||
       (int)($a['support_origin']??0)!==(int)($sp['origin_id']??-1))return false;
    $u=me();
    if(!$u)return false;
    foreach(memberships((int)$u['id']) as $m){
        if((int)$m['account_id']===(int)$sp['origin_id'] && $m['kind']==='platform'
            && in_array($m['role'],['owner','admin','support'],true))return true;
    }
    return false;
}
function console_can_switch_view(?array $context=null):bool {
    $a=$context??mine();
    return $a!==null && in_array((string)($a['kind']??''),['platform','reseller'],true)
        && in_array((string)($a['role']??''),['owner','admin'],true)
        && active_support()===null;
}
function console_view_mode(?array $context=null):string {
    if(console_can_switch_support_view($context)){
        $sp=active_support();
        return ($sp['view']??'admin')==='user'?'user':'admin';
    }
    if(!console_can_switch_view($context))return 'admin';
    return ($_SESSION['console_view']??'admin')==='user'?'user':'admin';
}
function has_platform():bool {
    $u=me();if(!$u)return false;
    if(white_label_context())return false;
    foreach(memberships((int)$u['id']) as $m)if($m['kind']==='platform' && in_array($m['role'],['owner','admin','support','finance'],true))return true;
    return false;
}
function allowed(string $permission,?array $account=null):bool {
    $a=$account??mine();if(!$a)return false;
    $r=$a['role']??'viewer';
    if($r==='support_viewer')return $permission==='read';
    if($r==='owner'||$r==='admin')return true;
    $grants=[
        'manager'=>['read','sites.write','agents.write','campaigns.write','contacts.write','calls.write','calls.read','staff.read','devices.read','integrations.read'],
        'reception'=>['read','calls.write','calls.read','contacts.read','devices.read'],
        'operator'=>['read','agents.read','campaigns.read','campaigns.write','calls.write','calls.read','contacts.read','devices.read'],
        'support'=>['read','calls.read','staff.read','devices.read','integrations.read'],
        'finance'=>['read','billing.read','billing.write'],
        'viewer'=>['read']
    ];
    return in_array($permission,$grants[$r]??[],true);
}
function deny(int $code=403,string $message='لا تملك صلاحية لهذا الإجراء'):never {
    if(agent_studio_ajax_request())agent_studio_ajax_error($code,$message);
    http_response_code($code);echo '<div style="direction:rtl;font-family:system-ui;padding:40px">'.e($message).'</div>';exit;
}
function require_login():array{
    $user=me();
    if(!$user || (white_label_context()&&!white_label_memberships((int)$user['id']))){
        if(agent_studio_ajax_request())
            agent_studio_ajax_error(401,'انتهت جلسة الدخول. سجّل الدخول من جديد ثم أعد نشر إعدادات المساعد.');
        go('/login');
    }
    return $user;
}
function require_permission(string $p):void {if(!allowed($p))deny();}
function owned_site(int $siteId,int $accountId):?array{
    return one('SELECT * FROM sites WHERE id=? AND account_id=?',[$siteId,$accountId]);
}
function owner_scope():int{
    $a=mine();if(!$a)deny();
    return (int)$a['account_id'];
}
function brand(array $a):array{
    if($a['kind']==='platform')return ['name'=>(string)($a['brand_name']?:'OnTrack Voice'),
        'logo'=>(string)($a['logo_path']??''),'accent'=>'#cb2925'];
    if($a['kind']==='reseller')return ['name'=>(string)($a['brand_name']?:$a['name']),'logo'=>(string)($a['logo_path']??''),'accent'=>'#e11d39'];
    if(!empty($a['parent_id'])){
        $p=account((int)$a['parent_id']);
        if($p)return ['name'=>(string)($p['brand_name']?:$p['name']),'logo'=>(string)($p['logo_path']??''),'accent'=>'#e11d39'];
    }
    return ['name'=>'OnTrack Voice','logo'=>'','accent'=>'#e11d39'];
}
function audit(string $action,?int $accountId=null,array $details=[]):void{
    $actor=me();if(!$actor)return;
    $info=json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    insert('INSERT INTO audit_events(actor_user_id,account_id,action,detail,created_at) VALUES (?,?,?,?,?)',[$actor['id'],$accountId,$action,$info,now()]);
}
function site_limit(int $accountId,string $key):int{
    $account=account($accountId);
    if(!account_service_active($account))return 0;
    // The platform is the service owner, not a metered customer. It must not
    // acquire an expiring demo subscription merely to create sites/agents or
    // maintain its own voice/SIM configuration. Child tenants remain metered.
    if($account['kind']==='platform')return in_array($key,[
        'call_quota','site_limit','agent_limit','staff_limit',
        'reseller_customers','google_key_limit'
    ],true)?1000000000:0;
    $row=one("SELECT p.* FROM subscriptions s JOIN plans p ON p.id=s.plan_id
        WHERE s.account_id=? AND s.status='active' AND s.starts_at<=? AND s.ends_at>?
        ORDER BY s.id DESC LIMIT 1",[$accountId,now(),now()]);
    return (int)($row[$key]??0);
}
function can_create(int $accountId,string $kind):bool{
    $table=$kind==='site'?'sites':'agents';$col=$kind==='site'?'site_limit':'agent_limit';
    return (int)one("SELECT COUNT(*) n FROM {$table} WHERE account_id=? AND status!='archived'",[$accountId])['n']<site_limit($accountId,$col);
}
function is_reseller_child(int $reseller,int $customer):bool{
    $a=account($customer);return $a&&$a['kind']==='customer'&&(int)$a['parent_id']===$reseller;
}
function support_access(int $customerId):bool{
    $a=mine();if(!$a)return false;
    return $a['kind']==='platform' || ($a['kind']==='reseller'&&is_reseller_child((int)$a['account_id'],$customerId));
}
function selected_site(int $accountId):?array {
    $id=(int)($_GET['site']??$_SESSION['site_id']??0);
    $site=$id?owned_site($id,$accountId):null;
    if($site&&$site['status']!=='active')$site=null;
    if(!$site)$site=one("SELECT * FROM sites WHERE account_id=? AND status='active' ORDER BY id LIMIT 1",[$accountId]);
    if($site)$_SESSION['site_id']=$site['id'];
    return $site;
}
function is_demo():bool{return getenv('ONTRACK_DEMO')==='1';}

/** Device enrollment is additive and keeps legacy pilot auth separate. */
function init_console_v5():void {
    $marker=private_path().'/schema.v5';
    if(is_file($marker))return;
    if(driver()==='mysql'){
        db()->exec("CREATE TABLE IF NOT EXISTS device_pairings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            account_id BIGINT NOT NULL,site_id BIGINT NOT NULL,agent_id BIGINT NOT NULL,
            code_hash CHAR(64) NOT NULL UNIQUE,status VARCHAR(24) NOT NULL,
            expires_at VARCHAR(19) NOT NULL,created_by BIGINT NOT NULL,
            created_at VARCHAR(19) NOT NULL,redeemed_at VARCHAR(19) NULL,device_id BIGINT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        db()->exec("CREATE TABLE IF NOT EXISTS device_bindings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            account_id BIGINT NOT NULL,site_id BIGINT NOT NULL,agent_id BIGINT NOT NULL,
            installation_id VARCHAR(128) NOT NULL,sim_slot INT NOT NULL,
            slot_claim CHAR(64) NULL UNIQUE,device_name VARCHAR(100) NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,status VARCHAR(24) NOT NULL,
            created_at VARCHAR(19) NOT NULL,last_seen_at VARCHAR(19) NULL,revoked_at VARCHAR(19) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }else{
        db()->exec("CREATE TABLE IF NOT EXISTS device_pairings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,site_id INTEGER NOT NULL,agent_id INTEGER NOT NULL,
            code_hash TEXT NOT NULL UNIQUE,status TEXT NOT NULL,expires_at TEXT NOT NULL,
            created_by INTEGER NOT NULL,created_at TEXT NOT NULL,redeemed_at TEXT,device_id INTEGER
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS device_bindings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,site_id INTEGER NOT NULL,agent_id INTEGER NOT NULL,
            installation_id TEXT NOT NULL,sim_slot INTEGER NOT NULL,
            slot_claim TEXT UNIQUE,device_name TEXT NOT NULL,
            token_hash TEXT NOT NULL UNIQUE,status TEXT NOT NULL,
            created_at TEXT NOT NULL,last_seen_at TEXT,revoked_at TEXT
        )");
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Additive v10 storage: answering settings and immutable release snapshots. */
function init_console_v10():void {
    $marker=private_path().'/schema.v10';
    if(is_file($marker))return;
    if(driver()==='mysql'){
        db()->exec("CREATE TABLE IF NOT EXISTS agent_answer_policies (
           account_id BIGINT NOT NULL, agent_id BIGINT NOT NULL, policy_json TEXT NOT NULL,
           updated_at VARCHAR(19) NOT NULL, PRIMARY KEY (account_id,agent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }else{
        db()->exec("CREATE TABLE IF NOT EXISTS agent_answer_policies (
           account_id INTEGER NOT NULL, agent_id INTEGER NOT NULL, policy_json TEXT NOT NULL,
           updated_at TEXT NOT NULL, PRIMARY KEY (account_id,agent_id))");
    }
    $cols=driver()==='sqlite'?array_column(all('PRAGMA table_info(agent_flow_versions)'),'name'):
        array_column(all('SHOW COLUMNS FROM agent_flow_versions'),'Field');
    if(!in_array('answer_policy_json',$cols,true))
        db()->exec("ALTER TABLE agent_flow_versions ADD COLUMN answer_policy_json TEXT NULL");
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Incremental plan overage price migration, safe for preexisting SQLite and MySQL. */
function init_console_v11():void {
    $marker=private_path().'/schema.v11';
    if(is_file($marker))return;
    $cols=driver()==='sqlite'?array_column(all('PRAGMA table_info(plans)'),'name'):
        array_column(all('SHOW COLUMNS FROM plans'),'Field');
    if(!in_array('overage_minor',$cols,true))
        db()->exec('ALTER TABLE plans ADD COLUMN overage_minor BIGINT NOT NULL DEFAULT 0');
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Tenant-native phone workspace; never reuses the retired pilot store. */
function init_console_v12():void {
    $marker=private_path().'/schema.v12';
    if(is_file($marker))return;
    $cols=driver()==='sqlite'?array_column(all('PRAGMA table_info(calls)'),'name'):
        array_column(all('SHOW COLUMNS FROM calls'),'Field');
    foreach(['external_call_id'=>'VARCHAR(64)','device_binding_id'=>'BIGINT','sim_slot'=>'BIGINT'] as $name=>$type){
        if(!in_array($name,$cols,true))db()->exec("ALTER TABLE calls ADD COLUMN $name $type NULL");
    }
    if(driver()==='sqlite')db()->exec("CREATE UNIQUE INDEX IF NOT EXISTS tenant_calls_unique ON calls(device_binding_id,external_call_id)");
    else{
        $indexed=one("SELECT COUNT(*) n FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='calls' AND index_name='tenant_calls_unique'");
        if((int)($indexed['n']??0)===0)db()->exec("CREATE UNIQUE INDEX tenant_calls_unique ON calls(device_binding_id,external_call_id)");
    }
    $int=driver()==='mysql'?'BIGINT':'INTEGER';
    $sql="CREATE TABLE IF NOT EXISTS tenant_call_events (
        device_binding_id $int NOT NULL, call_id VARCHAR(64) NOT NULL, sequence $int NOT NULL,
        created_at VARCHAR(19) NOT NULL, PRIMARY KEY(device_binding_id,call_id,sequence))";
    db()->exec($sql.(driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':''));
    $campaigns="CREATE TABLE IF NOT EXISTS tenant_campaigns (
        id VARCHAR(24) PRIMARY KEY, account_id $int NOT NULL, site_id $int NOT NULL,
        device_binding_id $int NOT NULL, sim_slot $int NOT NULL, name VARCHAR(160) NOT NULL,
        starts_at VARCHAR(19) NOT NULL, ends_at VARCHAR(19) NOT NULL,
        scenario TEXT NOT NULL, contact_ids_json TEXT NOT NULL, status VARCHAR(20) NOT NULL,
        created_at VARCHAR(19) NOT NULL)";
    db()->exec($campaigns.(driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':''));
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Isolated tenant knowledge, verified external sources, and caller role registry. */
function init_console_v13():void {
    $marker=private_path().'/schema.v13';
    if(is_file($marker))return;
    $mysql=driver()==='mysql';
    $id=$mysql?'BIGINT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $int=$mysql?'BIGINT':'INTEGER';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    $knowledgeText=$mysql?'MEDIUMTEXT':'TEXT';
    db()->exec("CREATE TABLE IF NOT EXISTS voice_knowledge_sources (
        id $id,account_id $int NOT NULL,site_id $int NOT NULL,
        name VARCHAR(150) NOT NULL,kind VARCHAR(25) NOT NULL,
        content $knowledgeText NOT NULL,visibility VARCHAR(20) NOT NULL DEFAULT 'public',
        status VARCHAR(18) NOT NULL,
        sha256 VARCHAR(64) NOT NULL,created_at VARCHAR(19) NOT NULL)".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS voice_data_connectors (
        id $id,account_id $int NOT NULL,site_id $int NOT NULL,
        type VARCHAR(25) NOT NULL,name VARCHAR(120) NOT NULL,
        base_url VARCHAR(500) NOT NULL,read_path VARCHAR(500) NOT NULL,
        auth_mode VARCHAR(25) NOT NULL,secret_enc TEXT NULL,
        status VARCHAR(20) NOT NULL,last_tested_at VARCHAR(19) NULL,
        created_at VARCHAR(19) NOT NULL)".$engine);
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Additive staff authorization and fact registry; independent of KB/connector v13. */
function init_console_v14():void {
    $marker=private_path().'/schema.v14';
    if(is_file($marker))return;
    $mysql=driver()==='mysql';
    $id=$mysql?'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $int=$mysql?'BIGINT':'INTEGER';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS voice_staff_numbers (
        id $id,account_id $int NOT NULL,user_id $int NOT NULL,
        phone_e164 VARCHAR(20) NOT NULL,pin_hash VARCHAR(255) NOT NULL,
        scopes_json TEXT NOT NULL,status VARCHAR(20) NOT NULL,
        failed_attempts $int NOT NULL DEFAULT 0,locked_until VARCHAR(19) NULL,
        created_at VARCHAR(19) NOT NULL,updated_at VARCHAR(19) NOT NULL,
        UNIQUE(account_id,phone_e164))".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS integration_facts (
        id $id,account_id $int NOT NULL,integration_id $int NOT NULL,
        category VARCHAR(30) NOT NULL,fact_key VARCHAR(80) NOT NULL,
        fact_value TEXT NOT NULL,updated_at VARCHAR(19) NOT NULL,
        UNIQUE(account_id,integration_id,category,fact_key))".$engine);
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Correct historic mobile call events misidentified as demo by the old ingest. */
function init_console_v15():void {
    $marker=private_path().'/schema.v15';
    if(is_file($marker))return;
    execute('UPDATE calls SET is_demo=0 WHERE is_demo=1 AND device_binding_id IS NOT NULL AND external_call_id IS NOT NULL');
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Account + editor user + agent scoped, bounded AI conversation across draft saves. */
function init_console_v16():void {
    $marker=private_path().'/schema.v16';
    if(is_file($marker))return;
    $mysql=driver()==='mysql';
    $int=$mysql?'BIGINT':'INTEGER';
    $body=$mysql?'MEDIUMTEXT':'TEXT';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS flow_ai_chats (
        account_id $int NOT NULL,
        user_id $int NOT NULL,
        agent_id $int NOT NULL,
        messages_json $body NOT NULL,
        pending_graph_json $body NULL,
        updated_at VARCHAR(19) NOT NULL,
        PRIMARY KEY (account_id,user_id,agent_id))".$engine);
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Agent Builder AI can safely resume proposed profile changes after a draft save. */
function init_console_v17():void {
    $marker=private_path().'/schema.v17';
    if(is_file($marker))return;
    $columns=driver()==='mysql'?array_column(all('SHOW COLUMNS FROM flow_ai_chats'),'Field'):
        array_column(all('PRAGMA table_info(flow_ai_chats)'),'name');
    if(!in_array('pending_profile_json',$columns,true)){
        db()->exec('ALTER TABLE flow_ai_chats ADD COLUMN pending_profile_json '.(driver()==='mysql'?'MEDIUMTEXT':'TEXT').' NULL');
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Official Meta WhatsApp transport and per-account human Inbox; no edits to existing WHMCS notification addon. */
function init_console_v18():void {
    $marker=private_path().'/schema.v18';
    if(is_file($marker))return;
    $mysql=driver()==='mysql';
    $int=$mysql?'BIGINT':'INTEGER';
    $pk=$mysql?'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $text=$mysql?'MEDIUMTEXT':'TEXT';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS wa_connections (
        id $pk,account_id $int NOT NULL,site_id $int NOT NULL,
        public_id VARCHAR(64) NOT NULL UNIQUE,label VARCHAR(120) NOT NULL,
        phone_number_id VARCHAR(100) NOT NULL,
        access_token_enc $text NOT NULL,app_secret_enc $text NOT NULL,verify_token_hash VARCHAR(64) NOT NULL,
        whmcs_bridge_url VARCHAR(400) NULL,whmcs_bridge_token_enc $text NULL,
        whmcs_bridge_verified_at VARCHAR(19) NULL,
        state VARCHAR(24) NOT NULL DEFAULT 'draft',
        last_tested_at VARCHAR(19) NULL,last_webhook_at VARCHAR(19) NULL,
        created_at VARCHAR(19) NOT NULL,updated_at VARCHAR(19) NOT NULL,
        UNIQUE(account_id,phone_number_id))".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS wa_conversations (
        id $pk,account_id $int NOT NULL,site_id $int NOT NULL,connection_id $int NOT NULL,
        contact VARCHAR(32) NOT NULL,contact_name VARCHAR(150) NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'open',
        ai_pause_until VARCHAR(19) NULL,human_locked_at VARCHAR(19) NULL,
        generation $int NOT NULL DEFAULT 0,last_customer_at VARCHAR(19) NULL,
        last_message_at VARCHAR(19) NOT NULL,created_at VARCHAR(19) NOT NULL,
        UNIQUE(connection_id,contact))".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS wa_messages (
        id $pk,account_id $int NOT NULL,conversation_id $int NOT NULL,
        connection_id $int NOT NULL,provider_message_id VARCHAR(160) NULL,
        role VARCHAR(20) NOT NULL,body $text NOT NULL,
        delivery_status VARCHAR(26) NOT NULL,created_at VARCHAR(19) NOT NULL,
        UNIQUE(connection_id,provider_message_id))".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS wa_audit (
        id $pk,account_id $int NOT NULL,conversation_id $int NULL,
        event VARCHAR(55) NOT NULL,detail VARCHAR(180) NOT NULL,
        created_at VARCHAR(19) NOT NULL)".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS wa_ai_jobs (
        id $pk,account_id $int NOT NULL,conversation_id $int NOT NULL,
        connection_id $int NOT NULL,source_message_id VARCHAR(160) NOT NULL,
        generation $int NOT NULL,status VARCHAR(22) NOT NULL DEFAULT 'pending',
        attempts $int NOT NULL DEFAULT 0,
        created_at VARCHAR(19) NOT NULL,updated_at VARCHAR(19) NOT NULL,
        UNIQUE(connection_id,source_message_id))".$engine);
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}


/** Native OnTrack WhatsApp agent is opt-in, per channel and per published agent. */
function init_console_v19():void {
    $marker=private_path().'/schema.v19';
    if(is_file($marker))return;
    $columns=driver()==='mysql'?array_column(all('SHOW COLUMNS FROM wa_connections'),'Field'):
        array_column(all('PRAGMA table_info(wa_connections)'),'name');
    if(!in_array('ai_agent_id',$columns,true))
        db()->exec('ALTER TABLE wa_connections ADD COLUMN ai_agent_id BIGINT NULL');
    if(!in_array('ai_enabled',$columns,true))
        db()->exec('ALTER TABLE wa_connections ADD COLUMN ai_enabled INTEGER NOT NULL DEFAULT 0');
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}


/** Manually configured Meta business credentials are tenant-owned. All secrets
 * remain encrypted/hash-only in v18; new v20 fields are non-sensitive IDs. */
function init_console_v20():void {
    $marker=private_path().'/schema.v20';
    if(is_file($marker))return;
    $columns=driver()==='mysql'?array_column(all('SHOW COLUMNS FROM wa_connections'),'Field'):
        array_column(all('PRAGMA table_info(wa_connections)'),'name');
    foreach(['waba_id'=>'VARCHAR(100) NULL','meta_app_id'=>'VARCHAR(100) NULL',
        'graph_version'=>"VARCHAR(8) NOT NULL DEFAULT 'v26.0'"] as $field=>$type){
        if(!in_array($field,$columns,true))
            db()->exec('ALTER TABLE wa_connections ADD COLUMN '.$field.' '.$type);
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Signed inbound events forwarded by existing WhatsApp providers, not an AI bridge. */
function init_console_v21():void {
    $marker=private_path().'/schema.v21';
    if(is_file($marker))return;
    $columns=driver()==='mysql'?array_column(all('SHOW COLUMNS FROM wa_connections'),'Field'):
        array_column(all('PRAGMA table_info(wa_connections)'),'name');
    if(!in_array('webhook_relay_secret_enc',$columns,true))
        db()->exec('ALTER TABLE wa_connections ADD COLUMN webhook_relay_secret_enc TEXT NULL');
    if(!in_array('webhook_relay_enabled',$columns,true))
        db()->exec('ALTER TABLE wa_connections ADD COLUMN webhook_relay_enabled INTEGER NOT NULL DEFAULT 0');
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Platform-only Embedded Signup availability and independent tenant methods. */
function init_console_v22():void {
    $marker=private_path().'/schema.v22';
    if(is_file($marker))return;
    $db=db();
    $driver=driver();
    $columns=$driver==='mysql'?array_column(all('SHOW COLUMNS FROM wa_connections'),'Field'):
        array_column(all('PRAGMA table_info(wa_connections)'),'name');
    foreach([
        'onboarding_method'=>"VARCHAR(24) NOT NULL DEFAULT 'meta_direct'",
        'source_callback_url'=>'VARCHAR(400) NULL',
        'source_verify_hash'=>'VARCHAR(64) NULL'
    ] as $field=>$sqlType){
        if(!in_array($field,$columns,true))
            $db->exec('ALTER TABLE wa_connections ADD COLUMN '.$field.' '.$sqlType);
    }
    $engine=$driver==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $db->exec("CREATE TABLE IF NOT EXISTS wa_platform_onboarding (
        id INTEGER NOT NULL PRIMARY KEY,
        app_id VARCHAR(100) NOT NULL DEFAULT '',
        app_secret_enc TEXT NULL,
        signup_config_id VARCHAR(100) NOT NULL DEFAULT '',
        coexist_config_id VARCHAR(100) NOT NULL DEFAULT '',
        graph_version VARCHAR(8) NOT NULL DEFAULT 'v26.0',
        verify_token_hash VARCHAR(64) NULL,
        signup_enabled INTEGER NOT NULL DEFAULT 0,
        coexist_enabled INTEGER NOT NULL DEFAULT 0,
        updated_at VARCHAR(19) NULL
    )".$engine);
    if(!one('SELECT id FROM wa_platform_onboarding WHERE id=1')){
        try{insert('INSERT INTO wa_platform_onboarding(id,updated_at) VALUES(1,?)',[now()]);}
        catch(PDOException $e){if(!one('SELECT id FROM wa_platform_onboarding WHERE id=1'))throw $e;}
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Call findings are attributable to one call and one SIM, not a loose contact
 * note. A reported handoff is NOT an executed transfer.
 */
function init_console_v23():void {
    $marker=private_path().'/schema.v23';
    if(is_file($marker))return;
    $int=driver()==='mysql'?'BIGINT':'INTEGER';
    $engine=driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS tenant_call_reports (
        account_id $int NOT NULL, site_id $int NOT NULL,
        device_binding_id $int NOT NULL, call_id VARCHAR(64) NOT NULL,
        summary TEXT NOT NULL, customer_notes TEXT NOT NULL,
        outcome VARCHAR(32) NOT NULL DEFAULT 'unknown',
        handoff_status VARCHAR(24) NOT NULL DEFAULT 'not_requested',
        handoff_reason TEXT NOT NULL,
        source VARCHAR(24) NOT NULL DEFAULT 'manual',
        revision $int NOT NULL DEFAULT 1,
        created_at VARCHAR(19) NOT NULL,updated_at VARCHAR(19) NOT NULL,
        PRIMARY KEY (device_binding_id,call_id)
    )".$engine);
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}


/**
 * Additive per-physical-SIM agent generation. Historical generation 1 is
 * backfilled only for the agent currently known on that device: never fabricate
 * assignments that predate this migration.
 */
function init_voice_agent_assignment_history():void {
    $marker=private_path().'/schema.voice-agent-generation.v1';
    if(is_file($marker))return;
    $columns=driver()==='mysql'?array_column(all('SHOW COLUMNS FROM device_bindings'),'Field'):
        array_column(all('PRAGMA table_info(device_bindings)'),'name');
    if(!in_array('agent_revision',$columns,true))
        db()->exec('ALTER TABLE device_bindings ADD COLUMN agent_revision INTEGER NOT NULL DEFAULT 1');
    if(driver()==='mysql'){
        db()->exec("CREATE TABLE IF NOT EXISTS device_agent_revisions (
            device_id BIGINT NOT NULL,account_id BIGINT NOT NULL,site_id BIGINT NOT NULL,
            agent_id BIGINT NOT NULL,agent_revision INTEGER NOT NULL,
            created_at VARCHAR(19) NOT NULL,
            PRIMARY KEY(device_id,agent_revision)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }else{
        db()->exec("CREATE TABLE IF NOT EXISTS device_agent_revisions (
            device_id INTEGER NOT NULL,account_id INTEGER NOT NULL,site_id INTEGER NOT NULL,
            agent_id INTEGER NOT NULL,agent_revision INTEGER NOT NULL,
            created_at TEXT NOT NULL,PRIMARY KEY(device_id,agent_revision)
        )");
    }
    db()->exec("INSERT INTO device_agent_revisions
        (device_id,account_id,site_id,agent_id,agent_revision,created_at)
        SELECT d.id,d.account_id,d.site_id,d.agent_id,d.agent_revision,d.created_at
        FROM device_bindings d WHERE NOT EXISTS (
            SELECT 1 FROM device_agent_revisions r
            WHERE r.device_id=d.id AND r.agent_revision=d.agent_revision)");
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}


/** Additive tenant/physical-SIM-bound transcript archive; no public files. */
function init_console_voice_transcripts_v1():void {
    $marker=private_path().'/schema.voice-transcripts.v1';
    if(is_file($marker))return;
    if(driver()==='mysql'){
        db()->exec("CREATE TABLE IF NOT EXISTS tenant_call_transcripts (
            account_id BIGINT NOT NULL,site_id BIGINT NOT NULL,
            device_binding_id BIGINT NOT NULL,sim_slot INT NOT NULL,
            call_id CHAR(36) NOT NULL,transcript TEXT NOT NULL,
            source VARCHAR(32) NOT NULL,created_at VARCHAR(19) NOT NULL,
            PRIMARY KEY(device_binding_id,call_id),
            KEY ix_transcript_scope(account_id,site_id,device_binding_id,sim_slot)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }else{
        db()->exec("CREATE TABLE IF NOT EXISTS tenant_call_transcripts (
            account_id INTEGER NOT NULL,site_id INTEGER NOT NULL,
            device_binding_id INTEGER NOT NULL,sim_slot INTEGER NOT NULL,
            call_id TEXT NOT NULL,transcript TEXT NOT NULL,
            source TEXT NOT NULL,created_at TEXT NOT NULL,
            PRIMARY KEY(device_binding_id,call_id))");
        db()->exec("CREATE INDEX IF NOT EXISTS ix_transcript_scope
            ON tenant_call_transcripts(account_id,site_id,device_binding_id,sim_slot)");
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}


/** Additive Knowledge folders and revisions. Existing v13 knowledge is preserved. */
function init_console_v26():void {
    $marker=private_path().'/schema.v26';
    if(is_file($marker))return;
    $mysql=driver()==='mysql';
    $id=$mysql?'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $int=$mysql?'BIGINT':'INTEGER';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $content=$mysql?'MEDIUMTEXT':'TEXT';
    db()->exec("CREATE TABLE IF NOT EXISTS voice_knowledge_folders (
        id $id, account_id $int NOT NULL, site_id $int NOT NULL,
        parent_id $int NOT NULL DEFAULT 0,name VARCHAR(100) NOT NULL,
        created_at VARCHAR(19) NOT NULL)".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS voice_knowledge_revisions (
        id $id,source_id $int NOT NULL,account_id $int NOT NULL,
        site_id $int NOT NULL,version INTEGER NOT NULL,
        content $content NOT NULL,sha256 VARCHAR(64) NOT NULL,
        created_at VARCHAR(19) NOT NULL)".$engine);
    $existing=$mysql?array_column(all('SHOW COLUMNS FROM voice_knowledge_sources'),'Field'):
        array_column(all('PRAGMA table_info(voice_knowledge_sources)'),'name');
    if(!in_array('folder_id',$existing,true))
        db()->exec('ALTER TABLE voice_knowledge_sources ADD COLUMN folder_id BIGINT NOT NULL DEFAULT 0');
    if(!in_array('version',$existing,true))
        db()->exec('ALTER TABLE voice_knowledge_sources ADD COLUMN version INTEGER NOT NULL DEFAULT 1');
    if(!in_array('updated_at',$existing,true))
        db()->exec('ALTER TABLE voice_knowledge_sources ADD COLUMN updated_at VARCHAR(19) NULL');
    if($mysql){
        $indexes=array_column(all("SHOW INDEX FROM voice_knowledge_sources"),'Key_name');
        if(!in_array('idx_voice_kb_scope',$indexes,true))
            db()->exec('CREATE INDEX idx_voice_kb_scope ON voice_knowledge_sources(account_id,site_id,folder_id,status)');
    }else{
        db()->exec('CREATE INDEX IF NOT EXISTS idx_voice_kb_scope ON voice_knowledge_sources(account_id,site_id,folder_id,status)');
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}


/** IO Premium: additive catalogue entry, never substitute a Google voice.
 * The platform's encrypted Fish settings are reused server-side for the
 * internal release. Ordinary customer accounts require an explicit grant.
 */
function init_console_v27():void {
    $marker=private_path().'/schema.v27';
    if(is_file($marker))return;
    $ioCode='io.premium';
    $cfg=private_path().'/voice-lab/owner-1.json';
    $available=false;
    if(is_file($cfg) && !is_link($cfg)) {
        $json=json_decode((string)file_get_contents($cfg),true);
        $voice=(string)($json['settings']['voiceId']??'');
        $key=$json['keys']['fish']??null;
        $available=preg_match('/^[a-f0-9]{32}$/D',$voice)===1
            && is_string($key) && (unseal_secret($key)??'')!=='';
    }
    $row=one('SELECT id,ready FROM voices WHERE code=?',[$ioCode]);
    if(!$row) {
        $id=insert("INSERT INTO voices(code,name,provider,tier,ready,status)
            VALUES (?,?,?,'premium',?,'active')",
            [$ioCode,'IO Premium','fish_audio',$available?1:0]);
    }else{
        $id=(int)$row['id'];
        execute('UPDATE voices SET name=?,provider=?,tier=? WHERE id=?',
            ['IO Premium','fish_audio','premium',$id]);
        // A later manual disable by platform admin must remain effective.
    }
    if($available) {
        foreach(all("SELECT id FROM accounts WHERE kind='platform' AND status='active'") as $platform) {
            $accountId=(int)$platform['id'];
            if(!one('SELECT id FROM voice_grants WHERE account_id=? AND voice_id=?',[$accountId,$id]))
                insert('INSERT INTO voice_grants(account_id,voice_id) VALUES (?,?)',[$accountId,$id]);
        }
    }
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

/** Additive rich voice metadata and zero-token local sample cache columns. */
function init_console_v28():void {
    $marker=private_path().'/schema.v28';
    if(is_file($marker))return;
    $existing=driver()==='mysql'
        ?array_column(all('SHOW COLUMNS FROM voices'),'Field')
        :array_column(all('PRAGMA table_info(voices)'),'name');
    foreach([
        'gender'=>"VARCHAR(20) NOT NULL DEFAULT 'neutral'",
        'dialect'=>"VARCHAR(80) NOT NULL DEFAULT ''",
        'source_ref_id'=>"VARCHAR(120) NOT NULL DEFAULT ''",
        'sample_path'=>'TEXT NULL',
        'sample_url'=>'TEXT NULL',
        'meta_json'=>'TEXT NULL',
    ] as $field=>$type){
        if(!in_array($field,$existing,true))
            db()->exec('ALTER TABLE voices ADD COLUMN '.$field.' '.$type);
    }
    $femaleCodes=['google.aoede','google.kore','google.leda','google.zephyr','google.autonoe',
        'google.erinome','google.laomedeia','google.despina','google.callirrhoe',
        'google.pulcherrima','google.vindemiatrix','google.sulafat','google.sadachbia'];
    $maleCodes=['google.orus','google.puck','google.fenrir','google.charon','google.umbriel',
        'google.schedar','google.achird','google.enceladus','google.algieba','google.algenib',
        'google.achernar','google.gacrux','google.zubenelgenubi','google.sadaltager',
        'google.iapetus','google.rasalgethi','google.alnilam'];
    foreach($femaleCodes as $code)
        execute("UPDATE voices SET gender='female',dialect=CASE WHEN dialect IS NULL OR dialect='' THEN 'متعدد اللغات' ELSE dialect END WHERE code=? AND (gender IS NULL OR gender='' OR gender='neutral')",[$code]);
    foreach($maleCodes as $code)
        execute("UPDATE voices SET gender='male',dialect=CASE WHEN dialect IS NULL OR dialect='' THEN 'متعدد اللغات' ELSE dialect END WHERE code=? AND (gender IS NULL OR gender='' OR gender='neutral')",[$code]);
    $cfg=private_path().'/voice-lab/owner-1.json';
    $ioRef='';
    if(is_file($cfg)&&!is_link($cfg)){
        $json=json_decode((string)file_get_contents($cfg),true);
        $cand=strtolower(trim((string)($json['settings']['voiceId']??'')));
        if(preg_match('/^[0-9a-f]{32}$/D',$cand)===1)$ioRef=$cand;
    }
    execute("UPDATE voices SET dialect=CASE WHEN dialect IS NULL OR dialect='' THEN 'متعدد اللهجات' ELSE dialect END, source_ref_id=CASE WHEN (source_ref_id IS NULL OR source_ref_id='') AND ?<>'' THEN ? ELSE source_ref_id END WHERE code='io.premium'",[$ioRef,$ioRef]);
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}

function init_console_v25():void {
    db()->exec("CREATE TABLE IF NOT EXISTS platform_feature_settings (
        feature_key VARCHAR(64) PRIMARY KEY, enabled INTEGER NOT NULL DEFAULT 1,
        updated_at VARCHAR(19) NOT NULL)");
}
function platform_resellers_visible():bool {
    $flag=one("SELECT enabled FROM platform_feature_settings WHERE feature_key='resellers'");
    return $flag===null || (int)$flag['enabled']===1;
}
function platform_set_resellers_visible(bool $enabled):void {
    $current=one("SELECT feature_key FROM platform_feature_settings WHERE feature_key='resellers'");
    if($current)execute("UPDATE platform_feature_settings SET enabled=?,updated_at=? WHERE feature_key='resellers'",[$enabled?1:0,now()]);
    else insert("INSERT INTO platform_feature_settings(feature_key,enabled,updated_at) VALUES ('resellers',?,?)",[$enabled?1:0,now()]);
}
