"""Safely install the complete standalone AYO voice lab via the pinned GitHub artifact.

Only /home/appontrack/public_html/ayo is touched. The main SaaS and its
authentication routes remain unchanged. The release contains no API keys.
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
import urllib.error
import urllib.parse
import urllib.request
import zipfile

DOCROOT = pathlib.Path('/home/appontrack/public_html')
TARGET = DOCROOT / 'ayo'
ALLOWED = {
    'ayo/index.php', 'ayo/api.php', 'ayo/app.js', 'ayo/style.css',
    'ayo/audio-worklet.js', 'ayo/ontrack.json', 'ayo/.htaccess',
    'ayo/secure-vault.php', 'ayo/voice-expression.php',
    'ayo/ayo-avatar.js', 'ayo/ayo-mic-stt.js',
    'ayo/assets/ayo-character.webp',
}
PUBLIC_URL = 'https://app.ontrackegy.com/ayo/'


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, url):
        return None


def public_probe():
    """Check the real URL without following redirects to the parent login."""
    req = urllib.request.Request(PUBLIC_URL, headers={
        'User-Agent': 'OnTrack-AYO-Release-Verify/1',
        'Cache-Control': 'no-cache',
    })
    try:
        with urllib.request.build_opener(NoRedirect()).open(req, timeout=12) as rsp:
            html = rsp.read(220000).decode('utf-8', 'replace')
            if rsp.status != 200 or 'AYO | OnTrack Voice Lab' not in html:
                return {'pass': False, 'http': rsp.status, 'reason': 'AYO HTML missing'}
            return {'pass': True, 'http': rsp.status, 'url': PUBLIC_URL}
    except urllib.error.HTTPError as ex:
        return {'pass': False, 'http': ex.code,
                'redirect': (ex.headers.get('Location') or '')[:250]}
    except Exception as ex:
        return {'pass': False, 'reason': type(ex).__name__ + ': ' + str(ex)[:160]}


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
    with urllib.request.urlopen(
        urllib.request.Request(url, headers={'User-Agent': 'OnTrack-AYO-Deploy/2'}),
        timeout=16,
    ) as response:
        archive_zip = response.read(2 * 1024 * 1024 + 1)
    if len(archive_zip) > 2 * 1024 * 1024:
        raise RuntimeError('Oversized artifact')
    with zipfile.ZipFile(io.BytesIO(archive_zip)) as archive_file:
        if set(archive_file.namelist()) != {'release.tar.gz', 'release.sha256'}:
            raise RuntimeError('Unexpected artifact contents')
        archive = archive_file.read('release.tar.gz')
        declared = archive_file.read('release.sha256').decode().strip()
    if hashlib.sha256(archive).hexdigest() != digest or declared != digest:
        raise RuntimeError('Release sha256 mismatch')

    stage = pathlib.Path(tempfile.mkdtemp(prefix='.ayo-stage-', dir=str(DOCROOT)))
    backup = None
    try:
        target_stage = stage / 'ayo'
        target_stage.mkdir(mode=0o755)
        seen = set()
        with tarfile.open(fileobj=io.BytesIO(archive), mode='r:gz') as tar:
            entries = tar.getmembers()
            if len(entries) > 20:
                raise RuntimeError('Unexpected tar entry count')
            for item in entries:
                if item.isdir() and item.name.strip('/') in ('ayo', 'ayo/assets'):
                    continue
                if (not item.isfile() or item.name not in ALLOWED
                        or item.size > 400000 or item.name in seen):
                    raise RuntimeError('Unsafe tar member: ' + item.name[:120])
                seen.add(item.name)
                data = tar.extractfile(item).read()
                dst = target_stage / item.name[4:]
                dst.parent.mkdir(mode=0o755, parents=True, exist_ok=True)
                dst.write_bytes(data)
                dst.chmod(0o644)
        if seen != ALLOWED:
            raise RuntimeError('Incomplete AYO release: ' + str(sorted(ALLOWED - seen)))
        avatar = (target_stage / 'assets/ayo-character.webp').read_bytes()
        if not (avatar.startswith(b'RIFF') and avatar[8:12] == b'WEBP'):
            raise RuntimeError('Invalid AYO avatar format')
        if 'DirectoryIndex index.php' not in (target_stage / '.htaccess').read_text():
            raise RuntimeError('Standalone routing policy missing')
        if not json.loads((target_stage / 'ontrack.json').read_text('utf8')).get('plans'):
            raise RuntimeError('Knowledge dataset invalid')
        php = '/opt/cpanel/ea-php83/root/usr/bin/php'
        if not pathlib.Path(php).is_file():
            php = '/usr/bin/php'
        for name in ('index.php', 'api.php', 'secure-vault.php', 'voice-expression.php'):
            result = subprocess.run(
                [php, '-l', str(target_stage / name)],
                capture_output=True, text=True, timeout=6,
            )
            if result.returncode:
                raise RuntimeError('Host PHP lint failed: ' + name + ': ' + result.stderr[:250])
        target_stage.chmod(0o755)
        (target_stage / 'assets').chmod(0o755)
        (target_stage / '.ayo-release.json').write_text(
            json.dumps({'commit': commit, 'files': len(seen)})
        )
        (target_stage / '.ayo-release.json').chmod(0o644)

        if TARGET.exists():
            if not TARGET.is_dir():
                raise RuntimeError('Existing /ayo is not a directory')
            marker = TARGET / '.ayo-release.json'
            if marker.is_file():
                old = json.loads(marker.read_text())
                old_sha = old.get('commit', '')
                if not re.fullmatch('[0-9a-f]{40}', old_sha):
                    raise RuntimeError('Invalid previous release marker')
                backup_name = 'ayo-' + old_sha[:12]
            else:
                # Preserve an earlier standalone AYO prototype, but never replace
                # arbitrary unrelated content that happens to live at /ayo.
                index = TARGET / 'index.php'
                if (not index.is_file() or index.is_symlink() or
                        'AYO' not in index.read_text('utf8', errors='replace')[:10000]):
                    raise RuntimeError('Unmanaged non-AYO directory: refusing overwrite')
                backup_name = 'ayo-legacy-before-' + commit[:12]
            backups = pathlib.Path('/home/appontrack/.ayo-backups')
            backups.mkdir(mode=0o700, exist_ok=True)
            backups.chmod(0o700)
            backup = backups / backup_name
            if backup.exists():
                raise RuntimeError('Existing release backup collision')
            os.replace(TARGET, backup)
        try:
            os.replace(target_stage, TARGET)
        except BaseException:
            if backup is not None and backup.exists():
                os.replace(backup, TARGET)
            raise

        health = public_probe()
        print(json.dumps({
            'status': 'deployed' if health['pass'] else 'deployed_http_unverified',
            'target': '/ayo/', 'source': commit, 'php_lint': 'pass',
            'files': len(seen), 'live_http': health, 'backup': bool(backup),
            'api_sha256': hashlib.sha256((TARGET / 'api.php').read_bytes()).hexdigest(),
        }))
        if not health['pass']:
            raise RuntimeError('AYO files installed but public URL check failed: ' + json.dumps(health))
    finally:
        shutil.rmtree(stage, ignore_errors=True)
