#!/usr/bin/env python3
"""Reproducible no-secret SaaS console artifact for the existing host worker."""
import hashlib
import pathlib
import tarfile
root=pathlib.Path("saas/console")
assert (root/"index.php").is_file()
assert (root/"_core/.htaccess").is_file()
target=pathlib.Path("dist/saas-console")
target.mkdir(parents=True,exist_ok=True)
files=[x for x in sorted(root.rglob("*")) if x.is_file()]
assert 1<len(files)<=249
assert not any(x.is_symlink() for x in files)
assert not any(x.suffix.lower() in (".env",".sqlite",".db",".key",".pem",".log") for x in files)
tarpath=target/"release.tar.gz"
with tarfile.open(tarpath,"w:gz",format=tarfile.USTAR_FORMAT) as tar:
    tar.add(root,arcname="console",recursive=False)
    directories=[x for x in sorted(root.rglob("*")) if x.is_dir()]
    for directory in directories:
        assert not directory.is_symlink()
        tar.add(directory,arcname="console/"+directory.relative_to(root).as_posix(),recursive=False)
    for file in files:
        tar.add(file,arcname="console/"+file.relative_to(root).as_posix(),recursive=False)
digest=hashlib.sha256(tarpath.read_bytes()).hexdigest()
(target/"release.sha256").write_text(digest+"\n")
print("SAAS_CONSOLE_ARCHIVE_READY",len(files),tarpath.stat().st_size,digest)
