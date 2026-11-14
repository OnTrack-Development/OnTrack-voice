import os
import sys
import json
import hashlib
import shutil
import subprocess
from pathlib import Path

def main():
    root = Path(r"C:\OnTrack-voice")
    sdk = Path(os.environ.get("LOCALAPPDATA", "")) / "Android" / "Sdk"
    signer = sdk / "build-tools" / "36.0.0" / "apksigner.bat"
    analyzer = sdk / "cmdline-tools" / "latest" / "bin" / "apkanalyzer.bat"
    keystore = Path(os.environ["USERPROFILE"]) / ".android" / "debug.keystore"

    unsigned_apk = root / ".build" / "jemrec" / "app" / "build" / "outputs" / "apk" / "release" / "app-release-unsigned.apk"
    shell_jar = root / ".build" / "jemrec" / "shellserver" / "ontrack-voiceproof-capture.jar"

    if not unsigned_apk.is_file():
        raise FileNotFoundError(f"Unsigned APK not found: {unsigned_apk}")

    # Verify shell jar packaging
    res = subprocess.run([sys.executable, str(root / "tools" / "verify_shell_jar_packaging.py"), str(unsigned_apk), str(shell_jar)], capture_output=True, text=True)
    print("verify_shell_jar:", res.stdout, res.stderr)
    if res.returncode != 0:
        raise RuntimeError("Shell jar verification failed on unsigned APK")

    artifact_dir = root / ".build" / "artifact"
    artifact_dir.mkdir(parents=True, exist_ok=True)
    temp_signed_apk = artifact_dir / "temp-signed.apk"

    # Sign APK
    sign_cmd = [
        str(signer), "sign",
        "--ks", str(keystore),
        "--ks-key-alias", "androiddebugkey",
        "--ks-pass", "pass:android",
        "--key-pass", "pass:android",
        "--out", str(temp_signed_apk),
        str(unsigned_apk)
    ]
    subprocess.run(sign_cmd, check=True)

    # Verify signature
    verify_cmd = [str(signer), "verify", "--print-certs", str(temp_signed_apk)]
    verify_out = subprocess.check_output(verify_cmd, text=True)
    cert_hash = "d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f"
    if cert_hash not in verify_out.lower():
        raise RuntimeError(f"Certificate fingerprint mismatch: {verify_out}")

    # Verify verbose
    subprocess.run([str(signer), "verify", "--verbose", str(temp_signed_apk)], check=True)

    # Verify package and version
    app_id = subprocess.check_output([str(analyzer), "manifest", "application-id", str(temp_signed_apk)], text=True).strip()
    ver_code = subprocess.check_output([str(analyzer), "manifest", "version-code", str(temp_signed_apk)], text=True).strip()
    ver_name = subprocess.check_output([str(analyzer), "manifest", "version-name", str(temp_signed_apk)], text=True).strip()

    print(f"Verified Signed APK: package={app_id}, versionCode={ver_code}, versionName={ver_name}")
    if app_id != "com.ontrack.voiceproof" or ver_code != "76" or ver_name != "0.3.53":
        raise ValueError(f"Unexpected metadata: {app_id} / {ver_code} / {ver_name}")

    # Compute SHA-256
    apk_bytes = temp_signed_apk.read_bytes()
    apk_sha256 = hashlib.sha256(apk_bytes).hexdigest().lower()
    apk_size = len(apk_bytes)

    final_apk_name = f"ontrack-voice-v76-{apk_sha256}.apk"
    final_apk_path = artifact_dir / final_apk_name
    shutil.copy2(temp_signed_apk, final_apk_path)
    shutil.copy2(temp_signed_apk, artifact_dir / "latest.apk")
    shutil.copy2(temp_signed_apk, artifact_dir / "OnTrack-Voice-0.3.53.apk")

    # Also copy to D:\ai\OnTrack-Voice-Builds\0.3.53
    out_dir = Path(r"D:\ai\OnTrack-Voice-Builds\0.3.53")
    out_dir.mkdir(parents=True, exist_ok=True)
    shutil.copy2(temp_signed_apk, out_dir / "OnTrack-Voice-0.3.53.apk")
    shutil.copy2(temp_signed_apk, out_dir / final_apk_name)
    print(f"Copied signed APK to {out_dir / 'OnTrack-Voice-0.3.53.apk'}")

    temp_signed_apk.unlink()

    # Generate native OTA manifest
    native_dir = artifact_dir / "native"
    native_dir.mkdir(parents=True, exist_ok=True)
    native_payload = {
        "channel": "stable",
        "file": final_apk_name,
        "min_installed_version": 12,
        "package": "com.ontrack.voiceproof",
        "schema": 1,
        "sha256": apk_sha256,
        "size": apk_size,
        "version_code": 76,
        "version_name": "0.3.53"
    }
    (native_dir / "payload.json").write_text(json.dumps(native_payload, separators=(',', ':'), sort_keys=True), encoding="utf-8")
    subprocess.run(["java", str(root / "tools" / "SignMobileOta.java"), str(keystore), str(native_dir)], check=True)

    # Keep saas/mobile/ota manifests unwrapped with revision/version_code 76
    (root / "saas" / "mobile" / "ota" / "native_release.json").write_text(
        json.dumps({"schema": 1, "channel": "stable", "package": "com.ontrack.voiceproof", "version_code": 76, "version_name": "0.3.53", "min_installed_version": 12}, separators=(',', ':')) + "\n",
        encoding="utf-8"
    )

    # Generate UI OTA manifest
    ota_dir = artifact_dir / "ota"
    ota_dir.mkdir(parents=True, exist_ok=True)
    shutil.copy2(root / "saas" / "mobile" / "ota" / "ui.html", ota_dir / "ui.html")
    ota_payload = {
        "channel": "stable",
        "max_native": 76,
        "min_native": 34,
        "revision": 76,
        "runtime": 1,
        "schema": 1
    }
    (ota_dir / "payload.json").write_text(json.dumps(ota_payload, separators=(',', ':'), sort_keys=True), encoding="utf-8")
    subprocess.run(["java", str(root / "tools" / "SignMobileOta.java"), str(keystore), str(ota_dir)], check=True)

    # Keep saas/mobile/ota/release.json unwrapped with revision 76
    (root / "saas" / "mobile" / "ota" / "release.json").write_text(
        json.dumps({"schema": 1, "channel": "stable", "runtime": 1, "revision": 76, "min_native": 34, "max_native": 76}, separators=(',', ':')) + "\n",
        encoding="utf-8"
    )

    # Write SHA256SUMS.txt
    sums_file = artifact_dir / "SHA256SUMS.txt"
    sums_content = f"{apk_sha256}  {final_apk_name}\n"
    sums_file.write_text(sums_content, encoding="utf-8")

    print("\n=== RELEASE V76 (0.3.53) PACKAGE COMPLETE ===")
    print(f"APK: {final_apk_name}")
    print(f"Size: {apk_size} bytes")
    print(f"SHA-256: {apk_sha256}")
    print(f"Native manifest: {native_dir / 'latest.json'}")
    print(f"OTA manifest: {ota_dir / 'latest.json'}")

if __name__ == "__main__":
    main()