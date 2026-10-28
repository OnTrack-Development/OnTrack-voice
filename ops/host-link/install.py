#!/usr/bin/env python3
"""One-time installation; execute only from the reviewed/pinned repository commit."""
import base64
import getpass
import hashlib
import json
import os
from pathlib import Path
import re
import shlex
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request

REPO = "OnTrack-Development/OnTrack-voice"
BRANCH = "ontrack-host-link"
WORKER_SHA256 = "5bf489534b149e01a32a7c58e11bef030415b5a6e5d324a5092d8481f11fc774"


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def main():
    os.umask(0o077)
    if os.getuid() == 0:
        raise RuntimeError("Run as appontrack, not root")
    source_ref = os.environ.pop("ONTRACK_HOST_SOURCE_REF", "")
    if not re.fullmatch(r"[0-9a-f]{40}", source_ref):
        raise RuntimeError("Bootstrap must supply the pinned source commit")
    account_home = Path.home().resolve()
    target_home = account_home / "ontrack-voice-link"
    if target_home.exists():
        raise RuntimeError("ontrack-voice-link already exists; existing installation was preserved")
    public = account_home / "public_html"
    if not public.is_dir():
        raise RuntimeError("public_html was not found; run under the hosting account")
    token = os.environ.pop("ONTRACK_HOST_TOKEN", "") or getpass.getpass("GitHub fine-grained token (hidden): ")
    if not token or any(c.isspace() for c in token):
        raise RuntimeError("Invalid token")
    url = "https://api.github.com/repos/" + REPO + "/contents/ops/host-link/worker.py?ref=" + source_ref
    request = urllib.request.Request(url, headers={"Authorization": "Bearer " + token,
        "Accept": "application/vnd.github+json", "User-Agent": "OnTrack-Host-Link/1.0",
        "X-GitHub-Api-Version": "2022-11-28"})
    with urllib.request.build_opener(NoRedirect()).open(request, timeout=20) as response:
        content = base64.b64decode(json.loads(response.read(262144))["content"])
    if hashlib.sha256(content).hexdigest() != WORKER_SHA256:
        raise RuntimeError("Worker checksum mismatch")
    compile(content, "worker.py", "exec")
    target_home.mkdir(mode=0o700)
    worker = target_home / "worker.py"
    worker.write_bytes(content)
    worker.chmod(0o600)
    cfg = {"token": token, "target": "appontrack-server11", "allow_exec": True,
           "account_home": str(account_home), "project_root": str(public.resolve()),
           "source_commit": source_ref}
    config = target_home / "config.json"
    config.write_text(json.dumps(cfg, indent=2) + "\n")
    config.chmod(0o600)
    # Test both polling and report upload before creating a recurring schedule.
    result = subprocess.run([sys.executable, str(worker)], timeout=65, capture_output=True, text=True)
    if result.returncode:
        raise RuntimeError("Initial poll/report failed. Installation is preserved. Verify token Contents read/write and organization approval, then rerun worker.py")
    statefile = target_home / "state.json"
    if not statefile.exists() or not json.loads(statefile.read_text()).get("completed"):
        raise RuntimeError("No job report was confirmed. Inspect the job queue; installation is preserved and cron was not changed")
    cron = "* * * * * " + shlex.quote(sys.executable) + " " + shlex.quote(str(worker)) + " >" + shlex.quote(str(target_home / "last-run.log")) + " 2>&1 # ontrack-voice-link"
    try:
        current = subprocess.run(["crontab", "-l"], capture_output=True, text=True, timeout=10)
        if current.returncode and not (current.returncode == 1 and not current.stdout.strip() and "no crontab" in current.stderr.lower()):
            raise RuntimeError("Could not read existing crontab; it was not modified")
        lines = [line for line in current.stdout.splitlines() if "# ontrack-voice-link" not in line]
        lines.append(cron)
        subprocess.run(["crontab", "-"], input="\n".join(lines) + "\n", text=True, check=True, timeout=10, capture_output=True)
        print("CONNECTED: initial report uploaded to GitHub; polling installed every minute.")
    except (FileNotFoundError, subprocess.SubprocessError, RuntimeError):
        print("CONNECTED: initial report uploaded. Add this in cPanel Cron Jobs, once per minute:")
        print(cron)
    print("STOP: touch " + shlex.quote(str(target_home / "STOP")))
    print("Results: " + "https://github.com/" + REPO + "/tree/" + BRANCH + "/ops/host-link/results/appontrack-server11")


if __name__ == "__main__":
    try:
        main()
    except urllib.error.HTTPError as exc:
        print("INSTALL FAILED: GitHub HTTP " + str(exc.code) + "; check token/repository/organization approval.", file=sys.stderr)
        sys.exit(1)
    except Exception as exc:
        print("INSTALL FAILED: " + str(exc), file=sys.stderr)
        sys.exit(1)
