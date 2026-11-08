#!/usr/bin/env python3
"""Deploy SOG Studio 404 fix as a compatibility-safe PHP-first upgrade.

Approved hosting user and existing deploy.lock. The backend supports both
legacy and packed text before the new JS ships. Backup allows full rollback
without touching any SaaS customer data or Android/mobile.
"""
from pathlib import Path
import fcntl,hashlib,json,os,stat,subprocess,tempfile,time

ROOT=Path('/home/appontrack/public_html')
PRIVATE=Path('/home/appontrack/.ontrack-mobile')
STAGE=PRIVATE/'saas-studio-v78'
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
FILES=('_core/Actions.php','_core/Views.php','assets/js/app.js')

def sha(raw:bytes)->str:return hashlib.sha256(raw).hexdigest()

def atomic(path:Path,raw:bytes,mode:int)->None:
    fd,tmp=tempfile.mkstemp(prefix='.studio-v78-',dir=str(path.parent))
    try:
        with os.fdopen(fd,'wb') as stream:
            stream.write(raw);stream.flush();os.fsync(stream.fileno())
        os.chmod(tmp,mode);os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

def lint(path:Path)->None:
    subprocess.run([PHP,'-l',str(path)],check=True,timeout=25,stdout=subprocess.DEVNULL)

def main()->None:
    if os.getuid()!=2379:raise RuntimeError('unauthorized_host_uid')
    for folder in (ROOT,ROOT/'_core',ROOT/'assets',ROOT/'assets/js',PRIVATE,STAGE,STAGE/'_core',STAGE/'assets',STAGE/'assets/js'):
        if not folder.is_dir() or folder.is_symlink():
            raise RuntimeError('missing_or_symlinked_studio_folder')
    old={};new={};mode={}
    for name in FILES:
        source=STAGE/name
        target=ROOT/name
        if source.is_symlink() or target.is_symlink() or not source.is_file() or not target.is_file():
            raise RuntimeError('missing_or_symlinked_studio_source')
        raw=source.read_bytes()
        if len(raw)<2000 or len(raw)>400000:raise RuntimeError('invalid_source_size')
        if name.endswith('.php'):
            if not raw.startswith(b'<?php'):raise RuntimeError('invalid_php_source')
            lint(source)
        elif b'studio_fields_b64' not in raw:
            raise RuntimeError('new_studio_client_bundle_missing')
        if name.endswith('Actions.php') and b'agent_studio_unpack_fields' not in raw:
            raise RuntimeError('server_decoder_missing')
        if name.endswith('Views.php') and b'white_label_url' not in raw:
            raise RuntimeError('canonical_form_action_missing')
        old[name]=target.read_bytes()
        new[name]=raw
        mode[name]=stat.S_IMODE(target.stat().st_mode)
    with (PRIVATE/'deploy.lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX)
        for name in FILES:
            if (ROOT/name).read_bytes()!=old[name]:
                raise RuntimeError('concurrent_source_change')
        backup=STAGE/('backup-'+str(int(time.time()))+'-'+str(os.getpid()))
        backup.mkdir(mode=0o700)
        for name in FILES:
            path=backup/name
            path.parent.mkdir(parents=True,mode=0o700,exist_ok=True)
            path.write_bytes(old[name]);os.chmod(path,0o600)
        changed=[]
        try:
            # PHP first (backwards compatible); JavaScript transport last.
            for name in FILES:
                if old[name]!=new[name]:
                    atomic(ROOT/name,new[name],mode[name])
                    changed.append(name)
            for name in FILES:
                if name.endswith('.php'):lint(ROOT/name)
                if sha((ROOT/name).read_bytes())!=sha(new[name]):
                    raise RuntimeError('published_sha256_mismatch')
        except BaseException:
            # UI first on rollback; never leave the packed client with old PHP.
            for name in reversed(changed):
                atomic(ROOT/name,old[name],mode[name])
            raise
    print('V78_SOG_AGENT_STUDIO_WAF_404_CANONICAL_PHP_JS_HOST_DEPLOY_PASS',flush=True)
    print(json.dumps({
        'status':'published','changed':changed,'backup':str(backup),
        'source_sha256':{name:sha(new[name]) for name in FILES},
        'customer_db_unchanged':True,'android_ota_unchanged':True,
        'cron_unchanged':True,'host_link_unchanged':True
    },separators=(',',':')),flush=True)

if __name__=='__main__':main()
