import base64
import datetime as dt
import importlib.util
import json
from pathlib import Path
import sys
import tempfile
import unittest

spec = importlib.util.spec_from_file_location("worker", Path(__file__).resolve().parents[1] / "worker.py")
worker = importlib.util.module_from_spec(spec)
spec.loader.exec_module(worker)


class FakeAPI:
    def __init__(self, job, fail_upload=False):
        self.job = job
        self.fail_upload = fail_upload
        self.uploads = []

    def get(self, path):
        if self.job is None:
            return None
        return {"content": base64.b64encode(json.dumps(self.job).encode()).decode()}

    def put(self, path, result):
        if self.fail_upload:
            raise RuntimeError("simulated network failure")
        self.uploads.append((path, result))


class WorkerTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.home = Path(self.temp.name)
        self.cfg = {"token": "test-secret-value", "target": "test-host", "allow_exec": True,
                    "account_home": str(self.home), "project_root": str(self.home)}

    def job(self, **changes):
        now = dt.datetime.now(dt.timezone.utc)
        value = {"schema": 1, "id": "unit-test-1", "target": "test-host", "action": "probe",
                 "created_at": now.isoformat(), "expires_at": (now + dt.timedelta(hours=1)).isoformat()}
        value.update(changes)
        return value

    def test_probe_and_duplicate_not_repeated(self):
        api = FakeAPI(self.job())
        worker.tick(self.home, self.cfg, api)
        worker.tick(self.home, self.cfg, api)
        self.assertEqual(1, len(api.uploads))
        self.assertEqual("success", api.uploads[0][1]["status"])

    def test_upload_failure_does_not_repeat_side_effect(self):
        counter = self.home / "count.txt"
        script = "from pathlib import Path; p=Path(%r); p.write_text(p.read_text()+'x' if p.exists() else 'x')" % str(counter)
        api = FakeAPI(self.job(action="exec", argv=[sys.executable, "-c", script]), fail_upload=True)
        with self.assertRaises(RuntimeError):
            worker.tick(self.home, self.cfg, api)
        self.assertEqual("x", counter.read_text())
        api.fail_upload = False
        worker.tick(self.home, self.cfg, api)
        self.assertEqual("x", counter.read_text())
        self.assertEqual("success", api.uploads[0][1]["status"])

    def test_crash_marks_unknown_without_execution(self):
        counter = self.home / "must-not-exist"
        job = self.job(action="exec", argv=[sys.executable, "-c", "open(%r,'w').write('bad')" % str(counter)])
        import hashlib
        digest = hashlib.sha256(json.dumps(job).encode()).hexdigest()
        worker.atomic_json(self.home / "state.json", {"active": {"id": job["id"], "digest": digest}, "completed": {}})
        api = FakeAPI(job)
        worker.tick(self.home, self.cfg, api)
        self.assertFalse(counter.exists())
        self.assertEqual("interrupted_unknown", api.uploads[0][1]["status"])

    def test_timeout_including_closed_output(self):
        result = worker.run_exec(self.job(action="exec", timeout_seconds=1,
            argv=[sys.executable, "-c", "import os,time; os.close(1);os.close(2);time.sleep(20)"]), self.cfg)
        self.assertEqual("timeout", result["status"])

    def test_stdout_stderr_and_nonzero_exit(self):
        result = worker.run_exec(self.job(action="exec", argv=[sys.executable, "-c",
            "import sys;print('out');print('err',file=sys.stderr);sys.exit(7)"]), self.cfg)
        self.assertEqual("failed", result["status"])
        self.assertEqual(7, result["exit_code"])
        self.assertIn("out", result["stdout"])
        self.assertIn("err", result["stderr"])

    def test_output_bounded(self):
        result = worker.run_exec(self.job(action="exec", argv=[sys.executable, "-c", "print('x'*1000000)"]), self.cfg)
        self.assertLessEqual(len(result["stdout"]), worker.LIMIT)
        self.assertTrue(result["output_truncated"])

    def test_credentials_redacted_before_upload(self):
        api = FakeAPI(self.job(action="exec", argv=[sys.executable, "-c", "print('test-secret-value github_pat_123456 Bearer abc-def')"]))
        worker.tick(self.home, self.cfg, api)
        value = json.dumps(api.uploads)
        self.assertNotIn("test-secret-value", value)
        self.assertNotIn("github_pat_123456", value)
        self.assertNotIn("abc-def", value)

    def test_reused_id_changed_content_rejected(self):
        api = FakeAPI(self.job())
        worker.tick(self.home, self.cfg, api)
        api.job["action"] = "exec"
        with self.assertRaises(ValueError):
            worker.tick(self.home, self.cfg, api)

    def test_invalid_jobs_rejected(self):
        bad = [dict(target="other"), dict(id="../../oops"), dict(action="delete"),
               dict(expires_at="2000-01-01T00:00:00Z"), dict(action="exec", argv="echo x"),
               dict(action="exec", argv=["pwd"], cwd="/"),
               dict(action="exec", argv=["pwd"], timeout_seconds=100)]
        for changes in bad:
            with self.subTest(changes=changes), self.assertRaises((ValueError, KeyError)):
                worker.validate_job(self.job(**changes), self.cfg)

    def test_exec_disabled_locally(self):
        self.cfg["allow_exec"] = False
        with self.assertRaises(ValueError):
            worker.validate_job(self.job(action="exec", argv=["pwd"]), self.cfg)

    def test_shell_text_is_not_interpreted(self):
        marker = self.home / "injected"
        result = worker.run_exec(self.job(action="exec", argv=[sys.executable, "-c", "import sys;print(sys.argv[1])", "$(touch %s)" % marker]), self.cfg)
        self.assertFalse(marker.exists())
        self.assertIn("$(touch", result["stdout"])


if __name__ == "__main__":
    unittest.main()
