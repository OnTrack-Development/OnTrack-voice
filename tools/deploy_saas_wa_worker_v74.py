#!/usr/bin/env python3
"""Atomically repair production WhatsApp cron PHP duplicate include.
Preserve the existing crontab and stopped Host-Link. No real sends in test.
"""
from pathlib import Path
import fcntl,hashlib,json,os,stat,subprocess,tempfile,time,shutil,secrets

PRIVATE=Path('/home/appontrack/.ontrack-mobile')
STAGE=PRIVATE/'saas-wa-worker-v74'
ROOT=Path('/home/appontrack/public_html')
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
NAME='whatsapp-worker.php'

def hash_bytes(data:bytes)->str:return hashlib.sha256(data).hexdigest()

def atomic(path:Path,data:bytes,mode:int)->None:
    fd,tmp=tempfile.mkstemp(prefix='.wa-worker-v74-',dir=str(path.parent))
    try:
        with os.fdopen(fd,'wb') as stream:
            stream.write(data);stream.flush();os.fsync(stream.fileno())
        os.chmod(tmp,mode);os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

def lint(path:Path)->None:
    subprocess.run([PHP,'-l',str(path)],check=True,timeout=20,
                   stdout=subprocess.DEVNULL)

def isolated_smoke()->None:
    namespace='v74_wa_'+secrets.token_hex(8)
    dbpath=Path(tempfile.gettempdir())/('ontrack-saas-ci-'+namespace)
    if dbpath.exists() or dbpath.is_symlink():
        raise RuntimeError('unsafe_isolated_worker_root')
    env=os.environ.copy()
    env['ONTRACK_CI_NAMESPACE']=namespace
    env.pop('ONTRACK_TEST_PRIVATE',None)
    try:
        test=subprocess.run([PHP,'-q',str(ROOT/NAME)],env=env,
            capture_output=True,text=True,timeout=80)
        if test.returncode!=0:
            # Do not echo SQL/PII from a server process.
            raise RuntimeError('isolated_whatsapp_cron_smoke_nonzero')
        result=json.loads(test.stdout.strip().splitlines()[-1])
        if (int(result.get('native_sent',-1))!=0 or
            int(result.get('bridge_sent',-1))!=0 or
            int(result.get('failed',-1))!=0):
            raise RuntimeError('isolated_wa_fixture_attempted_send')
    finally:
        if dbpath.is_dir() and not dbpath.is_symlink():
            shutil.rmtree(dbpath)

def main()->None:
    if os.getuid()!=2379:raise RuntimeError('wrong_host_user')
    for directory in (PRIVATE,STAGE,ROOT):
        if directory.is_symlink() or not directory.is_dir():
            raise RuntimeError('unsafe_whatsapp_worker_path')
    src=STAGE/NAME;dst=ROOT/NAME
    if src.is_symlink() or dst.is_symlink() or not src.is_file() or not dst.is_file():
        raise RuntimeError('missing_or_symlinked_wa_worker')
    candidate=src.read_bytes()
    old=dst.read_bytes()
    if not (300<len(candidate)<50000 and candidate.startswith(b'<?php')):
        raise RuntimeError('bad_wa_worker_source')
    if candidate.count(b'require_once __DIR__')!=5:
        raise RuntimeError('whatsapp_worker_requires_must_be_once')
    lint(src)
    with (PRIVATE/'deploy.lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX)
        if dst.read_bytes()!=old:raise RuntimeError('concurrent_host_worker_change')
        backup=STAGE/('backup-'+str(int(time.time()))+'-'+str(os.getpid()))
        backup.mkdir(mode=0o700)
        (backup/NAME).write_bytes(old)
        os.chmod(backup/NAME,0o600)
        mode=stat.S_IMODE(dst.stat().st_mode)
        changed=old!=candidate
        try:
            if changed:atomic(dst,candidate,mode)
            lint(dst)
            if hash_bytes(dst.read_bytes())!=hash_bytes(candidate):
                raise RuntimeError('worker_live_sha_mismatch')
            # Important: real production cron jobs are not executed by this
            # acceptance test. Use an isolated, brand new SQLite fixture.
            isolated_smoke()
        except BaseException:
            if changed:atomic(dst,old,mode)
            raise
    print('V74_WHATSAPP_REQUIRE_ONCE_ISOLATED_RUNTIME_AND_HOST_DEPLOY_PASS',flush=True)
    print(json.dumps({'status':'published','changed':changed,
        'backup':str(backup/NAME),'source_sha256':hash_bytes(candidate),
        'isolated_worker_smoke':'pass','production_queue_untouched':True,
        'cron_unchanged':True,'host_link_unchanged':True},
        separators=(',',':')),flush=True)
if __name__=='__main__':main()
