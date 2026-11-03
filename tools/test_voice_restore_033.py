#!/usr/bin/env python3
"""Regression gate for the owner's last verified 0.3.9 installed device.

A new APK may restore feature-complete tenant UI, but it must retain local
pairing state, original audio transport and call-report tenant isolation.
"""
import json
import re
from pathlib import Path

ROOT=Path(__file__).resolve().parent.parent
def src(path):
    return (ROOT/path).read_text(encoding="utf-8")
def require(condition,reason):
    if not condition:raise AssertionError(reason)

meta=json.loads(src("saas/mobile/ota/native_release.json"))
ui_meta=json.loads(src("saas/mobile/ota/release.json"))
prepare=src("saas/mobile/audio-proof/prepare.py")
android=src("saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLinkActivity.kt")
service=src("saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLinkService.kt")
pair=src("saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/TenantDeviceEnrollment.kt")
events=src("saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/CallEventOutbox.kt")
reports=src("saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/CallReportOutbox.kt")
ui=src("saas/mobile/ota/ui.html")
require(meta["version_code"]==33 and meta["version_name"]=="0.3.10",
        "New native version must be strictly greater than already-installed code32")
require(ui_meta["revision"]==30,"UI release must be strictly greater than deployed OTA29")
require(ui_meta["min_native"]==33 and ui_meta["max_native"]==33,
        "Advanced UI must never load on legacy Android32")
require("'versionCode = 33'" in prepare and 'versionName = "0.3.10"' in prepare,
        "Actual Gradle overlay must match native release metadata")
require('.remove("key")' not in android and '.remove("base")' not in android,
        "Signed OTA must not silently wipe the existing phone link")
require('TenantDeviceEnrollment(this)' in android and 'TenantEngineRestorePolicy.shouldRestore' in android,
        "Per-SIM pairing and enabled-state restoration must survive updates")
require('session.optString("agent_name"' in src("saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLiveCall.kt"),
        "Published agent identity must be used for voice greeting")
require('.put("input_channel","mix")' in service,"Previously proven duplex audio source changed")
require('fun bindingId(slot:Int)' in pair and 'peekEligible' in service,
        "SIM-scoped encrypted queue must remain tied to the original pairing")
require('originalDevice' in events and '.put("device_id",originalDevice)' in events,
        "CallLog history must retain its original account binding")
require('device_id' in reports and 'peekEligible' in reports,
        "Encrypted AI call report must never be sent after re-pair to another tenant")
require('reportOutbox.enqueue(reportSlot,reportBinding,reportCallId,realTranscript)' in service,
        "Real Gemini transcript must be linked to the exact original SIM")
require("workspace_call_analysis" in service and 'CallReportOutbox' in service,
        "Automatic call summaries were accidentally removed")
require('id="tenantMenu"' in ui and 'id="tenantTest"' in ui and
        'id="tenantWorkspaceSlot"' in ui and 'id="openAnswerSettings"' in ui,
        "Advanced multi-SIM setup and controls missing from the new release")
require('id="key"' not in ui,"Old Pilot key input was reintroduced in the customer UI")
require(src("saas/mobile/audio-proof/overlay/app/src/main/assets/ontrack-ui.html")==ui,
        "Embedded APK UI and OTA UI must be identical")
device=src("saas/console/_core/DevicePairing.php")
actions=src("saas/console/_core/Actions.php")
views=src("saas/console/_core/Views.php")
require('function device_binding_change_agent' in device and
        "case 'change_device_agent':" in actions and
        "تغيير الإيجنت بدون إعادة ربط" in views,
        "Admin action to change assigned SIM agent without re-pair is missing")
require('device_binding_heartbeat' in device and
        'saved.getInt("agent_id")' in pair,
        "Device must receive the new published agent at next heartbeat")
for label in ('ملخص المكالمة','ملاحظات العميل','التحويل لموظف'):
    require(label in views,"Report field missing: "+label)
require('customer_notes' in src("saas/console/_core/TenantWorkspace.php") and
        'handoff_reason' in src("saas/console/_core/TenantWorkspace.php"),
        "Customer notes and handoff were dropped")
print("PASS: Android 0.3.10 / OTA30 keeps paired SIM, live voice and existing product features")
