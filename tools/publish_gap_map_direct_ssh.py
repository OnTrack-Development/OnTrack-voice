#!/usr/bin/env python3
"""Publish only the architecture map to the authorized host over direct SSH.

Never touches the SaaS runtime, Android, APK, OTA or Host-Link queue.
Requires ONTRACK_GAP_MAP_PIN from a private Actions secret / runner environment,
NOT a source file. No PIN or verifier is printed to stdout/stderr.
"""
import http.cookiejar
import io
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tarfile
import tempfile
import urllib.error
import urllib.parse
import urllib.request

ROOT=Path(__file__).resolve().parents[1]
REPO="OnTrack-Development/OnTrack-voice"
BRANCH="fix/saas-21-closure-20261110"
HOST="65.21.208.232"
PORT="7195"
USER="appontrack"
ORIGIN="https://app.ontrackegy.com"
REMOTE_PRIVATE="/home/appontrack/.ontrack-gap-map"
REMOTE_PUBLIC="/home/appontrack/public_html/architecture-map"
WEB_FILES=["index.php","_auth.php","status.php","verify.php","style.css","app.js",".htaccess"]

def run(args,secret=False):
    completed=subprocess.run(args,capture_output=True,text=True,check=False,timeout=65)
    if completed.returncode:
        if secret:raise RuntimeError("secure PIN hash provisioning failed (details intentionally hidden)")
        raise RuntimeError("direct SSH / SCP operation failed; no credentials printed")
    return completed.stdout.strip()

def ssh(key,cmd):
    return run(["ssh.exe","-i",str(key),"-p",PORT,
        "-o","BatchMode=yes","-o","IdentitiesOnly=yes",
        "-o","StrictHostKeyChecking=yes","-o","ConnectTimeout=12",
        USER+"@"+HOST,cmd])

def scp(key,local,remote):
    return run(["scp.exe","-i",str(key),"-P",PORT,
        "-o","BatchMode=yes","-o","IdentitiesOnly=yes",
        "-o","StrictHostKeyChecking=yes","-o","ConnectTimeout=12",
        str(local),USER+"@"+HOST+":"+remote])

