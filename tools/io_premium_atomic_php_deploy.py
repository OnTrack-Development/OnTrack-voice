#!/usr/bin/env python3
"""Atomic, rollback-capable, narrowly scoped IO Premium canonical PHP deploy.

Executed only on appontrack host by authorized Windows SSH runner.
No dynamic command, no secrets in stdout, no whole-site replacement.
"""
import argparse
import hashlib
import json
import os
import pathlib
import shutil
import subprocess
import tempfile
import time

ROOT=pathlib.Path('/home/appontrack/public_html')
PRIVATE=pathlib.Path('/home/appontrack/.ontrack-mobile')
STAGE=PRIVATE/'io-premium-v51-stage'
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
FILES={
 '_core/Bootstrap.php':('ed00919b36e6f462cd8d1b250d99a1ebd107bf0d','7ea2eb1c9fedb1983ce6365ae00ce4bdc9558b6e'),
 '_core/TenantVoiceSession.php':('3add4386b7df206d45c55556432b026cb395b3ed','e5a85e1bf4ef4a1de01e57bff84ffec71ea4aff0'),
 'device/v1/tts.php':(None,'d7969de9eafa5042f842a6359f71c43a529dd768'),
}

def blobsha(content):
    return hashlib.sha1(('blob '+str(len(content))+'\\0').encode()+content).hexdigest()

def atomic_write(path,data):
    fd,tmp=tempfile.mkstemp(dir=str(path.parent),prefix='.io-v51-')
    try:
        with os.fdopen(fd,'wb') as out:
            out.write(data);out.flush();os.fsync(out.fileno())
        os.chmod(tmp,0o644)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

def main(apply):
    if os.getuid()!=2379 or not ROOT.is_dir() or ROOT.is_symlink() or PRIVATE.is_symlink() or not PRIVATE.is_dir():
        raise RuntimeError('invalid_deployment_account_or_root')
    out=[];files={}
    for rel,(baseline_sha,new_sha) in FILES.items():
        src=STAGE/rel;dest=ROOT/rel
        if not src.is_file() or src.is_symlink() or not dest.parent.is_dir() or dest.parent.is_symlink() or dest.is_symlink():
            raise RuntimeError('unsafe_missing_or_symlink_stage_or_target_'+rel)
        data=src.read_bytes()
        if blobsha(data)!=new_sha:raise RuntimeError('new_source_git_blob_mismatch_'+rel)
        check=subprocess.run([PHP,'-l',str(src)],capture_output=True,text=True,timeout=8)
        if check.returncode!=0:raise RuntimeError('php_syntax_failed_'+rel)
        current=blobsha(dest.read_bytes()) if dest.is_file() else None
        allowed=tuple(x for x in (baseline_sha,new_sha) if x is not None)
        if (current is not None and current not in allowed) or (current is None and baseline_sha is not None):
            raise RuntimeError('unexpected_live_file_revision_'+rel+'_sha_'+str(current))
        files[rel]=(dest,data,current)
        out.append({'file':rel,'source_sha':new_sha,'live_sha':current,'will_change':current!=new_sha})
    if not apply:
        print(json.dumps({'result':'preflight_pass','files':out,'site_untouched':True},separators=(',',':')))
        return
    stamp=str(int(time.time()))
    rollback=PRIVATE/'io-premium-v51-backup'/stamp
    rollback.mkdir(parents=True,exist_ok=False,mode=0o700)
    created=[]
    try:
        for rel,(dest,data,current) in files.items():
            if current is not None:
                backup=rollback/rel;backup.parent.mkdir(parents=True,exist_ok=True)
                shutil.copy2(dest,backup)
                backup.chmod(0o600)
        for rel,(dest,data,current) in files.items():
            if current != blobsha(data):
                atomic_write(dest,data);created.append(rel)
            if blobsha(dest.read_bytes())!=blobsha(data):
                raise RuntimeError('deployed_file_sha_mismatch_'+rel)
        print(json.dumps({'result':'installed_php','changed':created,'backup_dir':str(rollback),
            'protected_config_untouched':True,'ota_manifest_untouched':True},separators=(',',':')))
    except Exception:
        for rel in reversed(created):
            dest=files[rel][0];backup=rollback/rel
            if backup.is_file():atomic_write(dest,backup.read_bytes())
            elif dest.is_file():dest.unlink()
        raise

if __name__=='__main__':
    args=argparse.ArgumentParser()
    args.add_argument('--apply',action='store_true')
    opt=args.parse_args()
    main(opt.apply)
