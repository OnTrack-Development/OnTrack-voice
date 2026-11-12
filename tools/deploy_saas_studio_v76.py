#!/usr/bin/env python3
"""Atomic, rollback-safe Agent Studio JS and PHP view fix (v76).

Run on the approved unprivileged SSH host. Does not touch credentials, phone
data, database, workers, cron, or native Android OTA channels.
"""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import stat
import subprocess
import tempfile
import time

PRIVATE=Path('/home/appontrack/.ontrack-mobile')
STAGE=PRIVATE/'saas-studio-v76'
ROOT=Path('/home/appontrack/public_html')
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
ASSETS=('assets/js/flow-graph.js','assets/js/app.js','_core/Views.php')

def sha(raw:bytes)->str:
    return hashlib.sha256(raw).hexdigest()

def atomic(target:Path,raw:bytes,mode:int)->None:
    fd,tmp=tempfile.mkstemp(prefix='.studio-v76-',dir=str(target.parent))
    try:
        with os.fdopen(fd,'wb') as stream:
            stream.write(raw)
            stream.flush()
            os.fsync(stream.fileno())
        os.chmod(tmp,mode)
        os.replace(tmp,target)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

def main()->None:
    if os.getuid()!=2379:raise RuntimeError('wrong_host_user')
    for folder in (PRIVATE,STAGE,ROOT,ROOT/'_core',ROOT/'assets',ROOT/'assets/js'):
        if folder.is_symlink() or not folder.is_dir():
            raise RuntimeError('unsafe_studio_folder')
    incoming={}
    previous={}
    mode={}
    for name in ASSETS:
        source=STAGE/name
        target=ROOT/name
        if source.is_symlink() or target.is_symlink() or not source.is_file() or not target.is_file():
            raise RuntimeError('staged_studio_source_missing_or_symlinked')
        new=source.read_bytes()
        if len(new)<2000 or len(new)>350000:raise RuntimeError('studio_source_size_invalid')
        if name.endswith('.php'):
            if not new.startswith(b'<?php'):raise RuntimeError('invalid_php_view_source')
            subprocess.run([PHP,'-l',str(source)],check=True,timeout=20,stdout=subprocess.DEVNULL)
        elif name.endswith('flow-graph.js') and b'ontrack:studio-saved' not in new:
            raise RuntimeError('missing_shared_save_success_handler')
        elif name.endswith('app.js') and b'new URLSearchParams(data)' not in new:
            raise RuntimeError('missing_single_urlencoded_save_handler')
        incoming[name]=new
        previous[name]=target.read_bytes()
        mode[name]=stat.S_IMODE(target.stat().st_mode)
    with (PRIVATE/'deploy.lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX)
        for name in ASSETS:
            if (ROOT/name).read_bytes()!=previous[name]:
                raise RuntimeError('production_code_changed_during_preflight')
        backup=STAGE/('backup-'+str(int(time.time()))+'-'+str(os.getpid()))
        backup.mkdir(mode=0o700)
        for name in ASSETS:
            saved=backup/name
            saved.parent.mkdir(parents=True,mode=0o700,exist_ok=True)
            saved.write_bytes(previous[name])
            os.chmod(saved,0o600)
        changed=[]
        try:
            for name in ASSETS:
                if previous[name]!=incoming[name]:
                    atomic(ROOT/name,incoming[name],mode[name])
                    changed.append(name)
            subprocess.run([PHP,'-l',str(ROOT/'_core/Views.php')],
                           check=True,timeout=20,stdout=subprocess.DEVNULL)
            for name in ASSETS:
                if sha((ROOT/name).read_bytes())!=sha(incoming[name]):
                    raise RuntimeError('published_studio_sha_mismatch')
        except BaseException:
            for name in changed:
                atomic(ROOT/name,previous[name],mode[name])
            raise
    print('V76_STUDIO_SINGLE_JSON_SAVING_HOST_ATOMIC_DEPLOY_PASS',flush=True)
    print(json.dumps({
        'status':'published','changed':changed,'backup':str(backup),
        'source_sha256':{name:sha(incoming[name]) for name in ASSETS},
        'customer_database_changed':False,'android_ota_changed':False,
        'cron_unchanged':True,'host_link_unchanged':True
    },separators=(',',':')),flush=True)

if __name__=='__main__':main()
