"""Account-scoped, checksum-pinned deployment; no credential/config reads."""
import base64
import datetime
import fcntl
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import zlib


def atomic(path, content, mode=0o644):
    fd, temp = tempfile.mkstemp(dir=str(path.parent), prefix='.mobile-')
    try:
        with os.fdopen(fd, 'w') as stream:
            stream.write(content)
            stream.flush()
            os.fsync(stream.fileno())
        os.chmod(temp, mode)
        os.replace(temp, path)
    finally:
        if os.path.exists(temp):
            os.unlink(temp)


def main():
    raw = zlib.decompress(base64.b64decode(sys.argv[1]))
    if hashlib.sha256(raw).hexdigest() != sys.argv[2]:
        raise ValueError('Deployment bundle checksum mismatch')
    bundle = json.loads(raw)
    root = Path('/home/appontrack/public_html')
    private = Path('/home/appontrack/.ontrack-mobile')
    if os.getuid() != 2379 or not root.is_dir() or root.is_symlink():
        raise ValueError('Wrong account or root')
    private.mkdir(mode=0o700, exist_ok=True)
    if private.is_symlink():
        raise ValueError('Private directory is a symlink')
    os.chmod(private, 0o700)
    with (private/'deploy.lock').open('a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
        backup = private/'backups'/stamp
        backup.mkdir(parents=True, mode=0o700)
        original_path = root/'api/live_session.php'
        original = original_path.read_text()
        namespace = {'__name__': 'mobile_deploy_adapter'}
        exec(compile(bundle['patcher'], 'pinned-session-adapter', 'exec'), namespace)
        patched = namespace['patch'](original)
        php = next((x for x in ['/opt/cpanel/ea-php83/root/usr/bin/php','/usr/local/bin/php'] if Path(x).is_file()), None)
        if not php:
            raise ValueError('No validated PHP CLI candidate')
        with tempfile.TemporaryDirectory(dir=private) as temp:
            stage = Path(temp)
            for name, content in {**bundle['files'], 'live_session.php': patched}.items():
                if Path(name).name != name:
                    raise ValueError('Unexpected deployment path')
                (stage/name).write_text(content)
                if name.endswith('.php'):
                    result = subprocess.run([php, '-l', str(stage/name)], capture_output=True, text=True, timeout=5)
                    if result.returncode:
                        raise ValueError('PHP lint failed for '+name)
        target = root/'saas'/'mobile'
        target.mkdir(parents=True, mode=0o755, exist_ok=True)
        os.chmod(root/'saas',0o755)
        os.chmod(target,0o755)
        if target.is_symlink():
            raise ValueError('Target is a symlink')
        atomic(backup/'live_session.php',original,0o600)
        written = []
        for name, content in bundle['files'].items():
            dest = target/name
            if dest.is_file():
                atomic(backup/name,dest.read_text(),0o600)
            atomic(dest,content)
            written.append('saas/mobile/'+name)
        atomic(original_path,patched,original_path.stat().st_mode & 0o777)
        settings = private/'settings.json'
        if not settings.exists():
            atomic(settings,json.dumps({'revision':1,'enabled':True,'auto_answer':True},ensure_ascii=False),0o600)
        print(json.dumps({'status':'installed','files':written,'php_lint':'PASS',
            'session_before_sha256':hashlib.sha256(original.encode()).hexdigest(),
            'session_after_sha256':hashlib.sha256(patched.encode()).hexdigest(),
            'private_backup_created':True,'no_secret_config_read':True}))


if __name__ == '__main__':
    main()
