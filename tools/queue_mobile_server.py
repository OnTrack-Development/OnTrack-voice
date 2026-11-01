#!/usr/bin/env python3
"""Queue PHP-only pilot server release through the existing host Cron worker.

Uses the same single-slot queue and receipt guard as SaaS publishing.
"""
import base64
import datetime
import hashlib
import json
import os
from pathlib import Path

from queue_saas_console import (
    api, read, await_previous, artifact_url, BRANCH, QUEUE, TARGET
)


def main():
    if os.environ.get("GITHUB_REF") != "refs/heads/main":
        raise ValueError("main_branch_only")
    folder = Path("dist/pilot-server")
    raw = (folder / "release.tar.gz").read_bytes()
    digest = hashlib.sha256(raw).hexdigest()
    if digest != (folder / "release.sha256").read_text().strip():
        raise ValueError("release_checksum_mismatch")
    previous, prior = await_previous()
    job_id = "pilot-server-" + os.environ["GITHUB_RUN_ID"]
    if prior["id"] == job_id:
        print("already_queued", job_id)
        return
    artifacts = api("actions/runs/" + os.environ["GITHUB_RUN_ID"] + "/artifacts?per_page=100")["artifacts"]
    matches = [a for a in artifacts if a["name"] == "OnTrack-Pilot-PHP-Server" and not a["expired"]]
    if len(matches) != 1:
        raise RuntimeError("missing_immutable_pilot_artifact")
    url = artifact_url(matches[0]["id"])
    installer = Path("tools/deploy_mobile_server.py").read_text()
    script = installer + "\nimport sys\npublish(*sys.argv[1:])\n"
    now = datetime.datetime.now(datetime.timezone.utc)
    job = {
        "schema": 1, "id": job_id, "target": TARGET, "action": "exec",
        "created_at": now.isoformat(),
        "expires_at": (now + datetime.timedelta(hours=3)).isoformat(),
        "cwd": "/home/appontrack/public_html/saas",
        "timeout_seconds": 30,
        "argv": ["/bin/python3", "-c", script, url, digest, os.environ["GITHUB_SHA"]],
    }
    body = json.dumps(job, indent=2).encode()
    if len(body) > 16384:
        raise ValueError("host_job_too_large")
    api("contents/" + QUEUE, "PUT", {
        "branch": BRANCH, "sha": previous["sha"],
        "message": "deploy: PHP-only mobile pilot " + os.environ["GITHUB_SHA"][:12],
        "content": base64.b64encode(body).decode(),
    })
    print(json.dumps({
        "status": "queued", "id": job_id, "sha256": digest,
        "receipt": "ops/host-link/results/" + TARGET + "/" + job_id + ".json",
    }))


if __name__ == "__main__":
    main()
