#!/usr/bin/env python3
"""Apply v61 precision visual layer on live host, preserving all other CSS.

No database changes, token changes, or PHP changes. Atomic, backup-first.
Only the authorized /home/appontrack user may publish this stylesheet.
"""
from pathlib import Path
import fcntl
import hashlib
import json
import os
import stat
import tempfile
import time

ROOT = Path("/home/appontrack/public_html")
STAGE = Path("/home/appontrack/.ontrack-mobile/saas-ui-v61")
CSS = ROOT / "assets/css/app.css"
CANDIDATE = STAGE / "app.css"
START = "/* OTV_STYLE_V61_BEGIN"
END = "/* OTV_STYLE_V61_END */"

def sha(data):
    return hashlib.sha256(data).hexdigest()

def atomic_write(path, content, mode):
    fd, name = tempfile.mkstemp(dir=str(path.parent), prefix=".ui61-")
    try:
        with os.fdopen(fd, "wb") as output:
            output.write(content)
            output.flush()
            os.fsync(output.fileno())
        os.chmod(name, mode)
        os.replace(name, path)
    finally:
        if os.path.exists(name):
            os.unlink(name)

def main():
    if os.getuid() != 2379:
        raise RuntimeError("wrong_deployment_identity")
    if ROOT.is_symlink() or STAGE.is_symlink() or CSS.is_symlink() or CANDIDATE.is_symlink():
        raise RuntimeError("deployment_symlink_rejected")
    if not CSS.is_file() or not CANDIDATE.is_file():
        raise RuntimeError("site_or_staged_css_missing")
    if stat.S_IMODE(STAGE.stat().st_mode) != 0o700:
        raise RuntimeError("unprotected_staging_folder")
    raw = CANDIDATE.read_bytes()
    if len(raw) > 250000 or b"\0" in raw:
        raise RuntimeError("unexpected_css_payload")
    candidate = raw.decode("utf-8-sig")
    if candidate.count(START) != 1 or candidate.count(END) != 1:
        raise RuntimeError("v61_precision_rules_must_appear_once")
    delta = candidate[candidate.index(START):candidate.index(END) + len(END)]
    if not all(word in delta for word in (
        "--bg:#0d1015;", "--bg:#f5f7fa;", "--accent:#cb2925;",
        "--ot-radius-card:16px;", "body.light .welcome",
        "object-fit:contain!important", "@media (max-width:560px)",
        "@media (prefers-reduced-motion:reduce)"
    )):
        raise RuntimeError("style_contract_invalid")
    lock = STAGE / "publish.lock"
    with lock.open("a") as fh:
        fcntl.flock(fh, fcntl.LOCK_EX | fcntl.LOCK_NB)
        original = CSS.read_bytes()
        if len(original) > 200000 or b"\0" in original:
            raise RuntimeError("unexpected_production_css")
        live = original.decode("utf-8-sig")
        if live.count(START) == 1 and live.count(END) == 1:
            old_delta = live[live.index(START):live.index(END) + len(END)]
            if old_delta == delta:
                print(json.dumps({"status":"ALREADY_PUBLISHED",
                    "file":str(CSS),"sha256":sha(original)}))
                return
            raise RuntimeError("concurrent_v61_live_style_conflict")
        if START in live or END in live:
            raise RuntimeError("incomplete_prior_live_design_block")
        if not live.startswith('@charset "UTF-8";'):
            raise RuntimeError("unexpected_css_header")
        new = (live.rstrip("\r\n") + "\n\n" + delta + "\n").encode("utf-8")
        if new.count(START.encode()) != 1 or new.count(END.encode()) != 1:
            raise RuntimeError("css_patch_validation_failed")
        folder = STAGE / ("backup-" + str(int(time.time())) + "-" + str(os.getpid()))
        folder.mkdir(mode=0o700)
        backup = folder / "app.css"
        backup.write_bytes(original)
        backup.chmod(0o600)
        mode = stat.S_IMODE(CSS.stat().st_mode)
        try:
            if sha(CSS.read_bytes()) != sha(original):
                raise RuntimeError("production_css_changed_concurrently")
            atomic_write(CSS,new,mode)
            got = CSS.read_bytes()
            if sha(got) != sha(new):
                raise RuntimeError("after_write_sha_mismatch")
            if delta.encode() not in got:
                raise RuntimeError("new_visual_layer_not_active")
            print("OTV_STYLE_V61_LIVE_CSS_PASS",flush=True)
            print(json.dumps({
                "status":"PUBLISHED","file":str(CSS),"sha256":sha(got),
                "backup":str(backup),"rules_bytes":len(delta),
                "php_unchanged":True,"database_unchanged":True,
                "customer_files_unchanged":True,"signed_native_unchanged":True
            },separators=(",",":")),flush=True)
        except BaseException:
            atomic_write(CSS,original,mode)
            raise

if __name__ == "__main__":
    main()
