"""Run only within verified OnTrack host worker. Never accepts arbitrary paths."""
import base64,contextlib,datetime,fcntl,hashlib,io,json,os,subprocess,tempfile,urllib.request,zipfile
from pathlib import Path

CERT="d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f"
ROOT=Path("/home/appontrack/public_html/mobile/updates/native")
PRIVATE=Path("/home/appontrack/.ontrack-mobile")

def atomic(path,data):
    fd,t=tempfile.mkstemp(prefix=".native-stage-",dir=str(path.parent))
    try:
        with os.fdopen(fd,"wb") as f:f.write(data);f.flush();os.fsync(f.fileno())
        os.chmod(t,0o644);os.replace(t,path)
    finally:
        if os.path.exists(t):os.unlink(t)

def verify_signed(manifest,cert):
    envelope=json.loads(manifest);payload=envelope["payload"];meta=json.loads(payload)
    assert hashlib.sha256(cert).hexdigest()==CERT
    assert meta["schema"]==1 and meta["channel"]=="stable" and meta["package"]=="com.ontrack.voiceproof"
    assert meta["version_code"]>=12 and 1024<=meta["size"]<=150*1024*1024
    assert meta["file"]==f"ontrack-voice-v{meta['version_code']}-{meta['sha256']}.apk"
    assert len(meta["sha256"])==64
    with tempfile.TemporaryDirectory() as td:
        p=Path(td);(p/"cert.der").write_bytes(cert)
        (p/"payload").write_bytes(payload.encode())
        (p/"signature").write_bytes(base64.b64decode(envelope["signature"],validate=True))
        (p/"public.pem").write_bytes(subprocess.check_output(
            ["openssl","x509","-inform","DER","-in",str(p/"cert.der"),"-pubkey","-noout"],timeout=4))
        subprocess.run(["openssl","dgst","-sha256","-verify",str(p/"public.pem"),"-signature",str(p/"signature"),str(p/"payload")],
            check=True,stdout=subprocess.DEVNULL,timeout=5)
    return meta

def deploy(zip_url,manifest_b64,cert_b64,sha_expected):
    if os.getuid()!=2379 or PRIVATE.is_symlink() or ROOT.is_symlink():raise ValueError("host identity")
    manifest=base64.b64decode(manifest_b64);cert=base64.b64decode(cert_b64)
    meta=verify_signed(manifest,cert)
    assert meta["sha256"]==sha_expected
    assert zip_url.startswith("https://") and len(zip_url)<2500
    ROOT.mkdir(mode=0o755,parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix=".native-archive-",dir=str(PRIVATE))
    try:
        with os.fdopen(fd,"wb") as out:
            request=urllib.request.Request(zip_url,headers={"User-Agent":"OnTrack-Native-OTA-Worker"})
            with urllib.request.urlopen(request,timeout=20) as response:
                assert response.status==200
                content_type=response.headers.get("Content-Type","unknown").split(";")[0][:60]
                length=0
                while True:
                    chunk=response.read(262144)
                    if not chunk:break
                    length+=len(chunk)
                    if length>110*1024*1024:raise ValueError("archive too large")
                    out.write(chunk)
            # GitHub release assets are raw signed APKs; GitHub Actions
            # artifacts are ZIP wrappers. Both are accepted ONLY if the
            # extracted APK equals the signed version/size/SHA256 exactly.
            with open(tmp,"rb") as downloaded:
                digest_download=hashlib.sha256()
                while True:
                    part=downloaded.read(262144)
                    if not part:break
                    digest_download.update(part)
            raw_apk=(length==meta["size"] and digest_download.hexdigest()==sha_expected)
            if not raw_apk and not zipfile.is_zipfile(tmp):
                raise ValueError("NATIVE_DOWNLOAD_INVALID_FORMAT content_type="+
                                 content_type+" bytes="+str(length))
            with (contextlib.nullcontext(None) if raw_apk else zipfile.ZipFile(tmp)) as z:
                pkg=None
                if not raw_apk:
                    candidates=[x for x in z.infolist() if x.filename=="OnTrack-Voice-"+meta["version_name"]+".apk"]
                    assert len(candidates)==1 and candidates[0].file_size==meta["size"]
                    pkg=candidates[0]
                    assert not (pkg.external_attr>>16 & 0o170000)==0o120000
                dest=ROOT/meta["file"]
                with (PRIVATE/"deploy.lock").open("a") as lock:
                    fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
                    current=ROOT/"latest.json"
                    if current.is_file():
                        prev=json.loads(json.loads(current.read_text())["payload"])
                        if prev["version_code"]>meta["version_code"]:raise ValueError("native downgrade blocked")
                        if prev["version_code"]==meta["version_code"] and current.read_bytes()!=manifest:raise ValueError("version reuse conflict")
                    fd2,staged=tempfile.mkstemp(prefix=".native-apk-",dir=str(ROOT))
                    try:
                        digest=hashlib.sha256();copied=0
                        with os.fdopen(fd2,"wb") as output, (open(tmp,"rb") if raw_apk else z.open(pkg)) as stream:
                            while True:
                                part=stream.read(262144)
                                if not part:break
                                copied+=len(part)
                                assert copied<=meta["size"]
                                digest.update(part);output.write(part)
                            output.flush();os.fsync(output.fileno())
                        assert copied==meta["size"] and digest.hexdigest()==sha_expected
                        if dest.exists():
                            if hashlib.sha256(dest.read_bytes()).hexdigest()!=sha_expected:raise ValueError("immutable APK conflict")
                            os.unlink(staged)
                        else:
                            os.chmod(staged,0o644);os.replace(staged,dest)
                    finally:
                        if os.path.exists(staged):os.unlink(staged)
                    atomic(ROOT/"latest.apk",dest.read_bytes())
                    atomic(ROOT/".htaccess",b'Options -Indexes\nAddType application/vnd.android.package-archive .apk\n<IfModule mod_setenvif.c>\nSetEnvIfNoCase Request_URI \\.(apk|json|html)$ no-gzip dont-vary no-brotli\n</IfModule>\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule ^ - [E=no-gzip:1,E=no-brotli:1,L]\n</IfModule>\n<IfModule mod_headers.c>\nHeader always set Accept-Ranges "bytes"\n<Files "latest.json">\nHeader set Cache-Control "no-store"\n</Files>\n<FilesMatch "\\.apk$">\nHeader set Cache-Control "public, max-age=86400"\n</FilesMatch>\n</IfModule>\n')
                    atomic(current,manifest)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
    print(json.dumps({"status":"published","version":meta["version_code"],"sha256":sha_expected,"bytes":meta["size"],"path":"mobile/updates/native/","credentials_accessed":False}))
