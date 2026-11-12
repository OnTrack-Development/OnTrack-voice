#!/usr/bin/env python3
"""Direct, rollback-safe delivery of Arabic typography to OnTrack production.

Four files (1 additive module, 2 narrowly patched PHP, 1 additive CSS).
The existing billing/customer/SIM logic and signed APK are untouched.
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
STAGE=Path('/home/appontrack/.ontrack-mobile/saas-typography-v62')
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
FILES=['_core/ConsoleTypography.php','_core/Actions.php','_core/Views.php','assets/css/app.css']
START='/* OTV_TYPO_V62_BEGIN'
END='/* OTV_TYPO_V62_END */'

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
    # Insert a font code read into the main layout; never change another view.
    head='function layout_open(array $a,string $tab):void{\n'
    next_line='    $u=me();'
    new_head=select(candidate,head,next_line)
    if '    $accountFont=console_font_effective($a);' not in live:
        if live.count(head)!=1:raise RuntimeError('main_layout_header_changed')
        live=live.replace(head,new_head,1)
    body_old="</head><body>';"
    body_new = """</head><body data-ot-font="'.e($accountFont).'">';"""
    if body_new not in live:
        if live.count(body_old)!=1:raise RuntimeError('layout_body_unchanged_anchor_missing')
        live=live.replace(body_old,body_new,1)

    # The settings page has a stable entry point. The new section is the
    # only change: it precedes the existing MFA/brand/provider settings.
    settings_start='function render_settings(array $a):void{\n'
    settings_end='    // Owner decision 2026-11-04: no administrator 2FA/QR setup tile.'
    section=select(candidate,settings_start,settings_end)
    if "form_start('save_console_font'" not in live:
        old=select(live,settings_start,settings_end)
        live=live.replace(old,section,1)

    login_start='    $portal=white_label_context();\n'
    login_end='    $name=(string)$brand[\'name\'];'
    new_login=select(candidate,login_start,login_end)
    if "$portalFont=$portal?console_font_tenant" not in live:
        old_login=select(live,login_start,login_end)
        live=live.replace(old_login,new_login,1)
    body_login_old='<body class="login-body\'.($portal?\' white-label-auth\':\'\').\'">'
    body_login_new='<body data-ot-font="\'.e($portalFont).\'" class="login-body\'.($portal?\' white-label-auth\':\'\').\'">'
    if body_login_new not in live:
        if live.count(body_login_old)!=1:
            raise RuntimeError('white_label_login_body_changed')
        live=live.replace(body_login_old,body_login_new,1)
    for item in ('console_font_effective($a)', 'data-ot-font',
                 "form_start('save_console_font'", 'console_font_tenant'):
        if item not in live:raise RuntimeError('missing_font_view_contract_'+item)
    return live

def patch_actions(live:str,candidate:str)->str:
    begin="            case 'save_console_font':"
    end="            case 'platform_resellers_visibility':"
    addon=select(candidate,begin,end)
    if begin not in live:
        if live.count(end)!=1:raise RuntimeError('actions_font_anchor_changed')
        live=live.replace(end,addon+end,1)
    if "console_font_save($a,$chosen,$scope)" not in live:
        raise RuntimeError('font_action_handler_missing')
    return live

def patch_css(live:str,candidate:str)->str:
    if not live.startswith('@charset "UTF-8";'):
        raise RuntimeError('unexpected_live_css_header')
    if 'OTV_STYLE_V61_END' not in live:
        raise RuntimeError('previously_validated_v61_style_missing')
    if candidate.count(START)!=1 or candidate.count(END)!=1:
        raise RuntimeError('candidate_font_block_invalid')
    new_css=select(candidate,START,END)+END
    remote='@import url("https://fonts.googleapis.com/css2?family=Cairo:'
    import_line=next((x for x in candidate.splitlines() if x.startswith(remote)),None)
    if import_line is None or 'IBM+Plex+Sans+Arabic' not in import_line or 'Tajawal' not in import_line:
        raise RuntimeError('three_real_fonts_not_imported')
    if START not in live and END not in live:
        live=live.rstrip('\n')+'\n\n'+new_css+'\n'
    elif live.count(START)==1 and live.count(END)==1:
        if new_css not in live:raise RuntimeError('concurrent_font_style_conflict')
    else:
        raise RuntimeError('incomplete_font_stylesheet_live')
    if import_line not in live:
        live=live.replace('@charset "UTF-8";','@charset "UTF-8";\n'+import_line,1)
    if live.count(import_line)!=1 or live.count(START)!=1:
        raise RuntimeError('import_duplication_or_missing_font_rules')
    return live

def atomic_write(path:Path,content:bytes,mode:int)->None:
    handle,tmp=tempfile.mkstemp(dir=str(path.parent),prefix='.ot-typo62-')
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
            if rel=='_core/ConsoleTypography.php':
                before=read(path) if exists else ''
                if before and before!=candidate:raise RuntimeError('existing_font_module_conflict')
                after=candidate
            else:
                before=read(path)
                if rel=='_core/Views.php':after=patch_views(before,candidate)
                elif rel=='_core/Actions.php':after=patch_actions(before,candidate)
                elif rel=='assets/css/app.css':after=patch_css(before,candidate)
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
                'require_once '+json.dumps(str(ROOT/'_core/ConsoleTypography.php'))+';'
                'console_font_init();'
                '$r=one("SELECT COUNT(*) AS n FROM console_font_preferences");'
                'if(!isset($r["n"]))throw new Exception("typography_schema_not_ready");'
                'echo "TYPO_V62_LIVE_ACCOUNT_PREFERENCES_PASS";'],
                capture_output=True,timeout=70)
            if check.returncode!=0 or b'TYPO_V62_LIVE_ACCOUNT_PREFERENCES_PASS' not in check.stdout:
                raise RuntimeError('live_font_migration_failed_'+check.stderr.decode('utf-8','replace')[:500])
            print(check.stdout.decode('utf-8','replace').strip(),flush=True)
            print(json.dumps({'status':'PUBLISHED','changed':modified,
               'backup':str(backup),'hashes':{rel:digest(item[2]) for rel,item in planned.items()},
               'database':'font-preferences-additive-only','signed_apk_unchanged':True,
               'payment_and_customer_rows_unchanged':True},separators=(',',':')),flush=True)
        except BaseException:
            for rel in reversed(modified):
                path,old,new,mode,exists=planned[rel]
                if exists:atomic_write(path,old,mode)
                elif path.exists():path.unlink()
            raise

if __name__=='__main__':main()
