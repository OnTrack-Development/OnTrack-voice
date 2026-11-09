#!/usr/bin/env python3
"""Account-scoped GitHub pull worker. Python 3.9+, standard library only."""
import base64
import datetime as dt
import fcntl
import hashlib
import json
import os
from pathlib import Path
import platform
import re
import selectors
import shutil
import signal
import subprocess
import sys
import time
import tempfile
import urllib.error
import urllib.parse
import urllib.request

REPO = "OnTrack-Development/OnTrack-voice"
BRANCH = "ontrack-host-link"
PREFIX = "ops/host-link"
LIMIT = 32768


def utcnow():
    return dt.datetime.now(dt.timezone.utc).isoformat()


def atomic_json(path, data):
    path = Path(path)
    tmp = path.with_name(path.name + ".tmp")
    fd = os.open(str(tmp), os.O_WRONLY | os.O_CREAT | os.O_TRUNC | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, "w") as stream:
        json.dump(data, stream, ensure_ascii=False, sort_keys=True)
        stream.flush()
        os.fsync(stream.fileno())
    os.replace(str(tmp), str(path))


def redact(value, token):
    value = value.replace(token, "[REDACTED]") if token else value
    value = re.sub(r"(?i)(Bearer\s+)[A-Za-z0-9_.\-]+", r"\1[REDACTED]", value)
    value = re.sub(r"(?:github_pat_|gh[pousr]_)[A-Za-z0-9_]+", "[REDACTED]", value)
    value = re.sub(r"-----BEGIN [^-]*PRIVATE KEY-----[\s\S]*?-----END [^-]*PRIVATE KEY-----", "[PRIVATE KEY REDACTED]", value)
    return value


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class API:
    def __init__(self, token):
        self.token = token
        self.opener = urllib.request.build_opener(NoRedirect())

    def request(self, path, method="GET", payload=None):
        url = "https://api.github.com/repos/" + REPO + "/" + path
        headers = {"Authorization": "Bearer " + self.token,
                   "Accept": "application/vnd.github+json",
                   "X-GitHub-Api-Version": "2022-11-28",
                   "User-Agent": "OnTrack-Host-Link/1.0"}
        raw = None if payload is None else json.dumps(payload).encode()
        req = urllib.request.Request(url, data=raw, headers=headers, method=method)
        try:
            with self.opener.open(req, timeout=12) as response:
                raw = response.read(2 * 1024 * 1024 + 1)
                if len(raw) > 2 * 1024 * 1024:
                    raise ValueError("GitHub response too large")
                return json.loads(raw)
        except urllib.error.HTTPError as exc:
            if exc.code == 404:
                return None
            raise RuntimeError("GitHub HTTP %s" % exc.code) from None

    def get(self, path):
        return self.request("contents/" + urllib.parse.quote(path, safe="/") +
                            "?ref=" + urllib.parse.quote(BRANCH, safe=""))

    def put(self, path, value):
        current = self.get(path)
        payload = {"branch": BRANCH, "message": "host-link: report " + value["job_id"],
                   "content": base64.b64encode((json.dumps(value, ensure_ascii=False, indent=2) + "\n").encode()).decode()}
        if current:
            if base64.b64decode(current["content"]).decode() == base64.b64decode(payload["content"]).decode():
                return
            payload["sha"] = current["sha"]
        result = self.request("contents/" + urllib.parse.quote(path, safe="/"), "PUT", payload)
        if not result or "commit" not in result:
            raise RuntimeError("GitHub did not confirm report commit")


def verify_exec_job_signature(job, cfg):
    """Reject unsigned/modified exec jobs even if the GitHub branch is writable.

    The operator's Ed25519 public key is installed on the host outside Git.
    The signature covers canonical JSON of all fields except 'signature'.
    The worker never reads a private signing key.
    """
    signature = job.get("signature")
    if not isinstance(signature, str) or len(signature) > 256:
        raise ValueError("Signed host job required")
    try:
        sig = base64.b64decode(signature, validate=True)
    except (ValueError, base64.binascii.Error):
        raise ValueError("Invalid job signature encoding") from None
    if len(sig) != 64:
        raise ValueError("Invalid Ed25519 signature length")
    account_home = Path(cfg["account_home"]).resolve()
    key_path = Path(cfg.get("operator_pubkey_path", "")).expanduser()
    if not key_path.is_file() or key_path.is_symlink():
        raise ValueError("Operator public key not provisioned")
    pub = key_path.resolve()
    if pub.parent != account_home and account_home not in pub.parents:
        raise ValueError("Operator key outside host account")
    openssl = shutil.which("openssl")
    if openssl is None:
        raise ValueError("OpenSSL verification unavailable")
    data = {k: v for k, v in job.items() if k != "signature"}
    payload = json.dumps(data, ensure_ascii=False, sort_keys=True,
                         separators=(",", ":")).encode("utf-8")
    if len(payload) > 16384:
        raise ValueError("Job payload too large")
    with tempfile.TemporaryDirectory(prefix=".hostlink-verify-", dir=account_home) as folder:
        folder = Path(folder)
        message = folder / "message.json"
        signature_file = folder / "signature.bin"
        for path, content in ((message, payload), (signature_file, sig)):
            fd = os.open(str(path), os.O_CREAT | os.O_WRONLY | os.O_EXCL | os.O_NOFOLLOW, 0o600)
            with os.fdopen(fd, "wb") as output:
                output.write(content)
        check = subprocess.run([openssl, "pkeyutl", "-verify", "-rawin",
                                "-pubin", "-inkey", str(pub), "-in", str(message),
                                "-sigfile", str(signature_file)],
                               stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                               stderr=subprocess.DEVNULL, timeout=5, check=False)
    if check.returncode != 0:
        raise ValueError("Host job signature rejected")


