#!/usr/bin/env python3
"""Atomic rollback-safe WhatsApp worker parent suspension patch v71.

Approved SSH host only, no database writes, no cron/Host-Link modifications.
The code has been tested separately against an isolated fixture before deploy.
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

PRIVATE=Path('/home/appontrack/.ontrack-mobile')
STAGE=PRIVATE/'saas-wa-parent-v71'
ROOT=Path('/home/appontrack/public_html')
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
FILES=(
    '_core/WhatsAppNativeAgent.php',
    '_core/WhatsAppWhmcsBridge.php',
    '_core/WhatsAppJobSafety.php',
    'whatsapp-worker.php'
)

def sha(raw:bytes)->str:
    return hashlib.sha256(raw).hexdigest()

def atomic(path:Path,raw:bytes,mode:int)->None:
    fd,tmp=tempfile.mkstemp(prefix='.wa-v71-',dir=str(path.parent))
    try:
        with os.fdopen(fd,'wb') as f:
            f.write(raw);f.flush();os.fsync(f.fileno())
        os.chmod(tmp,mode)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

def lint(path:Path)->None:
    subprocess.run([PHP,'-l',str(path)],check=True,timeout=20,
                   stdout=subprocess.DEVNULL)

def main()->None:
    if os.getuid()!=2379:raise RuntimeError('wrong_host_user')
    for directory in (PRIVATE,STAGE,ROOT,ROOT/'_core'):
        if directory.is_symlink() or not directory.is_dir():
            raise RuntimeError('unsafe_host_directory')
    original={}
    target={}
    mode={}
    for name in FILES:
        src=STAGE/name
        dst=ROOT/name
        if src.is_symlink() or dst.is_symlink() or not src.is_file() or not dst.is_file():
            raise RuntimeError('missing_or_symlinked_wa_file')
        raw=src.read_bytes()
        if not (1000<=len(raw)<250000) or not raw.startswith(b'<?php'):
            raise RuntimeError('wa_invalid_source')
        if b'account_service_active' not in raw:
            raise RuntimeError('missing_reseller_parent_service_guard')
        lint(src)
        target[name]=raw
        original[name]=dst.read_bytes()
        mode[name]=stat.S_IMODE(dst.stat().st_mode)

    with (PRIVATE/'deploy.lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX)
        for name in FILES:
            if (ROOT/name).read_bytes()!=original[name]:
                raise RuntimeError('concurrent_host_edit')
        backup=STAGE/('backup-'+str(int(time.time()))+'-'+str(os.getpid()))
        backup.mkdir(mode=0o700)
        for name in FILES:
            saved=backup/name
            saved.parent.mkdir(parents=True,mode=0o700,exist_ok=True)
            saved.write_bytes(original[name])
            os.chmod(saved,0o600)
        changed=[]
        try:
            for name in FILES:
                if original[name]!=target[name]:
                    atomic(ROOT/name,target[name],mode[name])
                    changed.append(name)
            for name in FILES:
                lint(ROOT/name)
                if sha((ROOT/name).read_bytes())!=sha(target[name]):
                    raise RuntimeError('postdeploy_sha_mismatch')
        except BaseException:
            for name in changed:
                atomic(ROOT/name,original[name],mode[name])
            raise
    print('V71_RESELLER_WA_PARENT_WORKER_ATOMIC_PHP83_PUBLISH_PASS',flush=True)
    print(json.dumps({
        'status':'published',
        'changed':changed,
        'backup':str(backup),
        'source_sha256':{name:sha(target[name]) for name in FILES},
        'database_mutations':0,
        'host_link_unchanged':True,
        'cron_unchanged':True
    },separators=(',',':')),flush=True)

if __name__=='__main__':
    main()
