#!/usr/bin/env python3
"""Safely fix archived paid-plan voice access in the live canonical SaaS root.
Single anchored PHP predicate replacement, immutable rollback and no DB writes.
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

ROOT = Path("/home/appontrack/public_html")
STAGE = Path("/home/appontrack/.ontrack-mobile/saas-entitlements-v66")
REL = "_core/TenantVoiceSession.php"
PHP = "/opt/cpanel/ea-php83/root/usr/bin/php"
OLD = "JOIN plans p ON p.id=s.plan_id AND p.status='active'"
NEW = "JOIN plans p ON p.id=s.plan_id"
START = "function tenant_voice_entitled(array $binding):bool {"
END = "/** Validate Google Live access for an authenticated paired SIM"


def sha(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()


def load_file(path: Path) -> bytes:
    if path.is_symlink() or not path.is_file():
        raise RuntimeError("missing_or_symlinked_" + path.name)
    raw = path.read_bytes()
    if not raw or len(raw) > 120_000 or b"\\x00" in raw:
        raise RuntimeError("unsafe_php_contents_" + path.name)
    return raw


def code_section(source: str) -> str:
    if source.count(START) != 1 or source.count(END) != 1:
        raise RuntimeError("voice_entitlement_function_anchor_changed")
    start = source.index(START)
    end = source.index(END, start)
    if start >= end:
        raise RuntimeError("voice_entitlement_function_order_changed")
    return source[start:end]


def syntax_check(source: bytes) -> None:
    fd, name = tempfile.mkstemp(prefix=".v66-php-", suffix=".php", dir=str(STAGE))
    try:
        with os.fdopen(fd, "wb") as out:
            out.write(source)
        lint = subprocess.run([PHP, "-l", name], capture_output=True, timeout=30)
        if lint.returncode != 0:
            raise RuntimeError("v66_merged_php_syntax_invalid")
    finally:
        Path(name).unlink(missing_ok=True)


def atomic_write(path: Path, content: bytes, mode: int) -> None:
    fd, name = tempfile.mkstemp(prefix=".ot-v66-", dir=str(path.parent))
    try:
        with os.fdopen(fd, "wb") as out:
            out.write(content)
            out.flush()
            os.fsync(out.fileno())
        os.chmod(name, mode)
        os.replace(name, path)
    finally:
        Path(name).unlink(missing_ok=True)


def main() -> None:
    if (os.getuid() != 2379 or ROOT.is_symlink() or
        not ROOT.is_dir() or STAGE.is_symlink() or not STAGE.is_dir() or
        stat.S_IMODE(STAGE.stat().st_mode) != 0o700):
        raise RuntimeError("unauthorized_host_or_private_stage")

    target = ROOT / REL
    if target.parent.is_symlink():
        raise RuntimeError("unsafe_target_parent")

    candidate = load_file(STAGE / "new" / REL).decode("utf-8-sig")
    wanted = code_section(candidate)
    if OLD in wanted or wanted.count(NEW) != 1 or "s.status='active'" not in wanted or "s.ends_at>?" not in wanted:
        raise RuntimeError("candidate_voice_entitlement_policy_invalid")

    with (STAGE / "deploy.lock").open("a") as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        original = load_file(target)
        original_text = original.decode("utf-8-sig")
        original_section = code_section(original_text)
        if original_section.count(OLD) == 1:
            patched_section = original_section.replace(OLD, NEW, 1)
            patched = original_text.replace(original_section, patched_section, 1).encode("utf-8")
        elif OLD not in original_section and original_section.count(NEW) == 1:
            patched = original
        else:
            raise RuntimeError("unexpected_live_voice_entitlement_policy")
        current = code_section(patched.decode("utf-8"))
        if OLD in current or current.count(NEW) != 1 or "s.status='active'" not in current or "s.ends_at>?" not in current:
            raise RuntimeError("unsafe_voice_entitlement_mutation")
        if patched.decode("utf-8").count("<?php") != 1:
            raise RuntimeError("unexpected_php_open_tag")
        syntax_check(patched)

        backup = STAGE / ("backup-" + str(int(time.time())) + "-" + str(os.getpid()))
        backup.mkdir(mode=0o700)
        backup_file = backup / "TenantVoiceSession.php"
        backup_file.write_bytes(original)
        backup_file.chmod(0o600)
        if sha(load_file(target)) != sha(original):
            raise RuntimeError("concurrent_live_file_edit")

        changed = original != patched
        if changed:
            mode = stat.S_IMODE(target.stat().st_mode)
            atomic_write(target, patched, mode)
        try:
            live = load_file(target)
            if sha(live) != sha(patched):
                raise RuntimeError("postdeploy_source_hash_mismatch")
            lint = subprocess.run([PHP, "-l", str(target)], capture_output=True, timeout=30)
            if lint.returncode != 0:
                raise RuntimeError("postdeploy_php_lint_failed")
            print("V66_LIVE_ARCHIVED_PLAN_ENTITLEMENT_PATCH_PASS", flush=True)
            print(json.dumps({
                "status": "deployed", "changed": changed, "file": REL,
                "backup": str(backup_file), "before_sha256": sha(original),
                "after_sha256": sha(live), "live_php_lint": "pass",
                "database_mutations": 0, "android_apk_unchanged": True,
                "subscription_rows_unchanged": True
            }, separators=(",", ":")), flush=True)
        except BaseException:
            if changed:
                atomic_write(target, original, stat.S_IMODE(target.stat().st_mode))
            raise


if __name__ == "__main__":
    main()