def validate_job(job, cfg):
    if not isinstance(job, dict) or job.get("schema") != 1:
        raise ValueError("Unsupported job schema")
    if not re.fullmatch(r"[a-zA-Z0-9][a-zA-Z0-9_-]{0,79}", str(job.get("id", ""))):
        raise ValueError("Invalid job id")
    if job.get("target") != cfg["target"]:
        raise ValueError("Wrong target")
    created = dt.datetime.fromisoformat(job["created_at"].replace("Z", "+00:00"))
    expires = dt.datetime.fromisoformat(job["expires_at"].replace("Z", "+00:00"))
    now = dt.datetime.now(dt.timezone.utc)
    if created.tzinfo is None or expires.tzinfo is None:
        raise ValueError("Timestamps need timezone")
    if created > now + dt.timedelta(minutes=5) or expires <= now or expires <= created:
        raise ValueError("Expired job or incorrect clock")
    if expires - created > dt.timedelta(days=7):
        raise ValueError("Job validity exceeds seven days")
    action = job.get("action")
    if action not in ("probe", "exec"):
        raise ValueError("Unsupported action")
    if action == "exec":
        if cfg.get("allow_exec") is not True:
            raise ValueError("exec disabled in local configuration")
        verify_exec_job_signature(job, cfg)
        argv = job.get("argv")
        if not isinstance(argv, list) or not argv or len(argv) > 100 or any(not isinstance(x, str) or "\x00" in x for x in argv):
            raise ValueError("argv must be a nonempty array of strings")
        cwd = Path(job.get("cwd", cfg["project_root"])).resolve()
        home = Path(cfg["account_home"]).resolve()
        if cwd != home and home not in cwd.parents:
            raise ValueError("Working directory outside account home")
        if not cwd.is_dir():
            raise ValueError("Working directory does not exist")
        timeout = job.get("timeout_seconds", 15)
        if type(timeout) is not int or not 1 <= timeout <= 30:
            raise ValueError("Timeout must be 1..30 seconds")


def run_exec(job, cfg):
    env = {key: os.environ[key] for key in ("PATH", "HOME", "LANG", "LC_ALL", "TZ", "TMPDIR") if key in os.environ}
    process = subprocess.Popen(job["argv"], cwd=job.get("cwd", cfg["project_root"]),
                               env=env, stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                               stderr=subprocess.PIPE, start_new_session=True)
    selector = selectors.DefaultSelector()
    outputs = {"stdout": bytearray(), "stderr": bytearray()}
    truncated = False
    timed_out = False
    deadline = time.monotonic() + job.get("timeout_seconds", 15)
    for name, pipe in (("stdout", process.stdout), ("stderr", process.stderr)):
        os.set_blocking(pipe.fileno(), False)
        selector.register(pipe, selectors.EVENT_READ, name)
    try:
        while selector.get_map():
            remaining = deadline - time.monotonic()
            if remaining <= 0:
                timed_out = True
                break
            for key, _ in selector.select(min(remaining, 0.2)):
                chunk = os.read(key.fd, 8192)
                if not chunk:
                    selector.unregister(key.fileobj)
                    key.fileobj.close()
                    continue
                target = outputs[key.data]
                available = max(0, LIMIT - len(target))
                target.extend(chunk[:available])
                truncated = truncated or len(chunk) > available
        # A process can close stdout/stderr and continue running.
        if not timed_out:
            try:
                process.wait(timeout=max(0.001, deadline - time.monotonic()))
            except subprocess.TimeoutExpired:
                timed_out = True
    finally:
        try:
            os.killpg(process.pid, signal.SIGKILL)
        except ProcessLookupError:
            pass
        process.wait()
        selector.close()
        for pipe in (process.stdout, process.stderr):
            if not pipe.closed:
                pipe.close()
    return {"status": "timeout" if timed_out else ("success" if process.returncode == 0 else "failed"),
            "exit_code": process.returncode, "output_truncated": truncated,
            **{k: v.decode("utf-8", "replace") for k, v in outputs.items()}}