def main():
    if os.getenv("GITHUB_ACTIONS")!="true" or os.getenv("GITHUB_REPOSITORY")!=REPO:
        raise RuntimeError("authorized GitHub Actions environment required")
    sha=os.getenv("GITHUB_SHA","")
    if not re.fullmatch(r"[a-f0-9]{40}",sha):
        raise RuntimeError("GitHub sha missing or malformed")
    pin=os.getenv("ONTRACK_GAP_MAP_PIN","")
    if not re.fullmatch(r"[0-9]{6}",pin):
        raise RuntimeError("GAP_MAP_PIN_NOT_CONFIGURED: private GitHub Actions secret required")
    key=Path.home()/".ssh"/"ontrack_publish_ed25519"
    if not key.is_file():
        raise RuntimeError("authorized BEDIR runner SSH identity not available")
    status=ROOT/"dist/gap-map/status.json"
    if not status.is_file():raise RuntimeError("verified snapshot artifact missing")
    parsed=json.loads(status.read_text(encoding="utf-8"))
    if parsed.get("schema")!=2 or parsed.get("commit")!=sha or len(parsed.get("gaps",[]))<8:
        raise RuntimeError("snapshot SHA or gap data not from checked out commit")
    for name in WEB_FILES:
        if not (ROOT/"ops/gap-map/web"/name).is_file():
            raise RuntimeError("map web source file missing")
    # PHP CLI on Windows runner computes bcrypt, never echoing password or hash.
    command='echo password_hash(getenv("ONTRACK_GAP_MAP_PIN"), PASSWORD_DEFAULT);'
    verifier=run(["php","-r",command],secret=True)
    if not verifier.startswith("$2y$") or len(verifier)<55:
        raise RuntimeError("invalid private password verifier")
    job=os.getenv("GITHUB_RUN_ID","manual")
    if not re.fullmatch(r"\d{4,20}",job):
        raise RuntimeError("invalid GitHub Actions job ID")
    with tempfile.TemporaryDirectory(prefix="gap-map-release-") as folder:
        tarpath=Path(folder)/("gap-map-"+job+".tar.gz")
        with tarfile.open(tarpath,"w:gz") as archive:
            for name in WEB_FILES:
                archive.add(ROOT/"ops/gap-map/web"/name,arcname="web/"+name,recursive=False)
            archive.add(status,arcname="status.json",recursive=False)
            raw=(verifier+"\n").encode("utf-8")
            meta=tarfile.TarInfo("pin.hash");meta.size=len(raw);meta.mode=0o600
            archive.addfile(meta,io.BytesIO(raw))
        # Only append an archive into an account-owned private folder.
        prep=("set -eu; umask 077; "
            "test ! -L "+REMOTE_PRIVATE+"; "
            "mkdir -p "+REMOTE_PRIVATE+"/incoming "+REMOTE_PRIVATE+"/backups; "
            "chmod 700 "+REMOTE_PRIVATE+" "+REMOTE_PRIVATE+"/incoming "+REMOTE_PRIVATE+"/backups")
        ssh(key,prep)
        remote=REMOTE_PRIVATE+"/incoming/gap-map-"+job+".tar.gz"
        scp(key,tarpath,remote)
        # Use a new release staging directory, lint PHP, back up existing site.
        # The only public directory touched is the new architecture-map folder.
        update=("set -eu; umask 077; "
            "base="+REMOTE_PRIVATE+"; target="+REMOTE_PUBLIC+"; "
            "stage=\"$base/incoming/release-"+job+"\"; "
            "test ! -L \"$target\"; mkdir -p \"$stage\" \"$target\"; "
            "tar -xzf \""+remote+"\" -C \"$stage\"; "
            "for p in index.php _auth.php status.php verify.php; do php -l \"$stage/web/$p\" >/dev/null; done; "
            "backup=\"$base/backups/"+job+"\"; mkdir -p \"$backup\"; "
            "cp -a \"$target/.\" \"$backup/\" 2>/dev/null || true; "
            "if test -f \"$base/pin.hash\"; then cp -p \"$base/pin.hash\" \"$backup/old-pin.hash\"; fi; "
            "if test -f \"$base/status.json\"; then cp -p \"$base/status.json\" \"$backup/old-status.json\"; fi; "
            "install -m 0600 \"$stage/pin.hash\" \"$base/pin.hash\"; "
            "install -m 0600 \"$stage/status.json\" \"$base/status.json.new-"+job+"\"; "
            "mv \"$base/status.json.new-"+job+"\" \"$base/status.json\"; "
            "for p in index.php _auth.php status.php verify.php style.css app.js .htaccess; do "
            " install -m 0644 \"$stage/web/$p\" \"$target/$p\"; done; "
            "chmod 0755 \"$target\"; "
            "echo MAP_FILES_INSTALLED_NO_SAAS_MOBILE_CHANGE")
        installed=ssh(key,update)
        if "MAP_FILES_INSTALLED_NO_SAAS_MOBILE_CHANGE" not in installed:
            raise RuntimeError("host map upload receipt missing")
    # HTTPS verify: anonymous status must be denied and verified PIN must work
    # without exposing credentials to network logs outside the target host.
    opener=urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    with opener.open(ORIGIN+"/architecture-map/",timeout=20) as response:
        public=response.read(120000).decode("utf-8","replace")
        if response.status!=200 or "Architecture Map" not in public:
            raise RuntimeError("map PIN entrypoint did not return expected HTML")
    csrf=re.search(r'name="csrf" value="([0-9a-f]{48})"',public)
    if not csrf:raise RuntimeError("map login CSRF token missing")
    anonymous=urllib.request.build_opener()
    try:
        anonymous.open(ORIGIN+"/architecture-map/status.php",timeout=20)
        raise RuntimeError("anonymous map status unexpectedly public")
    except urllib.error.HTTPError as exc:
        if exc.code!=401:raise RuntimeError("unexpected anonymous map auth status "+str(exc.code))
    fields=urllib.parse.urlencode({"pin":pin,"csrf":csrf.group(1)}).encode()
    with opener.open(urllib.request.Request(ORIGIN+"/architecture-map/",data=fields),timeout=20) as response:
        if response.status!=200 or "/architecture-map/" not in response.geturl():
            raise RuntimeError("private PIN session handshake failed")
    with opener.open(ORIGIN+"/architecture-map/status.php",timeout=20) as response:
        data=json.loads(response.read(131073))
        if response.status!=200 or data.get("commit")!=sha or data.get("ok") is not True:
            raise RuntimeError("private map JSON snapshot SHA verification failed")
    print("GAP_MAP_DIRECT_SSH_PUBLISHED_AND_PIN_HTTP_VERIFIED sha="+sha[:12]+" url="+ORIGIN+"/architecture-map/")
    return 0

if __name__=="__main__":
    try:sys.exit(main())
    except Exception as e:
        # Never include private key paths or PIN contents in Actions output.
        print("GAP_MAP_PUBLISH_FAILED: "+str(e),file=sys.stderr)
        sys.exit(1)
