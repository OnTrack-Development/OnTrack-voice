"""Pinned, rollback-capable PHP-only pilot server publisher.

Does not install APKs, alter private phone settings, or touch auth.sha256.
The host worker invokes publish() with a CI-generated immutable artifact URL.
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

HOST_ROOT = pathlib.Path("/home/appontrack/public_html/saas/mobile/server")
HOST_PRIVATE = pathlib.Path("/home/appontrack/.ontrack-mobile")
PHP83 = "/opt/cpanel/ea-php83/root/usr/bin/php"


def valid_name(name):
    return name == "server/.htaccess" or bool(re.fullmatch(r"server/[A-Za-z0-9_-]+\.php", name))


def parse_release(raw):
    """Reject links, nested paths, duplicate names and oversized archives."""
    if len(raw) > 2 * 1024 * 1024:
        raise ValueError("release_too_large")
    entries = {}
    with tarfile.open(fileobj=io.BytesIO(raw), mode="r:gz") as archive:
        members = archive.getmembers()
        if len(members) > 90:
            raise ValueError("too_many_members")
        for member in members:
            if member.isdir() and member.name.rstrip("/") == "server":
                continue
            if not member.isfile() or not valid_name(member.name):
                raise ValueError("invalid_release_member")
            if member.name in entries or member.size > 512 * 1024:
                raise ValueError("duplicate_or_oversized_member")
            handle = archive.extractfile(member)
            if handle is None:
                raise ValueError("unreadable_member")
            entries[member.name[7:]] = handle.read(512 * 1024 + 1)
    if len(entries) < 18 or ".htaccess" not in entries or "common.php" not in entries:
        raise ValueError("incomplete_pilot_server_release")
    if b'Require all denied' not in entries[".htaccess"] or b'auth.sha256' not in entries[".htaccess"]:
        raise ValueError("auth_hash_must_remain_denied")
    return entries


def assert_no_symlinks(directory):
    if directory.is_symlink() or not directory.is_dir():
        raise ValueError("unsafe_target_directory")
    for parent, folders, files in os.walk(directory, followlinks=False):
        for name in folders + files:
            if (pathlib.Path(parent) / name).is_symlink():
                raise ValueError("target_contains_symlink")


def install_archive(raw, digest, source, *, root=HOST_ROOT, private=HOST_PRIVATE,
                    php=PHP83, expected_uid=2379):
    root, private = pathlib.Path(root), pathlib.Path(private)
    if os.getuid() != expected_uid:
        raise ValueError("wrong_host_uid")
    if not re.fullmatch(r"[0-9a-f]{40}", source):
        raise ValueError("invalid_source_commit")
    if not re.fullmatch(r"[0-9a-f]{64}", digest) or hashlib.sha256(raw).hexdigest() != digest:
        raise ValueError("release_sha_mismatch")
    if not root.parent.is_dir() or root.parent.is_symlink():
        raise ValueError("unsafe_parent")
    assert_no_symlinks(root)
    if private.is_symlink() or not private.is_dir():
        raise ValueError("unsafe_private_directory")
    auth_file = root / "auth.sha256"
    if not auth_file.is_file() or auth_file.is_symlink():
        raise ValueError("missing_protected_device_auth")
    entries = parse_release(raw)
    backups = private / "pilot-server-backups"
    backups.mkdir(mode=0o700, exist_ok=True)
    if backups.is_symlink():
        raise ValueError("unsafe_backup_directory")
    backups.chmod(0o700)
    backup = backups / source[:12]
    if backup.exists() or backup.is_symlink():
        raise ValueError("release_already_installed")
    stage_parent = pathlib.Path(tempfile.mkdtemp(prefix=".pilot-php-", dir=str(private)))
    stage = stage_parent / "server"
    swapped = False
    try:
        shutil.copytree(root, stage, symlinks=False)
        stage.chmod(0o755)
        for name, data in entries.items():
            path = stage / name
            if path.is_symlink() or (path.exists() and not path.is_file()):
                raise ValueError("unsafe_stage_member")
            path.write_bytes(data)
            path.chmod(0o644)
        for name in sorted(entries):
            if not name.endswith(".php"):
                continue
            result = subprocess.run([php, "-l", str(stage / name)],
                                    capture_output=True, text=True, timeout=3)
            if result.returncode:
                raise RuntimeError("host_php_lint_failed:" + name)
        # Rename the whole directory, preserving untracked auth.sha256 and
        # all existing private state. Never delete or replace the secret.
        os.replace(root, backup)
        try:
            os.replace(stage, root)
            swapped = True
        except BaseException:
            os.replace(backup, root)
            raise
        return {"status": "deployed", "source": source, "sha256": digest,
                "php_files": sum(n.endswith(".php") for n in entries),
                "auth_hash_preserved": (root / "auth.sha256").exists(),
                "backup": str(backup), "target": "/saas/mobile/server/"}
    finally:
        if not swapped and backup.exists() and not root.exists():
            os.replace(backup, root)
        shutil.rmtree(stage_parent, ignore_errors=True)


def download_release(url):
    parsed = urllib.parse.urlsplit(url)
    host = (parsed.hostname or "").lower()
    if parsed.scheme != "https" or not (
        host.endswith(".blob.core.windows.net") or
        host.endswith(".actions.githubusercontent.com")
    ):
        raise ValueError("untrusted_artifact_url")
    req = urllib.request.Request(url, headers={"User-Agent": "OnTrack-Pilot-PHP-Release/1"})
    with urllib.request.urlopen(req, timeout=12) as response:
        blob = response.read(4 * 1024 * 1024 + 1)
    if len(blob) > 4 * 1024 * 1024:
        raise ValueError("artifact_too_large")
    with zipfile.ZipFile(io.BytesIO(blob)) as z:
        names = [m for m in z.infolist() if m.filename == "release.tar.gz"]
        if len(names) != 1 or names[0].file_size > 2 * 1024 * 1024:
            raise ValueError("invalid_artifact_layout")
        return z.read(names[0])


def publish(url, digest, source):
    raw = download_release(url)
    print(json.dumps(install_archive(raw, digest, source), sort_keys=True))
