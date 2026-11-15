import os
import sys
import subprocess
import urllib.request
import json
from pathlib import Path

def main():
    root = Path(r"C:\OnTrack-voice")
    key = Path(os.environ["USERPROFILE"]) / ".ssh" / "ontrack_publish_ed25519"
    port = "7195"
    host = "65.21.208.232"
    user = "appontrack"
    target = f"{user}@{host}"

    apk_name = "ontrack-voice-v78-1917731db17aadb17cf0bf45433a112e44f536ab76a1430a8062842110baa642.apk"
    apk_path = root / ".build" / "artifact" / apk_name
    if not apk_path.is_file():
        raise FileNotFoundError(f"APK not found: {apk_path}")

    native_json = root / ".build" / "artifact" / "native" / "latest.json"
    native_cert = root / ".build" / "artifact" / "native" / "signing-cert.der"
    ota_json = root / ".build" / "artifact" / "ota" / "latest.json"
    ota_cert = root / ".build" / "artifact" / "ota" / "signing-cert.der"
    ontrack_php = root / "saas" / "config" / "ontrack.php"

    scp_base = ["scp", "-P", port, "-i", str(key), "-o", "StrictHostKeyChecking=no"]
    ssh_base = ["ssh", "-p", port, "-i", str(key), "-o", "StrictHostKeyChecking=no", target]

    print(f"Uploading APK ({apk_path.stat().st_size} bytes)...")
    subprocess.run(scp_base + [str(apk_path), f"{target}:/home/appontrack/public_html/mobile/updates/native/"], check=True)
    print("APK uploaded.")

    print("Uploading native manifests...")
    subprocess.run(scp_base + [str(native_json), f"{target}:/home/appontrack/public_html/mobile/updates/native/latest.json"], check=True)
    subprocess.run(scp_base + [str(native_cert), f"{target}:/home/appontrack/public_html/mobile/updates/native/signing-cert.der"], check=True)

    print("Uploading OTA manifests...")
    subprocess.run(scp_base + [str(ota_json), f"{target}:/home/appontrack/public_html/mobile/updates/latest.json"], check=True)
    subprocess.run(scp_base + [str(ota_cert), f"{target}:/home/appontrack/public_html/mobile/updates/signing-cert.der"], check=True)

    print("Uploading ontrack.php config...")
    subprocess.run(scp_base + [str(ontrack_php), f"{target}:/home/appontrack/iovoice-app/config/ontrack.php"], check=True)

    remote_cmds = (
        f"cd /home/appontrack/public_html/mobile/updates/native && ln -sf {apk_name} latest.apk && "
        f"cd /home/appontrack/public_html/mobile/updates && ln -sf native/{apk_name} latest.apk && "
        "cd /home/appontrack/iovoice-app && "
        "/usr/local/bin/ea-php82 artisan view:clear && "
        "/usr/local/bin/ea-php82 artisan config:clear && "
        "/usr/local/bin/ea-php82 artisan route:clear"
    )
    print("Executing remote symlinks and artisan clear...")
    subprocess.run(ssh_base + [remote_cmds], check=True)

    print("\n--- Verifying Live Remote Endpoints ---")
    
    # 1. Native latest.json
    req = urllib.request.Request("https://app.ontrackegy.com/mobile/updates/native/latest.json")
    with urllib.request.urlopen(req, timeout=10) as res:
        data = json.loads(res.read().decode("utf-8"))
        payload = json.loads(data["payload"])
        print(f"Live Native Manifest: version_code={payload['version_code']}, name={payload['version_name']}, sha256={payload['sha256']}")
        assert payload["version_code"] == 78, "Native version_code mismatch!"
        assert payload["file"] == apk_name, "Native filename mismatch!"

    # 2. OTA latest.json
    req = urllib.request.Request("https://app.ontrackegy.com/mobile/updates/latest.json")
    with urllib.request.urlopen(req, timeout=10) as res:
        data = json.loads(res.read().decode("utf-8"))
        payload = json.loads(data["payload"])
        print(f"Live OTA Manifest: revision={payload['revision']}, max_native={payload['max_native']}")
        assert payload["revision"] == 78, "OTA revision mismatch!"

    # 3. APK HTTP HEAD/GET length
    req = urllib.request.Request("https://app.ontrackegy.com/mobile/updates/native/latest.apk", method="HEAD")
    with urllib.request.urlopen(req, timeout=10) as res:
        cl = int(res.headers.get("Content-Length", 0))
        print(f"Live latest.apk Content-Length: {cl} bytes (Expected: {apk_path.stat().st_size})")
        assert cl == apk_path.stat().st_size, "Content-Length mismatch!"

    print("\n=== V78 DEPLOYMENT AND VERIFICATION SUCCESSFUL ===")

if __name__ == "__main__":
    main()
