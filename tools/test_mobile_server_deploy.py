#!/usr/bin/env python3
"""No-network regression for safe pilot PHP publishing."""
import hashlib
import io
import os
from pathlib import Path
import shutil
import sys
import tarfile
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parent))
from deploy_mobile_server import install_archive, parse_release, download_release
from pack_mobile_server import package

PHP = shutil.which("php") or "/usr/bin/php"
SOURCE = "a" * 40


def release(*, bad_php=False, extra=None):
    files = {".htaccess": b'Options -Indexes\n<Files "auth.sha256">\n Require all denied\n</Files>\n',
             "common.php": b"<?php echo 'safe';\n"}
    for i in range(17):
        files["module_%02d.php" % i] = b"<?php return true;\n"
    if bad_php:
        files["module_04.php"] = b"<?php syntax error;\n"
    buf = io.BytesIO()
    with tarfile.open(fileobj=buf, mode="w:gz") as tar:
        for name, data in files.items():
            info = tarfile.TarInfo("server/" + name)
            info.size = len(data)
            tar.addfile(info, io.BytesIO(data))
        if extra:
            name, data, kind = extra
            info = tarfile.TarInfo(name)
            info.type = kind
            info.size = len(data) if kind == tarfile.REGTYPE else 0
            tar.addfile(info, io.BytesIO(data) if kind == tarfile.REGTYPE else None)
    return buf.getvalue()


class PilotServerPublishTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        base = Path(self.tmp.name)
        self.root = base / "saas" / "mobile" / "server"
        self.root.mkdir(parents=True)
        self.private = base / "private"
        self.private.mkdir(mode=0o700)
        (self.root / "auth.sha256").write_text("dummy-non-secret-auth-hash")
        (self.root / "common.php").write_text("<?php echo 'old';")
        (self.root / "local-file.txt").write_text("preserve me")

    def install(self, raw, *, source=SOURCE, uid=None):
        return install_archive(raw, hashlib.sha256(raw).hexdigest(), source,
                               root=self.root, private=self.private, php=PHP,
                               expected_uid=os.getuid() if uid is None else uid)

    def test_atomic_publish_preserves_auth_and_old_files_and_backup(self):
        raw = release()
        result = self.install(raw)
        self.assertEqual(result["status"], "deployed")
        self.assertEqual(result["php_files"], 18)
        self.assertTrue(result["auth_hash_preserved"])
        self.assertEqual((self.root / "auth.sha256").read_text(), "dummy-non-secret-auth-hash")
        self.assertEqual((self.root / "local-file.txt").read_text(), "preserve me")
        self.assertEqual((self.root / "common.php").read_text(), "<?php echo 'safe';\n")
        backup = self.private / "pilot-server-backups" / SOURCE[:12]
        self.assertEqual((backup / "common.php").read_text(), "<?php echo 'old';")
        self.assertEqual((backup / "auth.sha256").read_text(), "dummy-non-secret-auth-hash")
        with self.assertRaisesRegex(ValueError, "release_already_installed"):
            self.install(raw)

    def test_invalid_php_cannot_replace_live_directory(self):
        with self.assertRaisesRegex(RuntimeError, "host_php_lint_failed"):
            self.install(release(bad_php=True))
        self.assertEqual((self.root / "common.php").read_text(), "<?php echo 'old';")
        self.assertFalse((self.private / "pilot-server-backups" / SOURCE[:12]).exists())

    def test_digest_and_identity_mismatch_fail_closed(self):
        raw = release()
        with self.assertRaisesRegex(ValueError, "release_sha_mismatch"):
            install_archive(raw, "0" * 64, SOURCE, root=self.root, private=self.private,
                            php=PHP, expected_uid=os.getuid())
        with self.assertRaisesRegex(ValueError, "wrong_host_uid"):
            self.install(raw, uid=os.getuid() + 1)

    def test_traversal_and_symlink_in_archive_rejected(self):
        for extra in [("server/../escape.php", b"<?php", tarfile.REGTYPE),
                      ("server/link.php", b"", tarfile.SYMTYPE)]:
            with self.assertRaisesRegex(ValueError, "invalid_release_member"):
                parse_release(release(extra=extra))

    def test_duplicate_member_rejected(self):
        with self.assertRaisesRegex(ValueError, "duplicate_or_oversized_member"):
            parse_release(release(extra=("server/common.php", b"<?php", tarfile.REGTYPE)))

    def test_existing_symlink_blocks_publish(self):
        (self.root / "external.php").symlink_to(self.private / "secret.txt")
        with self.assertRaisesRegex(ValueError, "target_contains_symlink"):
            self.install(release())
        self.assertEqual((self.root / "common.php").read_text(), "<?php echo 'old';")

    def test_missing_host_auth_blocks_release(self):
        (self.root / "auth.sha256").unlink()
        with self.assertRaisesRegex(ValueError, "missing_protected_device_auth"):
            self.install(release())
        self.assertEqual((self.root / "common.php").read_text(), "<?php echo 'old';")

    def test_packaging_excludes_tracked_auth_verifier(self):
        (self.root / ".htaccess").write_text(
            'Options -Indexes\\n<Files "auth.sha256">\\n Require all denied\\n</Files>\\n')
        for i in range(17):
            (self.root / ("module_%02d.php" % i)).write_text("<?php return true;")
        archive = self.private / "release.tar.gz"
        self.assertEqual(package(self.root, archive), 19)
        names = parse_release(archive.read_bytes())
        self.assertNotIn("auth.sha256", names)
        self.assertIn("common.php", names)
        self.assertIn(".htaccess", names)

    def test_non_https_or_untrusted_artifact_rejected(self):
        for url in ("file:///tmp/release.zip", "https://example.com/file.zip",
                    "http://x.blob.core.windows.net/archive.zip"):
            with self.assertRaisesRegex(ValueError, "untrusted_artifact_url"):
                download_release(url)


if __name__ == "__main__":
    unittest.main(verbosity=2)
