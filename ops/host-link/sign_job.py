#!/usr/bin/env python3
"""Local operator tool: sign host-link job with a private Ed25519 PEM key.

Never commit or upload the PEM. The worker only holds the public key.
Example: ONTRACK_HOSTLINK_SIGN_KEY=/private/operator.pem python3
  ops/host-link/sign_job.py job.json > signed-job.json
"""
import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile


def canonical(job):
    return json.dumps({k: v for k, v in job.items() if k != "signature"},
                      ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")


def sign(job, pem):
    if job.get("action") != "exec" or job.get("schema") != 1:
        raise ValueError("Only schema-1 executable jobs can be signed")
    if "signature" in job:
        raise ValueError("Remove any previous signature before signing")
    content = canonical(job)
    if len(content) > 16384:
        raise ValueError("Job too large")
    pem = Path(pem).resolve(strict=True)
    if pem.is_symlink() or (pem.stat().st_mode & 0o077):
        raise ValueError("Signing key must be a private 0600 file")
    with tempfile.TemporaryDirectory(prefix="ontrack-job-sign-") as tmp:
        message=Path(tmp)/"message.json"
        message.write_bytes(content)
        result=subprocess.run(["openssl", "pkeyutl", "-sign", "-rawin",
                               "-inkey", str(pem), "-in", str(message)],
                              capture_output=True, timeout=10, check=False)
        if result.returncode != 0 or len(result.stdout) != 64:
            raise RuntimeError("Unable to sign with Ed25519 PEM key")
    return dict(job, signature=base64.b64encode(result.stdout).decode("ascii"))


if __name__ == "__main__":
    if len(sys.argv)!=2 or not os.getenv("ONTRACK_HOSTLINK_SIGN_KEY"):
        raise SystemExit("Usage: ONTRACK_HOSTLINK_SIGN_KEY=/private/key.pem sign_job.py unsigned-job.json")
    job=json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
    signed=sign(job, os.environ["ONTRACK_HOSTLINK_SIGN_KEY"])
    print(json.dumps(signed, ensure_ascii=False, sort_keys=True, indent=2))
