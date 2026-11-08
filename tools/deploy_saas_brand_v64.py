#!/usr/bin/env python3
"""Safe v64 brand image/text modes and raw binary upload, with rollback.
Preserves independently deployed bills, customers, SIM, typography and APK.
"""
from pathlib import Path
import fcntl
import hashlib
import json
import os
import stat
import subprocess
import tempfile
import time

ROOT=Path('/home/appontrack/public_html')
STAGE=Path('/home/appontrack/.ontrack-mobile/saas-brand-v64')
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
FILES=['_core/BrandIdentity.php','_core/Bootstrap.php','_core/Actions.php','_core/Views.php',
       '_core/WhiteLabel.php','index.php','assets/css/app.css','assets/js/app.js']
START='/* OTV_BRAND_LOGO_V64_BEGIN'
END='/* OTV_BRAND_LOGO_V64_END */'

def digest(data:bytes)->str:
    return hashlib.sha256(data).hexdigest()

def read(path:Path)->str:
    if path.is_symlink() or not path.is_file():
        raise RuntimeError('missing_or_symlinked_'+path.name)
    data=path.read_bytes()
    if b'\0' in data or len(data)>300000:
        raise RuntimeError('unexpected_file_size_or_encoding_'+path.name)
    return data.decode('utf-8-sig').replace('\r\n','\n')

def select(source:str,start:str,end:str)->str:
    a=source.find(start)
    b=source.find(end,a+len(start)) if a>=0 else -1
    if a<0 or b<=a:
        raise RuntimeError('missing_unique_patch_anchor_'+start[:65])
    if source.count(start)!=1:raise RuntimeError('multiple_patch_anchors_'+start[:50])
    return source[a:b]


def patch_views(live:str,candidate:str)->str:
    # Replace only tightly scoped brand layout fragments, not the whole live
    # dashboard file (other teams may have published new invoice/UI sections).
    layoutStart='function layout_open(array $a,string $tab):void{'
    layoutEnd='    $role=white_label_context()'
    if "brand_identity_mode($a)" not in live:
        live=live.replace(select(live,layoutStart,layoutEnd),
                          select(candidate,layoutStart,layoutEnd),1)
    navbarStart="    echo '<div class=\"app-shell\"><aside class=\"sidebar\""
    navbarEnd="    echo '<nav class=\"nav-list\""
    if 'data-brand-display=\"'.replace('\\','') not in live[0:30000]:
        live=live.replace(select(live,navbarStart,navbarEnd),
                          select(candidate,navbarStart,navbarEnd),1)
    loginStart='function login_page(?string $error=null):void{'
    loginEnd="    $signup=white_label_url('/register');"
    if "    $loginBrand=white_label_brand_account();" not in live:
        live=live.replace(select(live,loginStart,loginEnd),
                          select(candidate,loginStart,loginEnd),1)
    # Replace only the login-brand HTML fragment; the generic <div><strong>
    # sequence also appears in unrelated settings and must never be global.
    authStart='<div class="login-brand"'
    authEnd='<div class="login-intro">'
    if 'data-brand-display=' not in select(live,authStart,authEnd):
        live=live.replace(select(live,authStart,authEnd),
                          select(candidate,authStart,authEnd),1)
    oldAccount='data-ot-account="\'.($portal?(int)$portal[\'id\']:0).\'"'
    newAccount='data-ot-account="\'.($loginBrand?(int)$loginBrand[\'id\']:0).\'"'
    if oldAccount in live:live=live.replace(oldAccount,newAccount,1)
    sectionStart='    $adminIdentity=in_array($a[\'kind\']'
    sectionEnd='/** Owner-managed phone roster;'
    if "form_start('brand_logo_remove'" not in live:
        live=live.replace(select(live,sectionStart,sectionEnd),
                          select(candidate,sectionStart,sectionEnd),1)
    for proof in ('function render_settings','data-brand-form','brand_identity_edit_allowed',
                  'data-brand-display','brand-logo?v=','brand_identity_mode($a)'):
        if proof not in live:raise RuntimeError('brand_view_contract_missing_'+proof)
    return live

def patch_actions(live:str,candidate:str)->str:
    begin="            case 'brand':"
    end="            case 'activate_domain_https':"
    if "case 'brand_logo_remove':" not in live:
        live=live.replace(select(live,begin,end),select(candidate,begin,end),1)
    for item in ("case 'brand_logo_remove':","brand_identity_legacy_upload",
                 "brand_identity_save($a,"):
        if item not in live:raise RuntimeError('brand_action_missing_'+item)
    return live

