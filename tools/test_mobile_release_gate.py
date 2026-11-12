#!/usr/bin/env python3
"""Synthetic release-gate regression: no GitHub network, no APK, no host calls."""
import hashlib
import json
import pathlib
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
import queue_mobile_native as native

RUN = "41306044730"
SHA = "a" * 64
UI = {"payload": json.dumps({"revision": 18, "sha256": SHA})}
JOB = {"id": "mobile-ota-" + RUN}
OK = {
    "job_id": JOB["id"],
    "target": native.TARGET,
    "status": "success",
    "exit_code": 0,
    "stdout": json.dumps({
        "status": "installed",
        "scope": "mobile/updates",
        "revision": 18,
        "sha256": SHA,
    }),
}


class NativeReleaseGateTests(unittest.TestCase):
    def check_denied(self, job=None, report=None, envelope=None):
        with self.assertRaises(RuntimeError):
            native.require_successful_ui_release(
                JOB if job is None else job,
                OK if report is None else report,
                RUN,
                UI if envelope is None else envelope,
            )

    def test_host_receipt_success_for_exact_signed_ui(self):
        native.require_successful_ui_release(JOB, OK, RUN, UI)

    def test_failed_host_ui_blocks_native_even_if_actions_publish_passed(self):
        self.check_denied(report={**OK, "status": "failed", "exit_code": 1,
                                  "stderr": "Revision cannot be reused with different content"})

    def test_queue_only_does_not_count_as_host_success(self):
        self.check_denied(report={**OK, "status": "queued"})
        self.check_denied(report={**OK, "status": "success", "exit_code": None})

    def test_stale_or_other_run_receipt_cannot_release(self):
        self.check_denied(job={"id": "mobile-ota-41259385082"})
        self.check_denied(report={**OK, "job_id": "mobile-ota-41259385082"})
        self.check_denied(report={**OK, "target": "different-host"})

    def test_wrong_revision_or_artifact_sha_blocks_native(self):
        self.check_denied(report={**OK, "stdout": json.dumps({
            "status": "installed", "scope": "mobile/updates",
            "revision": 17, "sha256": SHA})})
        self.check_denied(report={**OK, "stdout": json.dumps({
            "status": "installed", "scope": "mobile/updates",
            "revision": 18, "sha256": "b"*64})})
        self.check_denied(envelope={"payload": json.dumps({"revision": 19, "sha256": SHA})})

    def test_corrupt_or_incomplete_host_proof_blocks_native(self):
        self.check_denied(report={**OK, "stdout": "not-json"})
        self.check_denied(report={**OK, "stdout": "{}"})
        self.check_denied(report={**OK, "stdout": json.dumps({
            "status": "installed", "scope": "mobile/updates",
            "revision": "18", "sha256": SHA})})
        self.check_denied(envelope={"payload": "{}"})

    def test_wait_queue_rejects_receipt_sha_mismatch(self):
        raw = json.dumps(JOB).encode()
        report = {**OK, "job_sha256": "0"*64}
        with patch.object(native, "read", side_effect=[({"sha": "queue"}, raw),
                                                        ({}, json.dumps(report).encode())]), \
             patch.object(native.time, "sleep", side_effect=RuntimeError("queue_not_confirmed")):
            with self.assertRaisesRegex(RuntimeError, "queue_not_confirmed"):
                native.wait_queue()

    def test_wait_queue_returns_verified_host_report(self):
        raw = json.dumps(JOB).encode()
        report = {**OK, "job_sha256": hashlib.sha256(raw).hexdigest()}
        with patch.object(native, "read", side_effect=[({"sha": "queue"}, raw),
                                                        ({}, json.dumps(report).encode())]):
            obj, job, verified = native.wait_queue()
        self.assertEqual(obj["sha"], "queue")
        self.assertEqual(job["id"], JOB["id"])
        self.assertEqual(verified, report)


if __name__ == "__main__":
    unittest.main(verbosity=2)
