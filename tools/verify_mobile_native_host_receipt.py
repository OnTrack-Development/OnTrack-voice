#!/usr/bin/env python3
"""Verify the authoritative host worker receipt for a queued Android native OTA.

The worker queue and receipt remain on the existing ontrack-host-link branch.
No SSH credentials or private signing keys are transmitted.
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


def main():
    folder = Path(sys.argv[1])
    _, _, meta = native.verify(folder)
    run_id = os.environ["GITHUB_RUN_ID"]
    expected_job = "mobile-native-" + run_id
    expected_sha = meta["sha256"]
    for attempt in range(50):
        _, job_bytes = native.read(native.QUEUE)
        job = json.loads(job_bytes)
        if job.get("id") != expected_job:
            raise RuntimeError("NATIVE_JOB_NOT_CURRENT: another host job replaced expected release")
        try:
            _, report_bytes = native.read(f"ops/host-link/results/{native.TARGET}/{expected_job}.json")
        except urllib.error.HTTPError as exc:
            if exc.code != 404:
                raise
            print(f"HOST_NATIVE_ACK_PENDING: {attempt+1}/50", flush=True)
            time.sleep(12)
            continue
        report = json.loads(report_bytes)
        if report.get("job_id") != expected_job or report.get("target") != native.TARGET:
            raise RuntimeError("NATIVE_HOST_RECEIPT_ID_MISMATCH")
        if report.get("job_sha256") != hashlib.sha256(job_bytes).hexdigest():
            raise RuntimeError("NATIVE_HOST_RECEIPT_JOB_HASH_MISMATCH")
        if report.get("status") != "success" or report.get("exit_code") != 0:
            raise RuntimeError(f"NATIVE_HOST_DEPLOY_FAILED: {report.get('status')} (exit {report.get('exit_code')})")
        data = json.loads(report.get("stdout") or "{}")
        if (data.get("status") != "published"
                or data.get("version") != meta["version_code"]
                or data.get("sha256") != expected_sha
                or data.get("bytes") != meta["size"]):
            raise RuntimeError("NATIVE_HOST_RECEIPT_PAYLOAD_MISMATCH")
        print("HOST_NATIVE_DEPLOY_VERIFIED: PASS")
        print(json.dumps({"version": meta["version_name"], "version_code": meta["version_code"],
                          "sha256": expected_sha, "site": "https://app.ontrackegy.com/mobile/updates/native/"},
                         sort_keys=True))
        return
    raise RuntimeError("HOST_NATIVE_ACK_TIMEOUT: APK was queued but host publication is not confirmed")


if __name__ == "__main__":
    main()