def patch_bootstrap(live:str,candidate:str)->str:
    before="    if($a['kind']==='platform')return ['name'=>'OnTrack Voice','logo'=>'','accent'=>'#e11d39'];"
    after="    if($a['kind']==='platform')return ['name'=>(string)($a['brand_name']?:'OnTrack Voice'),"
    if before in live:
        replacement=select(candidate,after,"    if($a['kind']==='reseller')")
        live=live.replace(before,replacement.rstrip('\n'),1)
    if "if($a['kind']==='platform')return ['name'=>(string)" not in live:
        raise RuntimeError('platform_logo_remains_hard_coded')
    return live

def patch_white_label(live:str,candidate:str)->str:
    identEnd='/** On a white-label host/path'
    oldStart='function white_label_identity():array {'
    newStart='function white_label_brand_account():?array {'
    if newStart not in live:
        live=live.replace(select(live,oldStart,identEnd),
                          select(candidate,newStart,identEnd),1)
    endStart='function white_label_logo_file():?array {'
    if 'white_label_brand_account();' not in live[live.index(endStart):]:
        candidateTail=candidate[candidate.index(endStart):]
        live=live[:live.index(endStart)]+candidateTail
    for needle in ('function white_label_brand_account','function white_label_logo_html',
                   'brand_identity_mode($ctx)','white_label_logo_file'):
        if needle not in live:raise RuntimeError('white_label_logo_contract_missing_'+needle)
    return live

def patch_index(live:str,candidate:str)->str:
    upload="if($path==='/brand-upload'){"
    logo="if($path==='/brand-logo'){"
    if upload not in live:
        live=live.replace(logo,select(candidate,upload,logo)+logo,1)
    if "brand_identity_brand($a)" not in live:
        begin=logo
        end="require_login();\nif($path==='/invitations')"
        live=live.replace(select(live,begin,end),select(candidate,begin,end),1)
    if 'brand_identity_upload_http($a)' not in live:
        raise RuntimeError('binary_upload_php_route_missing')
    return live

def patch_css(live:str,candidate:str)->str:
    if 'OTV_TENANT_STYLE_V63_END' not in live:
        raise RuntimeError('previous_tenant_theme_missing')
    delta=select(candidate,START,END)+END
    if START not in live and END not in live:
        live=live.rstrip('\n')+'\n\n'+delta+'\n'
    elif live.count(START)==1 and live.count(END)==1 and delta in live:
        pass
    else:
        raise RuntimeError('concurrent_logo_css_conflict')
    return live

def patch_js(live:str,candidate:str)->str:
    begin="  // Raw binary upload keeps the image outside multipart/form-data"
    end="  document.querySelectorAll('[data-toggle-password]')"
    if begin not in live:
        live=live.replace(end,select(candidate,begin,end)+end,1)
    if "fetch('/saas/console/brand-upload'" not in live:
        raise RuntimeError('raw_binary_upload_js_missing')
    return live
def atomic_write(path:Path,content:bytes,mode:int)->None:
    handle,tmp=tempfile.mkstemp(dir=str(path.parent),prefix='.ot-brand64-')
    try:
        with os.fdopen(handle,'wb') as f:
            f.write(content);f.flush();os.fsync(f.fileno())
        os.chmod(tmp,mode)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

def lint_php(path:str,content:bytes)->None:
    fd,temp=tempfile.mkstemp(dir=str(STAGE),suffix='.php')
    try:
        with os.fdopen(fd,'wb') as f:f.write(content)
        check=subprocess.run([PHP,'-l',temp],capture_output=True,timeout=20)
        if check.returncode!=0:
            raise RuntimeError('php_lint_failed_'+path+'_'+check.stderr.decode('utf-8','replace')[:400])
    finally:
        os.unlink(temp)

