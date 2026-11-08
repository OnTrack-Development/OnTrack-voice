#!/usr/bin/env python3
"""Atomic two-file host deploy for reseller-inherited device/API authorization.

Run on the approved unprivileged SSH host after isolated v70 tests. No DB
changes or cron changes. Keep rollback bytes and fail on concurrent host edits.
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

PRIVATE = Path('/home/appontrack/.ontrack-mobile')
STAGE = PRIVATE / 'saas-parent-v70'
ROOT = Path('/home/appontrack/public_html')
PHP = '/opt/cpanel/ea-php83/root/usr/bin/php'
FILES = ('_core/DevicePairing.php', 'api/v1/index.php')


def sha(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()


def atomic(target: Path, content: bytes, mode: int) -> None:
    fd, temporary = tempfile.mkstemp(prefix='.v70-saas-', dir=str(target.parent))
    try:
        with os.fdopen(fd, 'wb') as stream:
            stream.write(content)
            stream.flush()
            os.fsync(stream.fileno())
        os.chmod(temporary, mode)
        os.replace(temporary, target)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def lint(path: Path) -> None:
    subprocess.run([PHP, '-l', str(path)], check=True, timeout=20,
                   stdout=subprocess.DEVNULL)


def main() -> None:
    if os.getuid() != 2379:
        raise RuntimeError('wrong_host_user')
    for directory in (PRIVATE, STAGE, ROOT, ROOT / '_core', ROOT / 'api',
                      ROOT / 'api/v1'):
        if directory.is_symlink() or not directory.is_dir():
            raise RuntimeError('unsafe_deploy_directory')
    old = {}
    new = {}
    permissions = {}
    for name in FILES:
        src = STAGE / name
        dst = ROOT / name
        if src.is_symlink() or dst.is_symlink() or not src.is_file() or not dst.is_file():
            raise RuntimeError('staged_or_live_php_missing')
        code = src.read_bytes()
        before = dst.read_bytes()
        if not (1000 <= len(code) <= 250000 and code.startswith(b'<?php')):
            raise RuntimeError('invalid_published_source')
        if name == '_core/DevicePairing.php':
            if b'account_service_active' not in code or b'device_binding_authorize' not in code:
                raise RuntimeError('missing_parent_device_authorization_guard')
        elif b'if(!account_service_active($a))' not in code:
            raise RuntimeError('missing_parent_api_authorization_guard')
        lint(src)
        old[name], new[name] = before, code
        permissions[name] = stat.S_IMODE(dst.stat().st_mode)
    with (PRIVATE / 'deploy.lock').open('a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX)
        for name in FILES:
            if (ROOT / name).read_bytes() != old[name]:
                raise RuntimeError('concurrent_host_code_edit')
        backup = STAGE / ('backup-' + str(int(time.time())) + '-' + str(os.getpid()))
        backup.mkdir(mode=0o700)
        for name in FILES:
            dst = backup / name
            dst.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
            dst.write_bytes(old[name])
            os.chmod(dst, 0o600)
        applied = []
        try:
            for name in FILES:
                if old[name] != new[name]:
                    atomic(ROOT / name, new[name], permissions[name])
                    applied.append(name)
            for name in FILES:
                lint(ROOT / name)
                if sha((ROOT / name).read_bytes()) != sha(new[name]):
                    raise RuntimeError('postdeploy_host_source_hash_mismatch')
        except BaseException:
            for name in applied:
                atomic(ROOT / name, old[name], permissions[name])
            raise
    print('V70_RESELLER_PARENT_API_AND_SIM_HOST_PHP83_DEPLOY_PASS', flush=True)
    print(json.dumps({
        'status': 'published', 'changed': applied,
        'backup': str(backup),
        'source_sha256': {name: sha(new[name]) for name in FILES},
        'billing_db_unchanged': True,
        'cron_unchanged': True,
        'host_link_unchanged': True
    }, separators=(',', ':')), flush=True)


if __name__ == '__main__':
    main()
