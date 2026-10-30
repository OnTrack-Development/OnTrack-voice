"""Deploy only immutable OTA HTML and its signed manifest; no credential reads."""
import base64, datetime, fcntl, hashlib, json, os, tempfile, zlib
from pathlib import Path

def atomic(path, content):
    fd,temp=tempfile.mkstemp(dir=path.parent,prefix='.ota-')
    try:
        with os.fdopen(fd,'wb') as f:f.write(content);f.flush();os.fsync(f.fileno())
        os.chmod(temp,0o644);os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)

def deploy(encoded,expected):
    raw=zlib.decompress(base64.b64decode(encoded))
    if hashlib.sha256(raw).hexdigest()!=expected:raise ValueError('Bundle checksum mismatch')
    bundle=json.loads(raw);manifest=bundle['manifest'];envelope=json.loads(manifest);meta=json.loads(envelope['payload'])
    html=bundle['html'].encode();name=meta['file']
    if len(html)>524288 or len(html)!=meta['size'] or hashlib.sha256(html).hexdigest()!=meta['sha256'] or name!='ui-'+meta['sha256']+'.html':raise ValueError('Invalid immutable OTA content')
    if os.getuid()!=2379:raise ValueError('Wrong account')
    root=Path('/home/appontrack/public_html/saas/mobile');private=Path('/home/appontrack/.ontrack-mobile')
    if not root.is_dir() or root.is_symlink() or private.is_symlink():raise ValueError('Wrong target')
    target=root/'updates'
    if target.is_symlink():raise ValueError('Target symlink')
    target.mkdir(mode=0o755,exist_ok=True)
    os.chmod(target,0o755)
    with (private/'deploy.lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        current=target/'latest.json'
        if current.is_file():
            old=json.loads(json.loads(current.read_text())['payload'])
            if meta['revision']<old['revision']:raise ValueError('OTA revision cannot go backwards; publish new higher revision for rollback')
            if meta['revision']==old['revision'] and current.read_text()!=manifest:raise ValueError('Revision cannot be reused with different content')
            backup=private/'ota-backups';backup.mkdir(mode=0o700,exist_ok=True)
            atomic(backup/('manifest-'+str(old['revision'])+'.json'),current.read_bytes())
            os.chmod(backup/('manifest-'+str(old['revision'])+'.json'),0o600)
        dest=target/name
        if dest.is_symlink():raise ValueError('Immutable file symlink')
        if dest.exists() and dest.read_bytes()!=html:raise ValueError('Immutable content conflict')
        atomic(dest,html)
        atomic(target/'.htaccess',b'Options -Indexes\n<IfModule mod_headers.c>\n<Files "latest.json">\n Header set Cache-Control "no-store"\n</Files>\n</IfModule>\n')
        atomic(current,manifest.encode())  # publish pointer last
    print(json.dumps({'status':'installed','scope':'saas/mobile/updates','revision':meta['revision'],'sha256':meta['sha256'],'runtime':meta['runtime'],'no_credentials_read':True,'root_api_unchanged':True}))