def main()->None:
    if os.getuid()!=2379 or ROOT.is_symlink() or not ROOT.is_dir() or STAGE.is_symlink() or not STAGE.is_dir():
        raise RuntimeError('unauthorized_host')
    if stat.S_IMODE(STAGE.stat().st_mode)!=0o700:raise RuntimeError('staging_not_private')
    lock=STAGE/'deploy.lock'
    with lock.open('a') as fp:
        fcntl.flock(fp,fcntl.LOCK_EX|fcntl.LOCK_NB)
        planned={}
        for rel in FILES:
            path=ROOT/rel
            if path.is_symlink() or path.parent.is_symlink():
                raise RuntimeError('destination_symlink_forbidden')
            candidate=read(STAGE/'new'/rel)
            exists=path.exists()
            if rel=='_core/BrandIdentity.php':
                before=read(path) if exists else ''
                if before and before!=candidate:raise RuntimeError('existing_brand_module_conflict')
                after=candidate
            else:
                before=read(path)
                if rel=='_core/Views.php':after=patch_views(before,candidate)
                elif rel=='_core/Actions.php':after=patch_actions(before,candidate)
                elif rel=='_core/Bootstrap.php':after=patch_bootstrap(before,candidate)
                elif rel=='_core/WhiteLabel.php':after=patch_white_label(before,candidate)
                elif rel=='index.php':after=patch_index(before,candidate)
                elif rel=='assets/css/app.css':after=patch_css(before,candidate)
                elif rel=='assets/js/app.js':after=patch_js(before,candidate)
                else:raise RuntimeError('unknown_path')
            encoded=after.encode('utf-8')
            if rel.endswith('.php'):
                if after.count('<?php')!=1:raise RuntimeError('invalid_php_tag_count_'+rel)
                lint_php(rel,encoded)
            mode=stat.S_IMODE(path.stat().st_mode) if exists else 0o644
            planned[rel]=(path,path.read_bytes() if exists else b'',encoded,mode,exists)
        backup=STAGE/('backup-'+str(int(time.time()))+'-'+str(os.getpid()))
        backup.mkdir(mode=0o700)
        modified=[]
        try:
            for rel,(path,old,new,mode,exists) in planned.items():
                if exists:
                    dst=backup/rel
                    dst.parent.mkdir(parents=True,exist_ok=True)
                    dst.write_bytes(old)
                    dst.chmod(0o600)
            for rel,(path,old,new,mode,exists) in planned.items():
                if path.exists()!=exists:raise RuntimeError('concurrent_file_presence_change_'+rel)
                if exists and digest(path.read_bytes())!=digest(old):
                    raise RuntimeError('concurrent_file_edit_'+rel)
                if old!=new:
                    atomic_write(path,new,mode)
                    modified.append(rel)
                if digest(path.read_bytes())!=digest(new):
                    raise RuntimeError('publish_integrity_failure_'+rel)
            check=subprocess.run([PHP,'-r',
                'require '+json.dumps(str(ROOT/'_core/Bootstrap.php'))+';'
                'init_schema();'
                'require_once '+json.dumps(str(ROOT/'_core/BrandIdentity.php'))+';'
                'brand_identity_init();'
                '$r=one("SELECT COUNT(*) AS n FROM brand_identity_settings");'
                'if(!isset($r["n"]))throw new Exception("brand_schema_absent");'
                '$p=one("SELECT * FROM accounts WHERE kind=? LIMIT 1",["platform"]);'
                'if(!$p || !isset(brand_identity_brand($p)["name"]))'
                'throw new Exception("platform_brand_not_readable");'
                'echo "BRAND_V64_LIVE_PRIVATE_LOGO_AND_MODE_SCHEMA_PASS";'],
                capture_output=True,timeout=75)
            if check.returncode!=0 or b'BRAND_V64_LIVE_PRIVATE_LOGO_AND_MODE_SCHEMA_PASS' not in check.stdout:
                raise RuntimeError('live_brand_mode_migration_failed_'+check.stderr.decode('utf-8','replace')[:500])
            print(check.stdout.decode('utf-8','replace').strip(),flush=True)
            print(json.dumps({'status':'PUBLISHED','changed':modified,
               'backup':str(backup),'hashes':{rel:digest(item[2]) for rel,item in planned.items()},
               'database':'brand-logo-display-additive-only','signed_apk_unchanged':True,
               'payment_and_customer_rows_unchanged':True},separators=(',',':')),flush=True)
        except BaseException:
            for rel in reversed(modified):
                path,old,new,mode,exists=planned[rel]
                if exists:atomic_write(path,old,mode)
                elif path.exists():path.unlink()
            raise

if __name__=='__main__':main()
