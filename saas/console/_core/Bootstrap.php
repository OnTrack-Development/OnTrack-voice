<?php
declare(strict_types=1);
const SAAS_ROOT = __DIR__ . '/..';
const SAAS_PRIVATE = '/home/appontrack/.ontrack-saas';
date_default_timezone_set('UTC');
function private_path(): string {
    $path = is_dir('/home/appontrack') ? SAAS_PRIVATE : sys_get_temp_dir().'/ontrack-saas-demo';
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
            (string)($config['user']??''),(string)($config['password']??''),
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
    if (is_file($done)) { init_console_v2(); init_console_v3(); init_console_v4(); init_console_v5(); init_console_v6(); init_console_v7(); init_console_v8(); init_console_v9(); init_console_v10(); init_console_v11(); init_console_v12(); init_console_v13(); init_console_v13(); return; }
    $path=__DIR__.'/../database/'.(driver()==='mysql'?'schema.mysql.sql':'schema.sqlite.sql');
    $text=(string)file_get_contents($path);
    $segments=array_filter(array_map('trim',explode(";\n",$text)));
    foreach($segments as $q){db()->exec($q);}
    file_put_contents($done,now());
    chmod($done,0600);
    init_console_v2();
    init_console_v3();
    init_console_v4(); init_console_v5(); init_console_v6(); init_console_v7(); init_console_v8(); init_console_v9(); init_console_v10(); init_console_v11(); init_console_v12(); init_console_v13(); init_console_v13();
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
function require_csrf():void {
    if (!hash_equals(csrf(),(string)($_POST['csrf']??''))) {http_response_code(419);exit('انتهت صلاحية الطلب');}
}
function flash(string $key,?string $value=null):?string{
    if($value!==null){$_SESSION['flash_'.$key]=$value;return null;}
    $v=$_SESSION['flash_'.$key]??null;unset($_SESSION['flash_'.$key]);return $v;
}
function route():string {
    $uri=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
    $prefix='/saas/console';
    if (str_starts_with($uri,$prefix)) $uri=substr($uri,strlen($prefix));
    $uri='/'.trim($uri,'/');
    return $uri==='/'?'/':$uri;
}
function go(string $path):never {header('Location: /saas/console'.($path==='/'?'':$path),true,303);exit;}
function startsession():void {
    if(PHP_SAPI==='cli')return;
    ini_set('session.use_strict_mode','1');
    session_name('OTVOICESESSION');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/saas/console','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
function me():?array {
    if(empty($_SESSION['uid']))return null;
    return one("SELECT id,name,email,status FROM users WHERE id=? AND status='active'",[(int)$_SESSION['uid']]);
}
function memberships(int $uid):array{
    return all("SELECT m.account_id,m.role,a.kind,a.name,a.slug,a.parent_id,a.status
        FROM memberships m JOIN accounts a ON a.id=m.account_id
        WHERE m.user_id=? AND a.status='active' ORDER BY a.id",[$uid]);
}
function account(int $id):?array{return one('SELECT * FROM accounts WHERE id=?',[$id]);}
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
            ($origin['kind']==='platform' || ($origin['kind']==='reseller'&&
                is_reseller_child($originId,$target)))) {
            return array_merge($subject,['account_id'=>$target,'role'=>support_mode($originId)==='read_only'?'support_viewer':'owner','support_origin'=>$originId]);
        }
        unset($_SESSION['support']);
    }
    foreach(memberships((int)$u['id']) as $m){if((int)$m['account_id']===$id){return array_merge(account($id)??[],$m);}}
    return null;
}
function console_view_mode(?array $context=null):string {
    $a=$context??mine();
    if(!$a||($a['kind']??'')!=='platform'||!in_array($a['role']??'',['owner','admin'],true)||active_support())return 'admin';
    return ($_SESSION['console_view']??'admin')==='user'?'user':'admin';
}
function has_platform():bool {
    $u=me();if(!$u)return false;
    foreach(memberships((int)$u['id']) as $m)if($m['kind']==='platform' && in_array($m['role'],['owner','admin','support','finance'],true))return true;
    return false;
}
function allowed(string $permission,?array $account=null):bool {
    $a=$account??mine();if(!$a)return false;
    $r=$a['role']??'viewer';
    if($r==='support_viewer')return $permission==='read';
    if($r==='owner'||$r==='admin')return true;
    $grants=[
        'manager'=>['read','sites.write','agents.write','campaigns.write','contacts.write','calls.read','staff.read','devices.read','integrations.read'],
        'reception'=>['read','calls.read','contacts.read','devices.read'],
        'operator'=>['read','agents.read','campaigns.read','campaigns.write','calls.read','contacts.read','devices.read'],
        'support'=>['read','calls.read','staff.read','devices.read','integrations.read'],
        'finance'=>['read','billing.read','billing.write'],
        'viewer'=>['read']
    ];
    return in_array($permission,$grants[$r]??[],true);
}
function deny(int $code=403,string $message='لا تملك صلاحية لهذا الإجراء'):never {
    http_response_code($code);echo '<div style="direction:rtl;font-family:system-ui;padding:40px">'.e($message).'</div>';exit;
}
function require_login():array{
    $user=me();if(!$user)go('/login');
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
    if($a['kind']==='platform')return ['name'=>'OnTrack Voice','logo'=>'','accent'=>'#e11d39'];
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
    $row=one("SELECT p.* FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.account_id=? AND s.status='active' ORDER BY s.id DESC LIMIT 1",[$accountId]);
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
    db()->exec("CREATE TABLE IF NOT EXISTS voice_knowledge_sources (
        id $id,account_id $int NOT NULL,site_id $int NOT NULL,
        name VARCHAR(150) NOT NULL,kind VARCHAR(25) NOT NULL,
        content TEXT NOT NULL,status VARCHAR(18) NOT NULL,
        sha256 VARCHAR(64) NOT NULL,created_at VARCHAR(19) NOT NULL)".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS voice_data_connectors (
        id $id,account_id $int NOT NULL,site_id $int NOT NULL,
        type VARCHAR(25) NOT NULL,name VARCHAR(120) NOT NULL,
        base_url VARCHAR(500) NOT NULL,read_path VARCHAR(500) NOT NULL,
        auth_mode VARCHAR(25) NOT NULL,secret_enc TEXT NULL,
        status VARCHAR(20) NOT NULL,last_tested_at VARCHAR(19) NULL,
        created_at VARCHAR(19) NOT NULL)".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS voice_caller_roles (
        id $id,account_id $int NOT NULL,site_id $int NOT NULL,
        phone_e164 VARCHAR(20) NOT NULL,name VARCHAR(120) NOT NULL,
        role VARCHAR(24) NOT NULL,permissions_json TEXT NOT NULL,
        pin_hash VARCHAR(255) NOT NULL,status VARCHAR(20) NOT NULL,
        created_at VARCHAR(19) NOT NULL,
        UNIQUE(account_id,phone_e164))".$engine);
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}


