#!/usr/bin/env python3
"""One transactional-like file batch: v67 SaaS first-section logic repairs.
Single host and canonical directory, immutable rollback, no live DB mutations,
no APK changes, no scheduler activation, no secrets in the repository.
"""
from pathlib import Path
import difflib
import fcntl
import hashlib
import json
import os
import stat
import subprocess
import tempfile
import time

ROOT=Path('/home/appontrack/public_html')
STAGE=Path('/home/appontrack/.ontrack-mobile/saas-first-logic-v67')
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
FILES=[
    '_core/Checkout.php','_core/KashierWebhook.php',
    '_core/WhatsAppSupport.php','_core/WhatsAppNativeAgent.php',
    '_core/WhatsAppWhmcsBridge.php','_core/WhatsAppJobSafety.php',
    '_core/VoiceFlowRuntime.php',
    'whatsapp-worker.php','device/v1/index.php','_core/Actions.php','_core/Views.php'
]
NEW={'_core/WhatsAppJobSafety.php'}
MAX=1_600_000

def checksum(value:bytes)->str:
    return hashlib.sha256(value).hexdigest()

def read(path:Path)->str:
    if path.is_symlink() or not path.is_file():
        raise RuntimeError('missing_or_symlink_'+path.name)
    raw=path.read_bytes()
    if not raw or len(raw)>MAX or b'\x00' in raw:
        raise RuntimeError('invalid_data_'+path.name)
    return raw.decode('utf-8-sig').replace('\r\n','\n')

def merge(live:str,base:str,new:str,label:str)->str:
    # Preserve ALL unrelated live changes; apply only exact audited v67 hunks.
    operations=difflib.SequenceMatcher(None,
        base.splitlines(keepends=True),new.splitlines(keepends=True),
        autojunk=False).get_opcodes()
    oldLines=base.splitlines(keepends=True)
    newLines=new.splitlines(keepends=True)
    for kind,i1,i2,j1,j2 in reversed(operations):
        if kind=='equal':continue
        original=''.join(oldLines[i1:i2])
        updated=''.join(newLines[j1:j2])
        if original and live.count(original)==1:
            live=live.replace(original,updated,1)
            continue
        if updated and updated in live and (not original or original not in live):
            continue
        found=False
        for depth in range(1,27):
            left=''.join(oldLines[max(0,i1-depth):i1])
            right=''.join(oldLines[i2:i2+depth])
            needle=left+original+right
            replace=left+updated+right
            if needle and live.count(needle)==1:
                live=live.replace(needle,replace,1)
                found=True
                break
            if replace and replace in live and (not needle or needle not in live):
                found=True
                break
        if not found:raise RuntimeError('live_patch_conflict_'+label+'_hunk_'+str(i1))
    return live

def atomic(path:Path,raw:bytes,mode:int)->None:
    fd,temp=tempfile.mkstemp(prefix='.ontrack-v67-',dir=str(path.parent))
    try:
        with os.fdopen(fd,'wb') as handle:
            handle.write(raw);handle.flush();os.fsync(handle.fileno())
        os.chmod(temp,mode);os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)

def lint(label:str,raw:bytes)->None:
    fd,temp=tempfile.mkstemp(prefix='.v67-lint-',suffix='.php',dir=str(STAGE))
    try:
        with os.fdopen(fd,'wb') as handle:handle.write(raw)
        check=subprocess.run([PHP,'-l',temp],capture_output=True,timeout=25)
        if check.returncode!=0:
            raise RuntimeError('php_lint_invalid_'+label)
    finally:
        Path(temp).unlink(missing_ok=True)

def main()->None:
    if (os.getuid()!=2379 or ROOT.is_symlink() or STAGE.is_symlink()
        or not ROOT.is_dir() or not STAGE.is_dir()
        or stat.S_IMODE(STAGE.stat().st_mode)!=0o700):
        raise RuntimeError('unauthorized_production_host_or_stage')
    with (STAGE/'deploy.lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        plan={}
        for rel in FILES:
            dst=ROOT/rel
            if dst.is_symlink() or dst.parent.is_symlink() or dst.parent.parent.is_symlink():
                raise RuntimeError('unsafe_destination_'+rel)
            candidate=read(STAGE/'new'/rel)
            if rel in NEW:
                before=read(dst) if dst.exists() else ''
                if before and before!=candidate:
                    raise RuntimeError('new_module_name_conflict_'+rel)
                changed=candidate
            else:
                base=read(STAGE/'old'/rel)
                before=read(dst)
                changed=merge(before,base,candidate,rel)
            if changed.count('<?php')!=1:
                raise RuntimeError('unexpected_php_tags_'+rel)
            if rel=='_core/Checkout.php' and not all(x in changed for x in
                ("$subscriptionId=insert(", "'subscription_id'=>$subscriptionId")):
                raise RuntimeError('paid_term_carry_patch_missing')
            if rel=='_core/KashierWebhook.php' and not all(x in changed for x in
                ('function kashier_refund_settle','refund_original_payment_proof_missing')):
                raise RuntimeError('refund_provider_verification_patch_missing')
            if rel=='_core/WhatsAppJobSafety.php' and not all(x in changed for x in
                ('wa_recover_stale_jobs','wa_job_reserve_send','wa_job_send_once')):
                raise RuntimeError('wa_dispatch_contract_missing')
            if rel=='device/v1/index.php' and not all(x in changed for x in
                ("'flow_start','flow_step','flow_state'","voice_flow_require_active_context")):
                raise RuntimeError('secure_flow_device_route_missing')
            encoded=changed.encode('utf-8')
            lint(rel,encoded)
            plan[rel]=(dst,dst.read_bytes() if dst.exists() else b'',encoded,
                stat.S_IMODE(dst.stat().st_mode) if dst.exists() else 0o644,dst.exists())
        backup=STAGE/('backup-'+str(int(time.time()))+'-'+str(os.getpid()))
        backup.mkdir(mode=0o700)
        touched=[]
        try:
            for rel,(dst,original,_,_,exists) in plan.items():
                if exists:
                    saved=backup/rel
                    saved.parent.mkdir(parents=True,exist_ok=True)
                    saved.write_bytes(original);saved.chmod(0o600)
            for rel,(dst,original,patched,mode,exists) in plan.items():
                if dst.exists()!=exists:
                    raise RuntimeError('concurrent_file_presence_'+rel)
                if exists and checksum(dst.read_bytes())!=checksum(original):
                    raise RuntimeError('concurrent_host_edit_'+rel)
                if original!=patched:
                    atomic(dst,patched,mode);touched.append(rel)
                if checksum(dst.read_bytes())!=checksum(patched):
                    raise RuntimeError('publish_hash_mismatch_'+rel)
            print('V67_ALL_LOGIC_FILES_PUBLISHED_WITH_ROLLBACK_PASS',flush=True)
            print(json.dumps({'status':'deployed','changed':touched,'backup':str(backup),
                'hashes':{rel:checksum(v[2]) for rel,v in plan.items()},
                'existing_users_untouched':True,'payments_not_mutated':True,
                'signed_mobile_unchanged':True,'external_whatsapp_module_requires_separate_probe':True},
                ensure_ascii=False,separators=(',',':')),flush=True)
        except BaseException:
            for rel in reversed(touched):
                dst,original,_,mode,exists=plan[rel]
                if exists:atomic(dst,original,mode)
                elif dst.exists():dst.unlink()
            raise

if __name__=='__main__':main()
