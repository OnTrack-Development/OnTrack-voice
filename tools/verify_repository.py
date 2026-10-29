#!/usr/bin/env python3
"""Check reference integrity, project status invariants and authored Markdown links."""
from pathlib import Path
import hashlib
import json
import re
import sys
import urllib.parse
import zipfile

ROOT = Path(__file__).resolve().parents[1]
errors = []
counts = {"json": 0, "reference_files": 0, "archives": 0, "links": 0}


def check(condition, message):
    if not condition:
        errors.append(message)


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


for path in ROOT.rglob("*.json"):
    if "__pycache__" in path.parts or ".git" in path.parts:
        continue
    try:
        json.loads(path.read_text())
        counts["json"] += 1
    except (ValueError, UnicodeError) as exc:
        errors.append(str(path.relative_to(ROOT)) + ": invalid JSON " + str(exc))

for name in ("agent-builder-v1.3", "voicecall-v0.6.3"):
    manifest = json.loads((ROOT / "reference/manifests" / (name + ".json")).read_text())
    for item in manifest["entries"]:
        target = item.get("repository_path")
        if target:
            path = ROOT / target
            check(path.is_file(), "Missing imported file: " + target)
            if path.is_file():
                check(digest(path) == item["imported_sha256"], "Import hash mismatch: " + target)
                counts["reference_files"] += 1
        else:
            check(item["status"] == "NOT_COPIED_RUNTIME_OR_BACKUP", "Undocumented exclusion")

for item in json.loads((ROOT / "reference/manifests/archives.json").read_text()):
    path = ROOT / item["path"]
    check(path.is_file(), "Missing archive: " + item["path"])
    if path.is_file():
        check(digest(path) == item["sha256"], "Archive hash mismatch: " + item["path"])
        with zipfile.ZipFile(path) as archive:
            for member in archive.namelist():
                check(not Path(member).is_absolute() and ".." not in Path(member).parts, "Unsafe archive path")
        counts["archives"] += 1

for item in json.loads((ROOT / "docs/evidence/screenshots/index.json").read_text()):
    path = ROOT / item["repository_path"]
    check(path.is_file() and digest(path) == item["sha256"], "Screenshot missing/changed: " + item["repository_path"])

baseline = json.loads((ROOT / "docs/evidence/source-baseline.json").read_text())
basepaths = {x["path"] for x in baseline["files"]}
# Partial preparation workspaces may reuse unchanged tracked blobs remotely.
def exists(path):
    if path.exists():
        return True
    try:
        relative = str(path.relative_to(ROOT))
    except ValueError:
        return False
    return relative in basepaths

docs = list((ROOT / "docs").rglob("*.md")) + [ROOT / x for x in ("README.md", "AGENTS.md", "CLAUDE.md", "CHANGELOG.md")]
for path in docs:
    if "history" in path.parts:
        continue  # Frozen historical plan; its prose is not the current working contract.
    text = path.read_text()
    for href in re.findall(r"!?\[[^\]]*\]\(([^\n)]+)\)", text):
        target = href.strip().split()[0].strip("<>")
        if target.startswith(("https://", "http://", "mailto:", "#")):
            continue
        target = urllib.parse.unquote(target.split("#", 1)[0])
        check(exists((path.parent / target).resolve()), str(path.relative_to(ROOT)) + ": broken link " + href)
        counts["links"] += 1

state = json.loads((ROOT / "docs/project/project-state.json").read_text())
check(state["visibility"] == "private", "Visibility record changed")
check(state["host_control"]["status"] == "VERIFIED_LIVE_CONTROL", "Host proof state changed without review")
check(state["host_control"]["branch"] == "ontrack-host-link", "Wrong operative branch")
check(state["phone_audio"]["status"] == "PENDING_DEVICE_AUDIO_PROOF", "Review actual phone evidence before advancing status")
report = json.loads((ROOT / state["host_control"]["result_path"]).read_text())
check(report["job_id"] == state["host_control"]["last_verified_job_id"] and report["status"] == "success" and report["exit_code"] == 0, "Remote execution evidence mismatch")
probe = json.loads(report["stdout"])
check(probe["host_link_cron_count"] == 1 and probe["host_link_cron_present"], "Cron evidence mismatch")
check(probe["marker_readback"].endswith(report["job_id"]), "File write/read nonce mismatch")

# Installer source is pinned to the tested worker, not auto-updated from branch.
worker = ROOT / "ops/host-link/worker.py"
installer = (ROOT / "ops/host-link/install.py").read_text()
expected = re.search(r'WORKER_SHA256 = "([0-9a-f]{64})"', installer).group(1)
check(digest(worker) == expected, "Pinned worker checksum mismatch")

secret_patterns = [r"AIza[0-9A-Za-z_-]{25,}", r"github_pat_[A-Za-z0-9_]{20,}", r"ghp_[A-Za-z0-9]{30,}", r"omnira_sk_live_[A-Za-z0-9_-]{20,}", r"-----BEGIN (?:OPENSSH |RSA |EC )?PRIVATE KEY-----"]
for path in ROOT.rglob("*"):
    if not path.is_file() or any(x in path.parts for x in (".git", "__pycache__")) or path.suffix in (".zip", ".png", ".pyc"):
        continue
    text = path.read_text(errors="replace")
    for pattern in secret_patterns:
        check(not re.search(pattern, text), "Credential-like value found (value hidden): " + str(path.relative_to(ROOT)))

if errors:
    print(json.dumps({"status": "FAILED", "checks": counts, "errors": errors}, ensure_ascii=False, indent=2))
    sys.exit(1)
print(json.dumps({"status": "PASS", "checks": counts, "note": "Local integrity/document checks, not live telephony proof"}, ensure_ascii=False, indent=2))
