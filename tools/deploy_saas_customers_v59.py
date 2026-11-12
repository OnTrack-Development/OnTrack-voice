#!/usr/bin/env python3
"""Atomic reviewed web dashboard delta on the authorized canonical OnTrack host.
Safe 3-file source-delta deployment with one new customer module.
Additive customer profile schema, existing invoices, orders and payments unchanged.
"""
from pathlib import Path
import difflib, fcntl, hashlib, json, os, stat, subprocess, tempfile, time

ROOT=Path('/home/appontrack/public_html')
STAGE=Path('/home/appontrack/.ontrack-mobile/saas-customers-v59')
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
FILES=['_core/Actions.php','_core/Views.php','_core/CustomerAccounts.php']
NEW_FILES={'_core/CustomerAccounts.php'}

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

def patch_customer_views(live:str,candidate:str)->str:
    # The live console is also updated by other features; only touch exact
    # anchors in the accounts view, preserving neighboring production UI.
    start=live.find('function render_accounts(array $a):void{')
    end=live.find('function render_plans(array $a):void{',start)
    if start<0 or end<=start:raise RuntimeError('accounts_view_anchors_missing')
    account=live[start:end]
    header="function render_accounts(array $a):void{"
    if 'render_customer_detail($a,$selected)' not in account:
        injection="""function render_accounts(array $a):void{
    require_once __DIR__.'/CustomerAccounts.php';
    $selected=max(0,(int)($_GET['customer_id']??0));
    if($selected>0){render_customer_detail($a,$selected);return;}"""
        if account.count(header)!=1:raise RuntimeError('accounts_header_missing')
        account=account.replace(header,injection,1)
    create="    echo card('إنشاء حساب جديد',form_start('create_account','/?tab=accounts')."
    create_new="""    if(in_array($a['role'],['owner','admin'],true)&&!active_support()&&
       console_view_mode($a)==='admin')echo card('إنشاء حساب جديد',form_start('create_account','/?tab=accounts')."""
    if create_new not in account:
        if account.count(create)!=1:raise RuntimeError('customer_create_form_conflict')
        account=account.replace(create,create_new,1)
    # Add profile + archive recovery after the already-deployed management forms.
    before='        $current=one("SELECT p.id plan_id'
    quick="""        $customerQuickId=(int)$r['id'];
        $customerQuickLink='<a class="btn small primary" href="/?tab=accounts&amp;customer_id='.$customerQuickId.'">ملف العميل</a>';
        if($r['status']==='archived'){
            $support=$customerQuickLink.
                form_start('customer_restore','/?tab=accounts','class="inline-form"').
                '<input type="hidden" name="customer_id" value="'.$customerQuickId.'">'.
                '<button class="btn small" type="submit">استعادة الحساب</button></form>';
        }else{
            $support=$customerQuickLink.$support;
        }
"""
    if '$customerQuickLink=' not in account:
        if account.count(before)!=1:raise RuntimeError('account_row_quick_link_anchor_conflict')
        account=account.replace(before,quick+before,1)
    detail_start=candidate.find('function render_customer_detail(array $actor,int $customerId):void {')
    detail_end=candidate.find('function render_plans(array $a):void{',detail_start)
    if detail_start<0 or detail_end<detail_start:raise RuntimeError('candidate_customer_detail_missing')
    detail=candidate[detail_start:detail_end]
    if 'function render_customer_detail(' not in account:account+=detail
    live=live[:start]+account+live[end:]
    return live

def atomic_write(path:Path,content:bytes,mode:int)->None:
    handle,tmp=tempfile.mkstemp(dir=str(path.parent),prefix='.customers-v59-')
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
                patched=patch_customer_views(original,after) if rel=='_core/Views.php' else apply(original,before,after,rel)
            if patched.count('<?php')!=1:raise RuntimeError('php_open_tag_mismatch_'+rel)
            if rel=='_core/CustomerAccounts.php' and not all(x in patched for x in (
                'customer_accounts_scope','customer_accounts_update',
                'customer_accounts_change_login','customer_accounts_status',
                'customer_accounts_delete','customer_accounts_restore',
                'customer_accounts_has_dependencies','customer_accounts_metrics'
            )):raise RuntimeError('customer_lifecycle_module_incomplete')
            if rel=='_core/Actions.php' and not all(x in patched for x in (
                "case 'create_account':","case 'customer_profile_save':",
                "case 'customer_owner_email':","case 'customer_delete':",
                "case 'customer_restore':","case 'set_account_status':"
            )):raise RuntimeError('customer_actions_missing')
            if rel=='_core/Views.php' and not all(x in patched for x in (
                'function render_customer_detail',
                "form_start('customer_profile_save'",
                "form_start('customer_owner_email'",
                "form_start('customer_delete'",
                "form_start('customer_restore'",
                'customer_accounts_metrics'
            )):raise RuntimeError('customer_profile_ui_missing')
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
            # Verify customer profile schema; no production customer mutations.
            check=subprocess.run([PHP,'-r',
                'require '+json.dumps(str(ROOT/'_core/Bootstrap.php'))+';'
                'init_schema();'
                'require_once '+json.dumps(str(ROOT/'_core/CustomerAccounts.php'))+';'
                'customer_accounts_init();'
                '$r=one("SELECT COUNT(*) AS n FROM customer_profiles");'
                'if(!isset($r["n"]))throw new Exception("customer_profile_schema_missing");'
                'echo "CUSTOMER_V59_LIVE_SCHEMA_PASS";'],
                capture_output=True,timeout=50)
            if check.returncode!=0 or b'CUSTOMER_V59_LIVE_SCHEMA_PASS' not in check.stdout:
                raise RuntimeError('live_customer_schema_validation_failed')
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
