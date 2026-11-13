#!/usr/bin/env python3
"""Audit repo source contract before building the signed no-root Voice APK."""
from pathlib import Path
root=Path(__file__).resolve().parents[1]
mobile=root/"saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof"
prepare=(root/"saas/mobile/audio-proof/prepare.py").read_text(encoding="utf-8")
wizard=(mobile/"ProofActivity.kt").read_text(encoding="utf-8")
logic=(mobile/"OnTrackPermissionChecklist.kt").read_text(encoding="utf-8")
setup=(mobile/"OnTrackSetupChecklist.kt").read_text(encoding="utf-8")
ui=(root/"saas/mobile/ota/ui.html").read_text(encoding="utf-8")
import json
release=json.loads((root/"saas/mobile/ota/native_release.json").read_text(encoding="utf-8"))
assert release["version_code"] == 72 and release["version_name"] == "0.3.49", "expected signed v72 restored v65 PCM release"
assert f'versionCode = {release["version_code"]}' in prepare
assert f'versionName = "{release["version_name"]}"' in prepare
assert "getBoolean(KEY, true)" in prepare and "'false', 'disable all saved call-recording toggle states'" in prepare
assert "AudioAccess.PERMISSIONS" in prepare and "val ASK_PERMISSIONS = RUNTIME_PERMISSIONS" in prepare
assert "Keeping complete test recording for local review" not in prepare
for unnecessary in ("READ_MEDIA_AUDIO","READ_EXTERNAL_STORAGE","WRITE_EXTERNAL_STORAGE",
                    "READ_CALL_LOG","READ_CONTACTS","USE_FULL_SCREEN_INTENT"):
    assert "'"+unnecessary+"'" in prepare,unnecessary
assert "tools}node': 'remove'" in prepare
assert "Manifest.permission.READ_PHONE_STATE" in wizard
assert "Settings.ACTION_MANAGE_OVERLAY_PERMISSION" in wizard
assert "Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES" in wizard
assert "Settings.ACTION_APP_NOTIFICATION_SETTINGS" in wizard
assert "Settings.ACTION_APPLICATION_DETAILS_SETTINGS" in wizard
assert "requestPermissions(arrayOf(Manifest.permission.RECORD_AUDIO)" not in wizard
assert "onResume()" in wizard and "refreshChecks?.invoke()" in wizard
assert "OnTrackPermissionChecklist.evaluate(permissions)" in wizard
assert "OnTrackSetupChecklist.evaluate(facts)" in wizard
assert "f.pcmDaemon" in setup and "CheckState.OPTIONAL" in setup
assert "CheckAction.PHONE_PERMISSION" in logic
assert "CheckAction.OUTBOUND_CALLS" in logic
assert "CheckAction.OVERLAY" in logic
assert "CheckAction.INSTALL_UPDATES" in logic
assert "غير مطلوب" in logic and "تسجيل" in logic
assert "فحص وتجهيز الهاتف" in ui
print("V58_ANDROID_MINIMAL_PERMISSIONS_UI_DEEP_LINKS_AND_NO_RECORDING_SOURCE_CONTRACT_PASS")
