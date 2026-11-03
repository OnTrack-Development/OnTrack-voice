#!/usr/bin/env python3
"""No-network test for matching Android APK, manifest, OTA UI, and release transport."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
native = json.loads((ROOT / "saas/mobile/ota/native_release.json").read_text())
ui = json.loads((ROOT / "saas/mobile/ota/release.json").read_text())
overlay = (ROOT / "saas/mobile/audio-proof/prepare.py").read_text()
workflow = (ROOT / ".github/workflows/native-call-reports-release.yml").read_text()
queue_native = (ROOT / "tools/queue_mobile_native.py").read_text()
queue_ui = (ROOT / "tools/queue_mobile_ota.py").read_text()
host_deploy = (ROOT / "saas/mobile/ota/native_deploy.py").read_text()

assert native["schema"] == 1 and native["channel"] == "stable"
assert native["package"] == "com.ontrack.voiceproof"
assert native["version_code"] == 32 and native["version_name"] == "0.3.9"
assert ui["revision"] == 29 and ui["min_native"] <= 20 and ui["max_native"] >= native["version_code"]
assert f"'versionCode = {native['version_code']}'" in overlay
assert f"""'versionName = "{native['version_name']}"'""" in overlay
apk_name = f"OnTrack-Voice-{native['version_name']}.apk"
assert apk_name in workflow and "OnTrack-Voice-0.3.9" in queue_ui
assert "meta['version_name']" in queue_native and "meta['version_name']" in host_deploy
assert "UI_OTA_HOST_ACK_MISSING" in queue_native
assert "Signing identity mismatch" in workflow
assert "d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f" in workflow

with tempfile.TemporaryDirectory() as directory:
    path = Path(directory)
    test_bytes = b"OnTrack release manifest regression" * 100
    apk = path / apk_name
    apk.write_bytes(test_bytes)
    output = path / "native"
    subprocess.run([sys.executable, str(ROOT / "tools/prepare_mobile_native.py"), str(apk), str(output)],
                   check=True, cwd=ROOT, capture_output=True)
    manifest = json.loads((output / "payload.json").read_text())
    assert manifest["version_code"] == 32 and manifest["version_name"] == "0.3.9"
    assert manifest["sha256"] == hashlib.sha256(test_bytes).hexdigest()
    assert manifest["file"] == f"ontrack-voice-v32-{manifest['sha256']}.apk"
    incorrect = path / "OnTrack-Voice-0.3.1.apk"
    incorrect.write_bytes(test_bytes)
    denied = subprocess.run([sys.executable, str(ROOT / "tools/prepare_mobile_native.py"),
                             str(incorrect), str(output)], cwd=ROOT, capture_output=True)
    assert denied.returncode != 0, "Outdated APK filename must never be signed for this release"
print("PASS: Android 0.3.9 / code 32 / UI OTA r29 / signed publication contract")
