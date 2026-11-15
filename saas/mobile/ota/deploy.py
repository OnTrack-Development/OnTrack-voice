"""Deploy only immutable OTA HTML and its signed manifest; no credential reads."""
import base64, datetime, fcntl, hashlib, json, lzma, os, subprocess, tempfile, zlib
from pathlib import Path

def verify_rsa_signature(envelope, cert_bytes=None):
    if not isinstance(envelope, dict) or "payload" not in envelope or "signature" not in envelope:
        raise ValueError("Invalid signed envelope structure")
    private = Path('/home/appontrack/.ontrack-mobile')
    pinned_cert = private / 'signing-cert.der'
    if cert_bytes is None and pinned_cert.is_file():
        cert_bytes = pinned_cert.read_bytes()
    if cert_bytes:
        with tempfile.TemporaryDirectory() as td:
            p = Path(td)
            payload_bytes = envelope["payload"].encode("utf-8") if isinstance(envelope["payload"], str) else envelope["payload"]
            (p / "payload").write_bytes(payload_bytes)
            (p / "cert.der").write_bytes(cert_bytes)
            (p / "signature").write_bytes(base64.b64decode(envelope["signature"], validate=True))
            (p / "public.pem").write_bytes(subprocess.check_output(
                ["openssl", "x509", "-inform", "DER", "-in", str(p / "cert.der"), "-pubkey", "-noout"], timeout=4))
            subprocess.run(["openssl", "dgst", "-sha256", "-verify", str(p / "public.pem"), "-signature", str(p / "signature"), str(p / "payload")],
                           check=True, capture_output=True, timeout=4)

def atomic(path, content):
    fd,temp=tempfile.mkstemp(dir=path.parent,prefix='.ota-')
    try:
        with os.fdopen(fd,'wb') as f:f.write(content);f.flush();os.fsync(f.fileno())
        os.chmod(temp,0o644);os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)

def deploy(encoded,expected):
    compressed=base64.b64decode(encoded)
    try:raw=lzma.decompress(compressed)
    except lzma.LZMAError:raw=zlib.decompress(compressed)
    if hashlib.sha256(raw).hexdigest()!=expected:raise ValueError('Bundle checksum mismatch')
    bundle=json.loads(raw);manifest=bundle['manifest'];envelope=json.loads(manifest);meta=json.loads(envelope['payload'])
    verify_rsa_signature(envelope, bundle.get('cert'))
    html=bundle['html'].encode();name=meta['file']
    if len(html)>524288 or len(html)!=meta['size'] or hashlib.sha256(html).hexdigest()!=meta['sha256'] or name!='ui-'+meta['sha256']+'.html':raise ValueError('Invalid immutable OTA content')
    if os.getuid()!=2379:raise ValueError('Wrong account')
    # The canonical SaaS release is served from public_html/, not /saas/console/.
    # Never recreate the retired /saas/mobile tree as a side effect of OTA.
    webroot=Path('/home/appontrack/public_html')
    root=webroot/'mobile'
    private=Path('/home/appontrack/.ontrack-mobile')
    if (not webroot.is_dir() or webroot.is_symlink()
            or root.is_symlink() or not private.is_dir() or private.is_symlink()):
        raise ValueError('Wrong target')
    # Canonical root/mobile may not exist after migration from /saas/mobile.
    # Create this fixed public directory only; preserve the old Pilot tree.
    root.mkdir(mode=0o755,exist_ok=True)
    if (not root.is_dir() or root.resolve().parent != webroot.resolve()):
        raise ValueError('Wrong target')
    os.chmod(root,0o755)
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
        atomic(target/'.htaccess',b'Options -Indexes\n<IfModule mod_setenvif.c>\nSetEnvIfNoCase Request_URI \\.(apk|json|html)$ no-gzip dont-vary no-brotli\n</IfModule>\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule ^ - [E=no-gzip:1,E=no-brotli:1,L]\n</IfModule>\n<IfModule mod_headers.c>\n<Files "latest.json">\nHeader set Cache-Control "no-store"\n</Files>\n</IfModule>\n')
        atomic(current,manifest.encode())  # publish pointer last
    print(json.dumps({'status':'installed','scope':'mobile/updates','revision':meta['revision'],'sha256':meta['sha256'],'runtime':meta['runtime'],'no_credentials_read':True,'root_api_unchanged':True}))
