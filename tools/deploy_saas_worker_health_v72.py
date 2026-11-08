#!/usr/bin/env python3
"""Atomic v72 worker health UI deploy. No DB, Cron, Host-Link or secret changes."""
from pathlib import Path
import fcntl
import hashlib
import json
import os
import stat
import subprocess
import tempfile
import time

PRIVATE=Path('/home/appontrack/.ontrack-mobile')
STAGE=PRIVATE/'saas-worker-health-v72'
ROOT=Path('/home/appontrack/public_html')
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
FILES=('_core/OperationalOutbox.php','_core/Views.php')

def sha(raw:bytes)->str:return hashlib.sha256(raw).hexdigest()

def atomic(path:Path,raw:bytes,mode:int)->None:
    fd,tmp=tempfile.mkstemp(prefix='.v72-worker-',dir=str(path.parent))
    try:
        with os.fdopen(fd,'wb') as stream:
            stream.write(raw);stream.flush();os.fsync(stream.fileno())
        os.chmod(tmp,mode);os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

def lint(path:Path)->None:
    subprocess.run([PHP,'-l',str(path)],check=True,timeout=25,
                   stdout=subprocess.DEVNULL)

def main()->None:
    if os.getuid()!=2379:raise RuntimeError('wrong_host_user')
    for folder in (PRIVATE,STAGE,ROOT,ROOT/'_core'):
        if folder.is_symlink() or not folder.is_dir():
            raise RuntimeError('unsafe_host_folder')
    old={}
    new={}
    modes={}
    for name in FILES:
        src=STAGE/name
        dest=ROOT/name
        if src.is_symlink() or dest.is_symlink() or not src.is_file() or not dest.is_file():
            raise RuntimeError('missing_host_php')
        raw=src.read_bytes()
        if len(raw)<1000 or len(raw)>400000 or not raw.startswith(b'<?php'):
            raise RuntimeError('unsafe_php_source')
        lint(src)
        if name.endswith('OperationalOutbox.php') and b'operational_workers_assess' not in raw:
            raise RuntimeError('freshness_check_not_in_source')
        if name.endswith('Views.php') and 'تنبيه تشغيلي'.encode('utf-8') not in raw:
            raise RuntimeError('health_warning_not_in_source')
        old[name]=dest.read_bytes()
        new[name]=raw
        modes[name]=stat.S_IMODE(dest.stat().st_mode)
    with (PRIVATE/'deploy.lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX)
        for name in FILES:
            if (ROOT/name).read_bytes()!=old[name]:
                raise RuntimeError('host_concurrent_edit')
        backup=STAGE/('backup-'+str(int(time.time()))+'-'+str(os.getpid()))
        backup.mkdir(mode=0o700)
        for name in FILES:
            target=backup/name
            target.parent.mkdir(parents=True,mode=0o700,exist_ok=True)
            target.write_bytes(old[name])
            os.chmod(target,0o600)
        changed=[]
        try:
            for name in FILES:
                if old[name]!=new[name]:
                    atomic(ROOT/name,new[name],modes[name])
                    changed.append(name)
            for name in FILES:
                lint(ROOT/name)
                if sha((ROOT/name).read_bytes())!=sha(new[name]):
                    raise RuntimeError('live_source_sha_mismatch')
        except BaseException:
            for name in changed:atomic(ROOT/name,old[name],modes[name])
            raise
    print('V72_WORKER_HEALTH_ATOMIC_PHP83_HOST_DEPLOY_PASS',flush=True)
    print(json.dumps({'status':'published','changed':changed,'backup':str(backup),
        'source_sha256':{name:sha(new[name]) for name in FILES},
        'database_mutations':0,'cron_unchanged':True,'host_link_unchanged':True},
        separators=(',',':')),flush=True)

if __name__=='__main__':main()
