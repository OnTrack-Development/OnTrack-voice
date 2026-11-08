#!/usr/bin/env python3
"""Atomic reviewed web dashboard delta on the authorized canonical OnTrack host.
Slot-specific SIM pairing and elimination of customer-issued API Key UI.
No migration; existing payment records and third-party provider credentials unchanged.
"""
from pathlib import Path
import difflib, fcntl, hashlib, json, os, stat, subprocess, tempfile, time

ROOT=Path('/home/appontrack/public_html')
STAGE=Path('/home/appontrack/.ontrack-mobile/saas-sim-v60')
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
FILES=['_core/Actions.php','_core/Views.php','_core/DevicePairing.php']
NEW_FILES=set()

def checksum(data:bytes)->str:
    return hashlib.sha256(data).hexdigest()

def source(path:Path)->str:
    if path.is_symlink() or not path.is_file():raise RuntimeError('source_missing_or_symlinked_'+path.name)
    data=path.read_bytes()
    if len(data)>1_000_000 or b'\0' in data:raise RuntimeError('invalid_source_bytes_'+path.name)
    return data.decode('utf-8-sig').replace('\r\n','\n')

def apply(live:str,old:str,new:str,label:str)->str:
    before=old.splitlines(keepends=True)
    after=new.splitlines(keepends=True)
    ops=difflib.SequenceMatcher(None,before,after,autojunk=False).get_opcodes()
    for kind,i1,i2,j1,j2 in reversed(ops):
        if kind=='equal':continue
        original=''.join(before[i1:i2])
        updated=''.join(after[j1:j2])
        if original and live.count(original)==1:
            live=live.replace(original,updated,1);continue
        if updated and updated in live and (not original or original not in live):
            continue
        fixed=False
        for depth in range(1,15):
            left=''.join(before[max(0,i1-depth):i1])
            right=''.join(before[i2:i2+depth])
            needle=left+original+right
            replacement=left+updated+right
            if needle and live.count(needle)==1:
                live=live.replace(needle,replacement,1)
                fixed=True;break
            if replacement and replacement in live and needle not in live:
                fixed=True;break
        if not fixed:raise RuntimeError('unexpected_live_edit_'+label+'_'+str(i1))
    return live

def patch_sim_views(live:str,candidate:str)->str:
    # Never replace unrelated live views (customer management, billing, theme).
    nav_start=live.find('function nav_entries(string $type):array{')
    nav_end=live.find('function layout_open(array $a,string $tab):void{',nav_start)
    if nav_start<0 or nav_end<=nav_start:raise RuntimeError('live_nav_boundaries_missing')
    nav=live[nav_start:nav_end]
    for legacy in (
        "        'keys'=>['⌘','مفاتيح API'],\\n",
    ):
        nav=nav.replace(legacy,'')
    nav=nav.replace("'keys'=>['⌘','مفاتيح API'],'integrations'=>","'integrations'=>")
    nav=nav.replace("        'plans'=>['◫','الخطط والحدود'],\\n        'billing'=>",
      "        'plans'=>['◫','الخطط والحدود'],\\n        'devices'=>['▣','الأجهزة والخطوط'],\\n        'billing'=>")
    nav=nav.replace("        'plans'=>['◫','باقات العملاء'],\\n        'billing'=>",
      "        'plans'=>['◫','باقات العملاء'],\\n        'devices'=>['▣','الأجهزة والخطوط'],\\n        'billing'=>")
    if "'keys'=>['⌘','مفاتيح API']" in nav:
        raise RuntimeError('customer_issued_keys_still_in_navigation')
    if nav.count("'devices'=>['▣','الأجهزة والخطوط']")<2:
        raise RuntimeError('platform_and_reseller_device_navigation_missing')
    live=live[:nav_start]+nav+live[nav_end:]

    # Existing official native v55 release already has SIM 1/2 code input.
    if 'function render_sim_pair_quick_start' not in live or (
        'ربط SIM 1 الآن' not in live or 'ربط SIM 2 الآن' not in live
    ):raise RuntimeError('live_six_digit_pair_buttons_missing')

    # Replace only the retired keys view with an informational landing.
    key_start='function render_keys(array $a):void{'
    key_end='function render_integrations(array $a):void{'
    def section(source,start,end):
        left=source.find(start);right=source.find(end,left+len(start))
        if left<0 or right<=left:raise RuntimeError('missing_section_'+start[:24])
        return left,right,source[left:right]
    l,r,_=section(live,key_start,key_end)
    _,_,new_keys=section(candidate,'function render_keys(array $a):void {',key_end)
    live=live[:l]+new_keys+live[r:]

    # Copy existing six-digit flash block from the candidate without touching
    # the auth layout, CSS, white-label, or the invoice/customer screens.
    flash_start="    if($pairCode=flash('device_pair_code')){"
    flash_end='    // Synthetic rows retain explicit DEMO marks;'
    l,r,_=section(live,flash_start,flash_end)
    _,_,new_flash=section(candidate,flash_start,flash_end)
    live=live[:l]+new_flash+live[r:]

    old='<a href="/saas/console/?tab=keys"><b>⌘</b><span>API Keys</span><small>مفاتيح مسمّاة وقابلة للإلغاء</small></a>'
    updated='<a href="/saas/console/?tab=devices"><b>▣</b><span>ربط الشرائح</span><small>SIM 1 / SIM 2 بكود 6 أرقام</small></a>'
    if old in live:live=live.replace(old,updated)
    if old in live or 'data-wa-copy="ot-sim-six-copy"' not in live:
        raise RuntimeError('retired_keys_shortcut_or_six_digit_copy_missing')
    return live

