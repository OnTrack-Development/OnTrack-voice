#!/usr/bin/env python3
"""Publish already-signed OnTrack native APK to real OTA host; require host receipts.

Uses original APK signing identity and the existing single-slot host worker.
GitHub token/private key never crosses to the host. UI manifest is advanced
first only when its max_native excludes the new native version.
"""
import base64
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import urllib.request
import zipfile

sys.path.insert(0, str(Path(__file__).resolve().parent))
import publish_v1_one_shot as pub
import queue_mobile_native as native
import queue_mobile_ota as ui_policy

EXPECTED_CERT = "d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f"
# Existing private editable prerelease already has upload permission. Do not
# overwrite the historical v48 asset; append distinct versioned assets only.
pub.RELEASE_TAG = os.environ.get("ONTRACK_PRIVATE_APK_RELEASE_TAG", "ontrack-native-v48")


def sha(data):
    return hashlib.sha256(data).hexdigest()


def public_bytes(path, max_size):
    req = urllib.request.Request(
        "https://app.ontrackegy.com/" + path,
        headers={"Cache-Control": "no-cache", "Pragma": "no-cache",
                 "User-Agent": "OnTrack-verified-native-OTA"})
    with urllib.request.urlopen(req, timeout=20) as res:
        if res.status != 200:
            raise RuntimeError("Public OTA endpoint not available: " + path)
        data = res.read(max_size + 1)
    if len(data) > max_size:
        raise RuntimeError("Public OTA data larger than expected")
    return data


def verify_apk(apk):
    release = json.loads(Path("saas/mobile/ota/native_release.json").read_text())
    version = release["version_name"]
    code = release["version_code"]
    if apk.name != f"OnTrack-Voice-{version}.apk":
        raise RuntimeError("APK filename does not match declared native release")
    data = apk.read_bytes()
    expected = os.environ["ONTRACK_EXPECTED_APK_SHA256"].lower()
    if sha(data) != expected or not (100000 < len(data) <= 15728640):
        raise RuntimeError("Preserved APK SHA256 or size mismatch")
    sdk = Path(os.environ["LOCALAPPDATA"]) / "Android" / "Sdk"
    signer = sdk / "build-tools" / "36.0.0" / "apksigner.bat"
    analyzer = sdk / "cmdline-tools" / "latest" / "bin" / "apkanalyzer.bat"
    cert = subprocess.check_output(
        [str(signer), "verify", "--print-certs", str(apk)], text=True).lower()
    if EXPECTED_CERT not in cert:
        raise RuntimeError("APK signer does not match original installation")
    subprocess.run([str(signer), "verify", "--verbose", str(apk)], check=True)
    package = subprocess.check_output(
        [str(analyzer), "manifest", "application-id", str(apk)], text=True).strip()
    version_code = subprocess.check_output(
        [str(analyzer), "manifest", "version-code", str(apk)], text=True).strip()
    if package != release["package"] or version_code != str(code):
        raise RuntimeError("APK identity, package or version code mismatch")
    if release["channel"] != "stable" or release["schema"] != 1:
        raise RuntimeError("Unexpected native update policy")
    return release, data, expected


def signed(folder, key):
    subprocess.run(["java", "tools/SignMobileOta.java", str(key), str(folder)], check=True)


def prepare_native(apk, key, out, expected):
    folder = out / "native"
    subprocess.run(["python", "tools/prepare_mobile_native.py", str(apk), str(folder)], check=True)
    signed(folder, key)
    envelope, cert, meta = native.verify(folder)
    if meta["sha256"] != expected or meta["size"] != apk.stat().st_size:
        raise RuntimeError("Signed native manifest does not match the verified APK")
    return envelope, cert, meta


def prepare_ui(out, key, code):
    current_bytes = public_bytes("mobile/updates/latest.json", 32768)
    envelope = json.loads(current_bytes)
    meta = json.loads(envelope["payload"])
    if meta.get("runtime") != 1 or meta.get("channel") != "stable":
        raise RuntimeError("Live UI compatibility contract mismatch")
    if not (48 <= meta["revision"] < 10000) or meta["min_native"] > code:
        raise RuntimeError("Live UI version cannot be safely advanced")
    if meta["max_native"] >= code:
        return None, meta

    source = Path("saas/mobile/ota/ui.html").read_bytes()
    if sha(source) != meta["sha256"]:
        # Preserve the exact currently deployed HTML; never silently replace
        # the app's UI from an unrelated branch revision.
        source = public_bytes("mobile/updates/" + meta["file"], 524288)
    if len(source) != meta["size"] or sha(source) != meta["sha256"]:
        raise RuntimeError("Current UI body failed signed hash/size contract")
    updated = dict(meta)
    updated["revision"] += 1
    updated["max_native"] = code
    updated["source_commit"] = os.environ["GITHUB_SHA"]
    if updated["revision"] >= 10000:
        raise RuntimeError("OTA revision exhausted")

    folder = out / "ota"
    folder.mkdir(parents=True, exist_ok=True)
    (folder / meta["file"]).write_bytes(source)
    (folder / "payload.json").write_text(
        json.dumps(updated, sort_keys=True, separators=(",", ":")), encoding="utf-8")
    signed(folder, key)
    signed_envelope, _, signed_meta = ui_policy.verify(folder)
    if signed_meta["revision"] != updated["revision"] or signed_meta["max_native"] != code:
        raise RuntimeError("Signed UI compatibility metadata mismatch")
    package = out / f"OnTrack-V{code}-UI-Compat-{signed_meta['revision']}.zip"
    with zipfile.ZipFile(package, "w", zipfile.ZIP_DEFLATED, compresslevel=7) as archive:
        for f in sorted(folder.iterdir()):
            if f.is_file():
                archive.write(f, "ota/" + f.name)
    return (folder, package, signed_envelope, signed_meta), meta


