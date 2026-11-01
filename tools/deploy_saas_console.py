"""Install a pinned CI-built SaaS console bundle onto the existing host (no SSH).
Invoked only by the authorized OnTrack host-link exec worker with a short-lived
artifact URL and a sha256 for its inner tarball. Secrets never transit the archive.
"""
import hashlib,io,json,os,pathlib,shutil,subprocess,tarfile,tempfile,urllib.parse,urllib.request,zipfile

HOST_ROOT=pathlib.Path("/home/appontrack/public_html/saas")
TARGET=HOST_ROOT/"console"
PRIVATE=pathlib.Path("/home/appontrack/.ontrack-saas")

def deploy(download_url,tar_sha,source_commit):
    if os.getuid()!=2379 or HOST_ROOT.is_symlink() or TARGET.is_symlink():
        raise RuntimeError("Host or target mismatch")
    if not HOST_ROOT.is_dir():raise RuntimeError("SaaS root missing")
    u=urllib.parse.urlsplit(download_url)
    if u.scheme!="https" or not u.hostname or not (
        u.hostname.endswith(".blob.core.windows.net") or
        u.hostname.endswith(".actions.githubusercontent.com")
    ):raise ValueError("Untrusted artifact URL")
    if len(tar_sha)!=64 or any(c not in "0123456789abcdef" for c in tar_sha):
        raise ValueError("Invalid pinned hash")
    req=urllib.request.Request(download_url,headers={"User-Agent":"OnTrack-SaaS-Console-Deploy/1.0"})
    with urllib.request.urlopen(req,timeout=12) as response:
        raw=response.read(4*1024*1024+1)
    if len(raw)>4*1024*1024:raise ValueError("Archive too large")
    with zipfile.ZipFile(io.BytesIO(raw)) as z:
        members=[m for m in z.infolist() if m.filename=="release.tar.gz"]
        if len(members)!=1:raise ValueError("Archive layout")
        archive=z.read(members[0])
    if hashlib.sha256(archive).hexdigest()!=tar_sha:raise ValueError("Archive SHA mismatch")
    stage=pathlib.Path(tempfile.mkdtemp(prefix=".console-stage-",dir=str(HOST_ROOT)))
    backup=None
    try:
        inner=stage/"console"
        inner.mkdir(mode=0o755)
        files=0
        with tarfile.open(fileobj=io.BytesIO(archive),mode="r:gz") as tf:
            entries=tf.getmembers()
            if len(entries)>250:raise ValueError("Too many files")
            for m in entries:
                parts=pathlib.PurePosixPath(m.name).parts
                if not parts or parts[0]!="console" or any(p in (".","..") for p in parts):
                    raise ValueError("Invalid archive member")
                if m.issym() or m.islnk() or not (m.isfile() or m.isdir()):
                    raise ValueError("Unsafe archive member type")
                if m.size>1024*1024:raise ValueError("Member exceeds size limit")
                to=stage.joinpath(*parts)
                if m.isdir():
                    to.mkdir(parents=True,exist_ok=True,mode=0o755)
                else:
                    to.parent.mkdir(parents=True,exist_ok=True,mode=0o755)
                    with tf.extractfile(m) as inp, to.open("wb") as out:
                        shutil.copyfileobj(inp,out)
                    to.chmod(0o644)
                    files+=1
        if not (inner/"index.php").is_file() or not (inner/"_core"/".htaccess").is_file():
            raise ValueError("Missing app front controller or security rules")
        php="/opt/cpanel/ea-php83/root/usr/bin/php"
        for name in ["index.php","_core/Bootstrap.php","_core/Actions.php","_core/Views.php","database/Seed.php","api/v1/index.php","hooks/index.php"]:
            x=subprocess.run([php,"-l",str(inner/name)],capture_output=True,text=True,timeout=3)
            if x.returncode!=0:raise RuntimeError("PHP syntax rejected: "+name)
        PRIVATE.mkdir(parents=True,exist_ok=True,mode=0o700)
        PRIVATE.chmod(0o700)
        if TARGET.exists():
            backup=HOST_ROOT/(".console-backup-"+source_commit[:12])
            if backup.exists():raise ValueError("Backup target already exists")
            os.replace(TARGET,backup)
        try:os.replace(inner,TARGET)
        except BaseException:
            if backup is not None and backup.exists():os.replace(backup,TARGET)
            raise
        seeded=subprocess.run([php,str(TARGET/"database"/"Seed.php")],capture_output=True,text=True,timeout=9)
        if seeded.returncode!=0:
            # Fail closed, roll back the published tree if database seeding fails.
            failed=HOST_ROOT/(".console-failed-"+source_commit[:12])
            os.replace(TARGET,failed)
            if backup is not None and backup.exists():os.replace(backup,TARGET)
            raise RuntimeError("Database initialization failed: "+seeded.stderr[:450])
        # Keep a verified backup for an immediate manual rollback.
        print(json.dumps({"result":"deployed","source":source_commit[:12],
                          "files":files,"target":"/saas/console/",
                          "php":"8.3","seed":json.loads(seeded.stdout),
                          "backup":bool(backup)},ensure_ascii=False))
    finally:
        shutil.rmtree(stage,ignore_errors=True)
