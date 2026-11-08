#!/usr/bin/env python3
"""Direct rollback-safe tenant brand/theme update, five narrowly patched files.
Preserves prior billing, customer, SIM and typography files and APK.
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
STAGE=Path('/home/appontrack/.ontrack-mobile/saas-theme-v63')
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
FILES=['_core/ConsoleTheme.php','_core/Actions.php','_core/Views.php','assets/css/app.css','assets/js/app.js']
START='/* OTV_TENANT_STYLE_V63_BEGIN'
END='/* OTV_TENANT_STYLE_V63_END */'

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
    if '    $themeVars=console_theme_attribute((int)$a[\'account_id\']);' not in live:
        start="    $accountFont=console_font_effective($a);"
        insert=select(candidate,start,"    $u=me();")
        old=select(live,start,"    $u=me();")
        live=live.replace(old,insert,1)
    old='</head><body data-ot-font="\'.e($accountFont).\'">\';'
    new='</head><body data-ot-font="\'.e($accountFont).\'" data-ot-account="\'.(int)$a[\'account_id\'].\'"\'.$themeVars.\'>';'
    if new not in live:
        if live.count(old)!=1:raise RuntimeError('tenant_dashboard_body_anchor_changed')
        live=live.replace(old,new,1)
    # Update reseller login identity/theme in-place.
    loginStart="    $portalFont=$portal?console_font_tenant((int)$portal['id']):'plex';"
    loginEnd="    $brand=white_label_identity();"
    section=select(candidate,loginStart,loginEnd)
    if '$portalTheme=console_theme_attribute(' not in live:
        live=live.replace(select(live,loginStart,loginEnd),section,1)
    loginOld='<body data-ot-font="\'.e($portalFont).\'" class="login-body'
    loginNew='<body data-ot-font="\'.e($portalFont).\'" data-ot-account="\'.($portal?(int)$portal[\'id\']:0).\'"\'.$portalTheme.\' class="login-body'
    if loginNew not in live:
        if live.count(loginOld)!=1:raise RuntimeError('tenant_auth_body_anchor_changed')
        live=live.replace(loginOld,loginNew,1)
    # Use a unique independent renderer to avoid touching other settings.
    formMarker='function render_console_theme_settings(array $a):void {'
    settingsMarker='function render_settings(array $a):void{'
    newRenderer=select(candidate,formMarker,settingsMarker)
    if formMarker not in live:
        if live.count(settingsMarker)!=1:raise RuntimeError('theme_settings_location_changed')
        live=live.replace(settingsMarker,newRenderer+settingsMarker,1)
    if '    render_console_theme_settings($a);' not in live:
        if live.count(settingsMarker)!=1:raise RuntimeError('settings_body_anchor_changed')
        live=live.replace(settingsMarker,settingsMarker+'\n    render_console_theme_settings($a);',1)
    for marker in ('console_theme_attribute','data-ot-account',
       'function render_console_theme_settings','data-ot-palette-editor',
       'data-ot-palette-preview','reset_console_theme'):
        if marker not in live:raise RuntimeError('theme_view_contract_missing_'+marker)
    return live

def patch_actions(live:str,candidate:str)->str:
    begin="            case 'save_console_theme':"
    end="            case 'save_console_font':"
    addon=select(candidate,begin,end)
    if begin not in live:
        if live.count(end)!=1:raise RuntimeError('tenant_theme_actions_anchor_missing')
        live=live.replace(end,addon+end,1)
    for needle in ("case 'save_console_theme':", "case 'reset_console_theme':",
                   'console_theme_write($a,$palette,false)'):
        if needle not in live:raise RuntimeError('tenant_theme_action_missing_'+needle)
    return live

def patch_css(live:str,candidate:str)->str:
    if not live.startswith('@charset "UTF-8";'):
        raise RuntimeError('CSS_header_changed')
    if 'OTV_TYPO_V62_END' not in live or 'OTV_STYLE_V61_END' not in live:
        raise RuntimeError('typography_or_precision_styles_missing')
    addon=select(candidate,START,END)+END
    if live.count(START)==0 and live.count(END)==0:
        return live.rstrip('\n')+'\n\n'+addon+'\n'
    if live.count(START)==1 and live.count(END)==1 and addon in live:
        return live
    raise RuntimeError('concurrent_theme_palette_css_conflict')

def patch_js(live:str,candidate:str)->str:
    head="  const body=document.body;"
    firstEnd="  const setTheme=(mode)=>{"
    if "themeStorageKey='ontrack-console-theme-'" not in live:
        old=select(live,head,firstEnd)
        updated=select(candidate,head,firstEnd)
        live=live.replace(old,updated,1)
    prev="setTheme(next);try{localStorage.setItem('ontrack-console-theme',next)}catch{}"
    updated="setTheme(next);try{localStorage.setItem(themeStorageKey,next)}catch{}"
    if prev in live:live=live.replace(prev,updated,1)
    sectionStart="  // v63: draft preview is local to the settings card until explicitly saved."
    sectionEnd="  document.querySelectorAll('[data-toggle-password]')"
    if sectionStart not in live:
        if live.count(sectionEnd)!=1:raise RuntimeError('JS_preview_insert_anchor_missing')
        code=select(candidate,sectionStart,sectionEnd)
        live=live.replace(sectionEnd,code+sectionEnd,1)
    for marker in ('themeStorageKey','setUpPaletteEditor','preview.style.setProperty',
                   'layout.value===\'single\''):
        if marker not in live:raise RuntimeError('missing_preview_js_'+marker)
    return live

def atomic_write(path:Path,content:bytes,mode:int)->None:
    handle,tmp=tempfile.mkstemp(dir=str(path.parent),prefix='.ot-theme63-')
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
            if rel=='_core/ConsoleTheme.php':
                before=read(path) if exists else ''
                if before and before!=candidate:raise RuntimeError('existing_font_module_conflict')
                after=candidate
            else:
                before=read(path)
                if rel=='_core/Views.php':after=patch_views(before,candidate)
                elif rel=='_core/Actions.php':after=patch_actions(before,candidate)
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
                'require_once '+json.dumps(str(ROOT/'_core/ConsoleTheme.php'))+';'
                'console_theme_init();'
                '$r=one("SELECT COUNT(*) AS n FROM console_theme_palettes");'
                'if(!isset($r["n"]))throw new Exception("theme_schema_not_ready");'
                '$p=console_theme_effective(1);'
                'if(!isset($p["light_content"]))throw new Exception("theme_token_missing");'
                'echo "THEME_V63_LIVE_TENANT_PALETTE_SCHEMA_PASS";'],
                capture_output=True,timeout=70)
            if check.returncode!=0 or b'THEME_V63_LIVE_TENANT_PALETTE_SCHEMA_PASS' not in check.stdout:
                raise RuntimeError('live_theme_migration_failed_'+check.stderr.decode('utf-8','replace')[:500])
            print(check.stdout.decode('utf-8','replace').strip(),flush=True)
            print(json.dumps({'status':'PUBLISHED','changed':modified,
               'backup':str(backup),'hashes':{rel:digest(item[2]) for rel,item in planned.items()},
               'database':'tenant-theme-additive-only','signed_apk_unchanged':True,
               'payment_and_customer_rows_unchanged':True},separators=(',',':')),flush=True)
        except BaseException:
            for rel in reversed(modified):
                path,old,new,mode,exists=planned[rel]
                if exists:atomic_write(path,old,mode)
                elif path.exists():path.unlink()
            raise

if __name__=='__main__':main()