def main():
    if os.environ.get("GITHUB_REF") != "refs/heads/release/voice-v1-20261106":
        raise RuntimeError("Only the pinned internal release branch may publish OTA")
    apk = Path(os.environ["ONTRACK_SIGNED_APK"])
    policy, data, expected_sha = verify_apk(apk)
    native_code = policy["version_code"]
    out = Path(".build") / "signed-ota-publish" / os.environ["GITHUB_RUN_ID"]
    out.mkdir(parents=True, exist_ok=True)
    key = Path.home() / ".android" / "debug.keystore"
    if not key.is_file():
        raise RuntimeError("Original APK and OTA signing key missing")
    native_manifest, cert, native_meta = prepare_native(apk, key, out, expected_sha)
    if native_meta["version_code"] != native_code:
        raise RuntimeError("Signed native version does not match policy")
    current_native = pub.live("mobile/updates/native/latest.json")
    if current_native is None:
        raise RuntimeError("Could not read public native OTA manifest before publication")
    native_before = json.loads(current_native["payload"])
    if (native_before["version_code"] > native_code or
       (native_before["version_code"] == native_code
        and native_before["sha256"] != expected_sha)):
        raise RuntimeError("Native OTA downgrade or immutable version reuse blocked")

    # Verify/prepare compatible UI BEFORE publishing any native pointer.
    future_ui, previous_ui = prepare_ui(out, key, native_code)
    if future_ui:
        folder, zip_path, ui_envelope, ui_meta = future_ui
        ui_url, content = pub.asset(
            zip_path.name, sha(zip_path.read_bytes()), zip_path.stat().st_size, zip_path)
        if content != zip_path.read_bytes():
            raise RuntimeError("Private signed UI zip roundtrip mismatch")
        ui_program = pub.program(
            ["saas/mobile/ota/deploy.py", "saas/mobile/ota/remote_deploy.py"],
            "remote_install(*sys.argv[1:])")
        pub.host_job("ui", "/home/appontrack/public_html/saas", ui_program,
                     [ui_url, ui_meta["sha256"], str(ui_meta["revision"])],
                     lambda result: result.get("status") == "installed"
                     and result.get("sha256") == ui_meta["sha256"]
                     and result.get("revision") == ui_meta["revision"])
        if pub.live("mobile/updates/latest.json") != json.loads(ui_envelope):
            raise RuntimeError("Updated signed UI not confirmed on live host")
        print("UI_NATIVE_VERSION_50_COMPATIBILITY_PUBLISHED", ui_meta["revision"], flush=True)
    else:
        live_ui = pub.live("mobile/updates/latest.json")
        if live_ui is None or json.loads(live_ui["payload"])["max_native"] < native_code:
            raise RuntimeError("Existing UI does not authorize new native version")
        print("SIGNED_UI_COMPATIBILITY_ALREADY_LIVE", flush=True)

    # Source is a private GitHub Release asset, not an Actions artifact (full quota).
    # Resolve its authenticated, short-lived CDN URL, then host verifies hash.
    asset_name = os.environ["ONTRACK_PRIVATE_APK_ASSET"]
    apk_url, downloaded = pub.asset(asset_name, expected_sha, len(data))
    if sha(downloaded) != expected_sha or downloaded != data:
        raise RuntimeError("Private release APK differs from preserved signed artifact")

    if current_native == json.loads(native_manifest):
        print("SIGNED_NATIVE_ALREADY_PUBLIC", flush=True)
    else:
        program = pub.program(
            ["saas/mobile/ota/native_deploy.py"],
            "deploy(*sys.argv[1:]);ROOT.chmod(0o755)")
        pub.host_job("apk", "/home/appontrack/public_html", program,
                     [apk_url, base64.b64encode(native_manifest).decode(),
                      base64.b64encode(cert).decode(), expected_sha],
                     lambda result: result.get("status") == "published"
                     and result.get("sha256") == expected_sha
                     and result.get("version") == native_code
                     and result.get("bytes") == len(data))
    if pub.live("mobile/updates/native/latest.json") != json.loads(native_manifest):
        raise RuntimeError("Host native receipt passed but live native OTA pointer mismatch")
    final_ui = pub.live("mobile/updates/latest.json")
    if final_ui is None or json.loads(final_ui["payload"])["max_native"] < native_code:
        raise RuntimeError("Live UI compatibility pointer missing after native publication")
    print("SIGNED_APK_OTA_PUBLISHED_AND_HOST_VERIFIED", json.dumps({
        "version": policy["version_name"], "version_code": native_code,
        "sha256": expected_sha,
        "url": "https://app.ontrackegy.com/mobile/updates/native/" + native_meta["file"],
        "ui_revision": json.loads(final_ui["payload"])["revision"],
        "host_ack": True, "installed_on_phone": False,
    }), flush=True)


if __name__ == "__main__":
    main()
