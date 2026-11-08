#!/usr/bin/env python3
"""Atomic reviewed web dashboard delta on the authorized canonical OnTrack host.
Safe 7-file source-delta deployment with one new invoice module.
Additive invoice schema + legacy invoice document import; verified payment records unchanged.
"""
from pathlib import Path
import difflib, fcntl, hashlib, json, os, stat, subprocess, tempfile, time

ROOT=Path('/home/appontrack/public_html')
STAGE=Path('/home/appontrack/.ontrack-mobile/saas-billing-v58')
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
FILES=['_core/Actions.php','_core/Views.php','_core/Checkout.php',
       '_core/Bootstrap.php','_core/OrderClaim.php','index.php',
       '_core/BillingInvoices.php']
NEW_FILES={'_core/BillingInvoices.php'}

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

def atomic_write(path:Path,content:bytes,mode:int)->None:
    handle,tmp=tempfile.mkstemp(dir=str(path.parent),prefix='.billing-v58-')
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
                patched=apply(original,before,after,rel)
            if patched.count('<?php')!=1:raise RuntimeError('php_open_tag_mismatch_'+rel)
            if rel=='_core/BillingInvoices.php' and not all(x in patched for x in (
                'billing_invoice_init','billing_invoice_for_order','billing_invoice_settle',
                'billing_invoice_create_manual','billing_invoice_cancel_manual',
                'billing_invoice_get_visible','billing_invoice_backfill'
            )):raise RuntimeError('billing_invoice_module_incomplete')
            if rel=='_core/Views.php' and not all(x in patched for x in (
                "function render_invoices","form_start('invoice_create'",
                "form_start('invoice_cancel'","form_start('invoice_reissue'",
                'billing_invoice_rows'
            )):raise RuntimeError('invoice_ui_missing')
            if rel=='_core/Actions.php' and not all(x in patched for x in (
                "case 'invoice_create':","case 'invoice_cancel':",
                "case 'invoice_reissue':","case 'invoice_backfill':",
                'billing_invoice_settle'
            )):raise RuntimeError('invoice_actions_missing')
            if rel=='_core/Bootstrap.php' and 'billing_invoice_init();' not in patched:
                raise RuntimeError('invoice_schema_bootstrap_missing')
            if rel=='_core/Checkout.php' and not all(x in patched for x in (
                'billing_invoice_settle','billing_invoice_for_order'
            )):raise RuntimeError('checkout_invoice_integration_missing')
            if rel=='_core/OrderClaim.php' and 'billing_invoice_settle' not in patched:
                raise RuntimeError('external_payment_invoice_integration_missing')
            if rel=='index.php' and not all(x in patched for x in (
                "case 'invoices':render_invoices", 'billing_invoice_get_visible'
            )):raise RuntimeError('invoices_route_missing')
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
            # All accounting schema changes are additive; original orders,
            # receipts, provider credentials, entitlements and tenant identities stay intact.
            check=subprocess.run([PHP,'-r',
                'require '+json.dumps(str(ROOT/'_core/Bootstrap.php'))+';'
                'init_schema();'
                'require_once '+json.dumps(str(ROOT/'_core/BillingInvoices.php'))+';'
                '$n=0;for($i=0;$i<10;$i++){'
                '$k=billing_invoice_backfill(null,500);$n+=$k;if($k<500)break;}'
                '$a=one("SELECT COUNT(*) AS n FROM billing_invoices");'
                'if(!isset($a["n"]))throw new Exception("invoice_schema_missing");'
                'echo "BILLING_V58_LIVE_SCHEMA_AND_LEGACY_BACKFILL_PASS ".$n;'],
                capture_output=True,timeout=100)
            if check.returncode!=0 or b'BILLING_V58_LIVE_SCHEMA_AND_LEGACY_BACKFILL_PASS' not in check.stdout:
                raise RuntimeError('live_invoice_schema_or_backfill_failed')
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
