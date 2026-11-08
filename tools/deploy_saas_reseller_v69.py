#!/usr/bin/env python3
"""Deploy the tested, reversible reseller parent-service gate only.

Must run as the existing unprivileged hosting account via authorized SSH.
Does not mutate subscriptions, accounts, payment records, cron or Host-Link.
"""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import subprocess
import tempfile
import time

PRIVATE = Path('/home/appontrack/.ontrack-mobile')
STAGE = PRIVATE / 'saas-reseller-v69'
WEB = Path('/home/appontrack/public_html')
CORE = WEB / '_core'
PHP = '/opt/cpanel/ea-php83/root/usr/bin/php'
FILES = ('Bootstrap.php', 'TenantVoiceSession.php', 'CustomerAccounts.php')


def hash_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def atomic_write(path: Path, data: bytes) -> None:
    fd, temporary = tempfile.mkstemp(prefix='.v69-reseller-', dir=str(path.parent))
    try:
        with os.fdopen(fd, 'wb') as file:
            file.write(data)
            file.flush()
            os.fsync(file.fileno())
        os.chmod(temporary, 0o644)
        os.replace(temporary, path)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def main() -> None:
    if os.getuid() != 2379:
        raise RuntimeError('reseller_patch_wrong_host_user')
    for folder in (PRIVATE, STAGE, WEB, CORE):
        if folder.is_symlink() or not folder.is_dir():
            raise RuntimeError('unsafe_reseller_patch_directory')
    pending = {}
    previous = {}
    for filename in FILES:
        src = STAGE / filename
        dest = CORE / filename
        if src.is_symlink() or dest.is_symlink() or not src.is_file() or not dest.is_file():
            raise RuntimeError('unsafe_or_missing_reseller_patch_' + filename)
        content = src.read_bytes()
        if not (1000 < len(content) < 500000) or not content.startswith(b'<?php'):
            raise RuntimeError('invalid_reseller_source_' + filename)
        subprocess.run([PHP, '-l', str(src)], check=True, timeout=20,
                       stdout=subprocess.DEVNULL)
        pending[filename] = content
        previous[filename] = dest.read_bytes()

    # Serialize with the existing OTA and SaaS deploy lock. No customer data
    # or DB migrations are touched. Keep all original files for rollback.
    with (PRIVATE / 'deploy.lock').open('a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX)
        # Reject concurrent source changes observed during staging/preflight.
        for filename in FILES:
            if (CORE / filename).read_bytes() != previous[filename]:
                raise RuntimeError('concurrent_reseller_source_change_' + filename)
        backup = STAGE / ('backup-' + str(int(time.time())))
        if backup.exists():
            raise RuntimeError('reseller_backup_collision')
        backup.mkdir(mode=0o700)
        for filename in FILES:
            target = backup / filename
            target.write_bytes(previous[filename])
            os.chmod(target, 0o600)

        changed = []
        try:
            for filename in FILES:
                if pending[filename] != previous[filename]:
                    atomic_write(CORE / filename, pending[filename])
                    changed.append(filename)
            for filename in FILES:
                subprocess.run([PHP, '-l', str(CORE / filename)],
                               check=True, timeout=20, stdout=subprocess.DEVNULL)
        except BaseException:
            for filename in changed:
                atomic_write(CORE / filename, previous[filename])
            raise

    print('V69_RESELLER_PARENT_SERVICE_GATE_ATOMIC_PHP83_DEPLOY_PASS', flush=True)
    print(json.dumps({
        'status': 'published', 'changed': changed,
        'backup': str(backup),
        'source_sha256': {name: hash_bytes(pending[name]) for name in FILES},
        'customer_db_untouched': True, 'cron_untouched': True,
        'host_link_untouched': True
    }, separators=(',', ':')), flush=True)


if __name__ == '__main__':
    main()