def atomic_write(path:Path,content:bytes,mode:int)->None:
    handle,tmp=tempfile.mkstemp(dir=str(path.parent),prefix='.sim-v60-')
    try:
        with os.fdopen(handle,'wb') as fh:
            fh.write(content);fh.flush();os.fsync(fh.fileno())
        os.chmod(tmp,mode)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

def php_lint(content:bytes,name:str)->None:
    fd,tmp=tempfile.mkstemp(dir=str(STAGE),suffix='.php')
    try:
        with os.fdopen(fd,'wb') as fh:fh.write(content)
        result=subprocess.run([PHP,'-l',tmp],capture_output=True,timeout=18)
        if result.returncode!=0:raise RuntimeError('php_lint_failed_'+name)
    finally:os.unlink(tmp)

def main():
    if os.getuid()!=2379 or ROOT.is_symlink() or not ROOT.is_dir() or STAGE.is_symlink() or not STAGE.is_dir():
        raise RuntimeError('unauthorized_host_or_staging')
    if stat.S_IMODE(STAGE.stat().st_mode)!=0o700:raise RuntimeError('private_staging_mode_not_0700')
    releases=ROOT/'mobile/updates/native/latest.json'
    if releases.is_symlink() or not releases.is_file():raise RuntimeError('live_public_native_manifest_absent')
    meta=json.loads(releases.read_text())
    payload=json.loads(meta['payload'])
    if int(payload.get('version_code',0))<54 or not (ROOT/'mobile/updates/native'/payload['file']).is_file():
        raise RuntimeError('signed_v54_apk_not_found_in_public_ota_directory')

    lock=STAGE/'publisher.lock'
    with lock.open('a') as fh:
        fcntl.flock(fh,fcntl.LOCK_EX|fcntl.LOCK_NB)
        plan={}
        for rel in FILES:
            destination=ROOT/rel
            if destination.is_symlink() or destination.parent.is_symlink():raise RuntimeError('unsafe_target_'+rel)
            after=source(STAGE/'new'/rel)
            if rel in NEW_FILES:
                if destination.exists() and source(destination)!=after:
                    raise RuntimeError('unexpected_preexisting_module_'+rel)
                original=source(destination) if destination.exists() else ''
                patched=after
            else:
                before=source(STAGE/'old'/rel)
                original=source(destination)
                patched=patch_sim_views(original,after) if rel=='_core/Views.php' else apply(original,before,after,rel)
            if patched.count('<?php')!=1:raise RuntimeError('php_open_tag_mismatch_'+rel)
            if rel=='_core/DevicePairing.php' and not all(x in patched for x in (
                "status='cancelled'",'device_pairing_create','device_pairing_redeem',
                'device_pairing_rate_guard','device_pairing_hash'
            )):raise RuntimeError('atomic_six_digit_pairing_incomplete')
            if rel=='_core/Actions.php' and not all(x in patched for x in (
                "case 'create_key':","deny(410,","flash('device_pair_slot'",
                "case 'create_device_pairing':"
            )):raise RuntimeError('sim_actions_or_disabled_keys_missing')
            if rel=='_core/Views.php' and not all(x in patched for x in (
                "function render_sim_pair_quick_start","function render_keys",
                "id=\"ot-sim-six-copy\"","data-wa-copy=\"ot-sim-six-copy\"",
                "'devices'=>['▣','الأجهزة والخطوط']"
            )):raise RuntimeError('slot_copy_or_navigation_missing')
            changed=patched.encode('utf-8')
            php_lint(changed,rel.replace('/','_'))
            exists=destination.exists()
            plan[rel]=(destination,destination.read_bytes() if exists else b'',changed,
                stat.S_IMODE(destination.stat().st_mode) if exists else 0o644,exists)
        backup=STAGE/('backup-'+str(int(time.time()))+'-'+str(os.getpid()))
        backup.mkdir(mode=0o700)
        touched=[]
        try:
            for rel,(_,original,_,_,existed) in plan.items():
                if not existed:continue
                p=backup/rel;p.parent.mkdir(parents=True,exist_ok=True)
                p.write_bytes(original);p.chmod(0o600)
            for rel,(dest,original,patched,mode,existed) in plan.items():
                if dest.exists()!=existed:raise RuntimeError('concurrent_file_presence_change_'+rel)
                if existed and checksum(dest.read_bytes())!=checksum(original):
                    raise RuntimeError('concurrent_host_edit_'+rel)
                if original!=patched:
                    atomic_write(dest,patched,mode);touched.append(rel)
                if checksum(dest.read_bytes())!=checksum(patched):
                    raise RuntimeError('after_publish_hash_mismatch_'+rel)
            # Verify the production version and exact on-disk PHP. Do not
            # issue a real short-lived pairing code or modify any account.
            check=subprocess.run([PHP,'-r',
                'require '+json.dumps(str(ROOT/'_core/Bootstrap.php'))+';'
                'init_schema();'
                '$v=file_get_contents('+json.dumps(str(ROOT/'_core/Views.php'))+');'
                '$a=file_get_contents('+json.dumps(str(ROOT/'_core/Actions.php'))+');'
                '$d=file_get_contents('+json.dumps(str(ROOT/'_core/DevicePairing.php'))+');'
                'if(!str_contains($v,"data-wa-copy")||!str_contains($a,"flash(\\\'device_pair_slot\\\'")'
                '||!str_contains($d,"status=\\\'cancelled\\\'"))'
                'throw new Exception("sim60_contract_missing");'
                'echo "SIM60_LIVE_BACKEND_AND_WEBSITE_PASS";'],
                capture_output=True,timeout=60)
            if check.returncode!=0 or b'SIM60_LIVE_BACKEND_AND_WEBSITE_PASS' not in check.stdout:
                raise RuntimeError('live_sim60_verification_failed')
            print(check.stdout.decode('utf-8',errors='replace').strip(),flush=True)
            print(json.dumps({'status':'PUBLISHED','changed':touched,
                'backup':str(backup),'tenant_identity_unchanged':True,
                'secrets_untouched':True,'provider_payment_records_untouched':True,'signed_native_version':payload['version_code'],
                'checksums':{rel:checksum(v[2]) for rel,v in plan.items()}},
                ensure_ascii=False,separators=(',',':')),flush=True)
        except BaseException:
            for rel in reversed(touched):
                dest,original,_,mode,existed=plan[rel]
                if existed:atomic_write(dest,original,mode)
                elif dest.exists():dest.unlink()
            raise

if __name__=='__main__':main()
