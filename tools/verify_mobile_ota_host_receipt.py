#!/usr/bin/env python3
"""Verify that the exact signed UI revision was installed by the OnTrack host worker.

A queued job or a green GitHub build is not proof of deployment. This gate
requires a host receipt bound to the exact queue bytes and signed UI SHA256.
"""
import hashlib
import json
import os
import sys
import time
import urllib.error
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import queue_mobile_native as native
import queue_mobile_ota as ota


def main():
    folder = Path(sys.argv[1])
    _, _, metadata = ota.verify(folder)
    job_id = "mobile-ota-" + os.environ["GITHUB_RUN_ID"]
    for attempt in range(60):
        _, job_bytes = native.read(native.QUEUE)
        job = json.loads(job_bytes)
        if job.get("id") != job_id:
            raise RuntimeError("UI_OTA_JOB_REPLACED: refusing to report success")
        try:
            _, report_bytes = native.read(
                f"ops/host-link/results/{native.TARGET}/{job_id}.json"
            )
        except urllib.error.HTTPError as exc:
            if exc.code != 404:
                raise
            print(f"HOST_UI_ACK_PENDING: {attempt+1}/60", flush=True)
            time.sleep(10)
            continue
        report = json.loads(report_bytes)
        if report.get("job_id") != job_id or report.get("target") != native.TARGET:
            raise RuntimeError("UI_OTA_RECEIPT_ID_OR_TARGET_MISMATCH")
        if report.get("job_sha256") != hashlib.sha256(job_bytes).hexdigest():
            raise RuntimeError("UI_OTA_RECEIPT_JOB_HASH_MISMATCH")
        if report.get("status") != "success" or report.get("exit_code") != 0:
            raise RuntimeError(
                f"UI_OTA_HOST_DEPLOY_FAILED: {report.get('status')} / "
                f"exit={report.get('exit_code')}"
            )
        payload = json.loads(report.get("stdout") or "{}")
        if (payload.get("status") != "installed"
                or payload.get("scope") != "mobile/updates"
                or type(payload.get("revision")) is not int
                or payload["revision"] != metadata["revision"]
                or payload.get("sha256") != metadata["sha256"]):
            raise RuntimeError("UI_OTA_HOST_RECEIPT_PAYLOAD_MISMATCH")
        print("HOST_SIGNED_UI_DEPLOY_VERIFIED: PASS", flush=True)
        print(json.dumps({
            "revision": metadata["revision"],
            "sha256": metadata["sha256"],
            "receipt_job_id": job_id
        }, sort_keys=True), flush=True)
        return
    raise RuntimeError("HOST_UI_ACK_TIMEOUT: signed UI queued but not proven installed")


if __name__ == "__main__":
    main()