/** Additive staff voice authorization and connector fact snapshots. No old data overwritten. */
function init_console_v13():void {
    $marker=private_path().'/schema.v13';
    if(is_file($marker))return;
    if(driver()==='mysql'){
        db()->exec("CREATE TABLE IF NOT EXISTS voice_staff_numbers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            account_id BIGINT NOT NULL,user_id BIGINT NOT NULL,
            phone_e164 VARCHAR(20) NOT NULL,pin_hash VARCHAR(255) NOT NULL,
            scopes_json TEXT NOT NULL,status VARCHAR(20) NOT NULL,
            failed_attempts INT NOT NULL DEFAULT 0,locked_until VARCHAR(19) NULL,
            created_at VARCHAR(19) NOT NULL,updated_at VARCHAR(19) NOT NULL,
            UNIQUE KEY uq_voice_staff_phone(account_id,phone_e164)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        db()->exec("CREATE TABLE IF NOT EXISTS integration_facts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            account_id BIGINT NOT NULL,integration_id BIGINT NOT NULL,
            category VARCHAR(24) NOT NULL,fact_key VARCHAR(80) NOT NULL,
            fact_value TEXT NOT NULL,updated_at VARCHAR(19) NOT NULL,
            UNIQUE KEY uq_connector_fact(account_id,integration_id,category,fact_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }else{
        db()->exec("CREATE TABLE IF NOT EXISTS voice_staff_numbers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,account_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,phone_e164 TEXT NOT NULL,
            pin_hash TEXT NOT NULL,scopes_json TEXT NOT NULL,status TEXT NOT NULL,
            failed_attempts INTEGER NOT NULL DEFAULT 0,locked_until TEXT,
            created_at TEXT NOT NULL,updated_at TEXT NOT NULL,
            UNIQUE(account_id,phone_e164)
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS integration_facts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,account_id INTEGER NOT NULL,
            integration_id INTEGER NOT NULL,category TEXT NOT NULL,
            fact_key TEXT NOT NULL,fact_value TEXT NOT NULL,updated_at TEXT NOT NULL,
            UNIQUE(account_id,integration_id,category,fact_key)
        )");
    }
    // Existing voice call RBAC and signed integration fact ingestion share one
    // membership-backed identity registry (multiple phones per employee).
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
