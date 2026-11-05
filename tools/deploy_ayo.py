"""Install only the isolated /ayo PHP browser demo from pinned CI artifact.
Copied into the host's existing GitHub/Cron worker process by an approved publish job.
No credentials or user files are bundled; no existing SaaS or Android paths are changed.
"""
import hashlib
import io
import json
import os
import pathlib
import re
import shutil
import subprocess
import tarfile
import tempfile
import urllib.parse
import urllib.request
import zipfile

DOCROOT = pathlib.Path('/home/appontrack/public_html')
TARGET = DOCROOT / 'ayo'
ALLOWED = {'ayo/index.php','ayo/api.php','ayo/app.js','ayo/style.css','ayo/audio-worklet.js','ayo/ontrack.json'}


def deploy(url, digest, commit):
    if os.getuid() != 2379 or not DOCROOT.is_dir() or DOCROOT.is_symlink() or TARGET.is_symlink():
        raise RuntimeError('Host path or identity mismatch')
    if not re.fullmatch('[0-9a-f]{64}', digest) or not re.fullmatch('[0-9a-f]{40}', commit):
        raise RuntimeError('Invalid release identifiers')
    host = urllib.parse.urlsplit(url)
    if host.scheme != 'https' or not host.hostname or not (
        host.hostname.endswith('.blob.core.windows.net') or
        host.hostname.endswith('.actions.githubusercontent.com')
    ):
        raise RuntimeError('Artifact origin not allowlisted')
    with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'OnTrack-AYO-Deploy/1'}),timeout=12) as response:
        archive_zip = response.read(2*1024*1024+1)
    if len(archive_zip) > 2*1024*1024:
        raise RuntimeError('Oversized artifact')
    with zipfile.ZipFile(io.BytesIO(archive_zip)) as z:
        if set(z.namelist()) != {'release.tar.gz','release.sha256'}:
            raise RuntimeError('Unexpected artifact contents')
        archive = z.read('release.tar.gz')
        declared = z.read('release.sha256').decode().strip()
    if hashlib.sha256(archive).hexdigest() != digest or declared != digest:
        raise RuntimeError('Release sha256 mismatch')
    stage = pathlib.Path(tempfile.mkdtemp(prefix='.ayo-stage-', dir=str(DOCROOT)))
    backup = None
    try:
        target_stage = stage/'ayo'
        target_stage.mkdir(mode=0o755)
        seen = set()
        with tarfile.open(fileobj=io.BytesIO(archive), mode='r:gz') as tar:
            entries = tar.getmembers()
            if len(entries)>10:
                raise RuntimeError('Unexpected tar entry count')
            for item in entries:
                if item.isdir() and item.name.strip('/') == 'ayo':
                    continue
                if not item.isfile() or item.name not in ALLOWED or item.size>130000 or item.name in seen:
                    raise RuntimeError('Unsafe tar member')
                seen.add(item.name)
                data = tar.extractfile(item).read()
                dst = target_stage/pathlib.Path(item.name).name
                dst.write_bytes(data)
                dst.chmod(0o644)
        if seen != ALLOWED:
            raise RuntimeError('Missing release file')
        php = '/opt/cpanel/ea-php83/root/usr/bin/php'
        if not pathlib.Path(php).is_file():
            php = '/usr/bin/php'
        for name in ('index.php','api.php'):
            check = subprocess.run([php,'-l',str(target_stage/name)],capture_output=True,text=True,timeout=4)
            if check.returncode != 0:
                raise RuntimeError('Host PHP lint failed: '+name)
        if not json.loads((target_stage/'ontrack.json').read_text('utf8')).get('plans'):
            raise RuntimeError('Knowledge dataset invalid')
        (target_stage/'.htaccess').write_text('Options -Indexes\nDirectoryIndex index.php\n')
        (target_stage/'.htaccess').chmod(0o644)
        (target_stage/'.ayo-release.json').write_text(json.dumps({'commit':commit,'files':len(seen)}))
        (target_stage/'.ayo-release.json').chmod(0o644)
        target_stage.chmod(0o755)
        if TARGET.exists():
            marker = TARGET/'.ayo-release.json'
            if not TARGET.is_dir() or not marker.is_file():
                raise RuntimeError('Existing unmanaged /ayo folder not modified')
            old = json.loads(marker.read_text())
            if not re.fullmatch('[0-9a-f]{40}',old.get('commit','')):
                raise RuntimeError('Invalid previous release marker')
            backups = pathlib.Path('/home/appontrack/.ayo-backups')
            backups.mkdir(mode=0o700,exist_ok=True)
            backups.chmod(0o700)
            backup = backups/('ayo-'+old['commit'][:12])
            if backup.exists():
                raise RuntimeError('Previous backup already exists')
            os.replace(TARGET,backup)
        try:
            os.replace(target_stage,TARGET)
        except BaseException:
            if backup is not None and backup.exists():
                os.replace(backup,TARGET)
            raise
        print(json.dumps({'status':'deployed','target':'/ayo/','source':commit,
                          'php_lint':'pass','files':len(seen),
                          'api_sha256':hashlib.sha256((TARGET/'api.php').read_bytes()).hexdigest(),
                          'backup':bool(backup)}))
    finally:
        shutil.rmtree(stage,ignore_errors=True)