def probe(cfg):
    tools = {}
    for name in ("php", "python3", "node", "npm", "git", "asterisk", "ffmpeg", "adb", "crontab"):
        tools[name] = shutil.which(name)
    root = Path(cfg["project_root"])
    return {"status": "success", "uid": os.getuid(), "hostname": platform.node(),
            "python": platform.python_version(), "account_home": cfg["account_home"],
            "project_root": str(root), "tools": tools,
            "project_entries": sorted(p.name for p in root.iterdir() if not p.name.startswith("."))[:80] if root.is_dir() else []}


def tick(home, cfg, api):
    home = Path(home)
    statefile = home / "state.json"
    state = json.loads(statefile.read_text()) if statefile.exists() else {"completed": {}, "active": None}
    active = state.get("active")
    # Never replay a job after a crash between starting and persisting its result.
    if active:
        result = active.get("result") or {"job_id": active["id"], "target": cfg["target"],
                    "job_sha256": active["digest"], "status": "interrupted_unknown",
                    "finished_at": utcnow(), "note": "Not replayed; inspect effects before issuing a new job."}
        result = json.loads(redact(json.dumps(result, ensure_ascii=False), cfg["token"]))
        api.put(PREFIX + "/results/" + cfg["target"] + "/" + active["id"] + ".json", result)
        state["completed"][active["id"]] = active["digest"]
        state["active"] = None
        atomic_json(statefile, state)
    response = api.get(PREFIX + "/jobs/" + cfg["target"] + ".json")
    if not response:
        return
    content = base64.b64decode(response["content"])
    if len(content) > 16384:
        raise ValueError("Job too large")
    digest = hashlib.sha256(content).hexdigest()
    job = json.loads(content)
    job_id = job.get("id") if isinstance(job, dict) else None
    if job_id in state["completed"]:
        if state["completed"][job_id] != digest:
            raise ValueError("Job id reused with different contents")
        return
    # An expired, not-yet-started job is a terminal validation condition, not
    # a reason to crash every Cron minute. Persist a receipt so publishers can
    # unblock safely without ever replaying an uncertain running action.
    try:
        validate_job(job, cfg)
    except ValueError as exc:
        if str(exc) != "Expired job or incorrect clock":
            raise
        result = {"job_id": job_id, "target": cfg["target"],
                  "job_sha256": digest, "status": "expired",
                  "finished_at": utcnow(), "note": "Rejected before execution"}
        api.put(PREFIX + "/results/" + cfg["target"] + "/" + job_id + ".json", result)
        state["completed"][job_id] = digest
        atomic_json(statefile, state)
        return
    state["active"] = {"id": job_id, "digest": digest}
    atomic_json(statefile, state)
    started = time.monotonic()
    result = {"job_id": job_id, "target": cfg["target"], "job_sha256": digest, "started_at": utcnow()}
    try:
        result.update(probe(cfg) if job["action"] == "probe" else run_exec(job, cfg))
    except Exception as exc:
        result.update({"status": "failed", "error": str(exc)})
    result.update({"finished_at": utcnow(), "duration_ms": round((time.monotonic() - started) * 1000)})
    # Persist BEFORE network transmission, so upload failures do not rerun commands.
    state["active"]["result"] = json.loads(redact(json.dumps(result, ensure_ascii=False), cfg["token"]))
    atomic_json(statefile, state)
    api.put(PREFIX + "/results/" + cfg["target"] + "/" + job_id + ".json", state["active"]["result"])
    state["completed"][job_id] = digest
    state["active"] = None
    atomic_json(statefile, state)


def main():
    os.umask(0o077)
    home = Path(__file__).resolve().parent
    if (home / "STOP").exists():
        return
    configpath = home / "config.json"
    if configpath.is_symlink() or configpath.stat().st_mode & 0o077:
        raise ValueError("config.json must be a private regular file (chmod 600)")
    cfg = json.loads(configpath.read_text())
    fd = os.open(str(home / "worker.lock"), os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, "w") as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            return
        tick(home, cfg, API(cfg["token"]))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        # Do not serialize request headers or config on errors.
        print("Host Link: " + type(exc).__name__ + " (see private config/job and GitHub access)", file=sys.stderr)
        sys.exit(1)
