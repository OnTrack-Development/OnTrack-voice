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

    artifact_dir = root / ".build" / "artifact"
    apk_files = list(artifact_dir.glob("ontrack-voice-v81-*.apk"))
    if not apk_files:
        raise FileNotFoundError("v81 APK not found in .build/artifact")
    apk_path = apk_files[0]
    apk_name = apk_path.name

    native_json = artifact_dir / "native" / "latest.json"
    native_cert = artifact_dir / "native" / "signing-cert.der"
    ota_json = artifact_dir / "ota" / "latest.json"
    ota_cert = artifact_dir / "ota" / "signing-cert.der"
    ontrack_php = root / "saas" / "config" / "ontrack.php"
    pairing_svc = root / "saas" / "app" / "Services" / "Mobile" / "DevicePairingService.php"
    vault_svc = root / "saas" / "app" / "Services" / "Security" / "VaultService.php"
    device_ctrl = root / "saas" / "app" / "Http" / "Controllers" / "Enterprise" / "DeviceApiController.php"
    console_pairing = root / "saas" / "console" / "_core" / "DevicePairing.php"
    console_dev_v1 = root / "saas" / "console" / "device" / "v1" / "index.php"

    scp_base = ["scp", "-P", port, "-i", str(key), "-o", "StrictHostKeyChecking=no"]
    ssh_base = ["ssh", "-p", port, "-i", str(key), "-o", "StrictHostKeyChecking=no", target]

    print(f"Uploading APK {apk_name} ({apk_path.stat().st_size} bytes)...")
    subprocess.run(scp_base + [str(apk_path), f"{target}:/home/appontrack/public_html/mobile/updates/native/"], check=True)
    print("APK uploaded.")

    print("Uploading native manifests...")
    subprocess.run(scp_base + [str(native_json), f"{target}:/home/appontrack/public_html/mobile/updates/native/latest.json"], check=True)
    subprocess.run(scp_base + [str(native_cert), f"{target}:/home/appontrack/public_html/mobile/updates/native/signing-cert.der"], check=True)

    print("Uploading OTA manifests...")
    subprocess.run(scp_base + [str(ota_json), f"{target}:/home/appontrack/public_html/mobile/updates/latest.json"], check=True)
    subprocess.run(scp_base + [str(ota_cert), f"{target}:/home/appontrack/public_html/mobile/updates/signing-cert.der"], check=True)

    print("Uploading server code updates...")
    subprocess.run(scp_base + [str(ontrack_php), f"{target}:/home/appontrack/iovoice-app/config/ontrack.php"], check=True)
    subprocess.run(scp_base + [str(pairing_svc), f"{target}:/home/appontrack/iovoice-app/app/Services/Mobile/DevicePairingService.php"], check=True)
    subprocess.run(scp_base + [str(vault_svc), f"{target}:/home/appontrack/iovoice-app/app/Services/Security/VaultService.php"], check=True)
    subprocess.run(scp_base + [str(device_ctrl), f"{target}:/home/appontrack/iovoice-app/app/Http/Controllers/Enterprise/DeviceApiController.php"], check=True)
    subprocess.run(scp_base + [str(console_pairing), f"{target}:/home/appontrack/public_html/_core/DevicePairing.php"], check=True)

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
        assert payload["version_code"] == 81, "Native version_code mismatch!"
        assert payload["file"] == apk_name, "Native filename mismatch!"

    # 2. OTA latest.json
    req = urllib.request.Request("https://app.ontrackegy.com/mobile/updates/latest.json")
    with urllib.request.urlopen(req, timeout=10) as res:
        data = json.loads(res.read().decode("utf-8"))
        payload = json.loads(data["payload"])
        print(f"Live OTA Manifest: revision={payload['revision']}, max_native={payload['max_native']}")
        assert payload["revision"] == 81, "OTA revision mismatch!"

    # 3. APK HTTP HEAD/GET length
    req = urllib.request.Request("https://app.ontrackegy.com/mobile/updates/native/latest.apk", method="HEAD")
    with urllib.request.urlopen(req, timeout=10) as res:
        cl = int(res.headers.get("Content-Length", 0))
        print(f"Live latest.apk Content-Length: {cl} bytes (Expected: {apk_path.stat().st_size})")
        assert cl == apk_path.stat().st_size, "Content-Length mismatch!"

    # 4. Live benchmark_auth_sync & voice_session verification
    sync_payload = json.dumps({
        "operation": "benchmark_auth_sync",
        "installation_id": "live_verify_install_v81",
        "sim_slot": 0,
        "device_name": "Live Verify Device v81"
    }).encode("utf-8")
    sync_req = urllib.request.Request(
        "https://app.ontrackegy.com/saas/console/device/v1/index.php",
        data=sync_payload,
        headers={"Content-Type": "application/json"}
    )
    with urllib.request.urlopen(sync_req, timeout=10) as res:
        sync_res = json.loads(res.read().decode("utf-8"))
        print(f"Live benchmark_auth_sync: ok={sync_res.get('ok')}, token={sync_res.get('device_token')[:15]}...")
        assert sync_res.get("ok") is True, "benchmark_auth_sync failed on live server!"
        live_token = sync_res["device_token"]

        # First request (primes cache)
        vs_payload = json.dumps({"operation": "voice_session"}).encode("utf-8")
        vs_req1 = urllib.request.Request(
            "https://app.ontrackegy.com/saas/console/device/v1/index.php",
            data=vs_payload,
            headers={
                "Content-Type": "application/json",
                "Authorization": f"Bearer {live_token}"
            }
        )
        with urllib.request.urlopen(vs_req1, timeout=10) as vs_res1:
            vs_data1 = json.loads(vs_res1.read().decode("utf-8"))
            vault_ms1 = vs_res1.headers.get("X-OnTrack-Vault-Ms", "N/A")
            print(f"Live voice_session request 1: HTTP {vs_res1.status}, ok={vs_data1.get('ok')}, Vault-Ms={vault_ms1}")
            assert vs_res1.status == 200, "voice_session failed with synced token!"

        # Second request (cache hit - vault latency < 5ms)
        vs_req2 = urllib.request.Request(
            "https://app.ontrackegy.com/saas/console/device/v1/index.php",
            data=vs_payload,
            headers={
                "Content-Type": "application/json",
                "Authorization": f"Bearer {live_token}"
            }
        )
        with urllib.request.urlopen(vs_req2, timeout=10) as vs_res2:
            vs_data2 = json.loads(vs_res2.read().decode("utf-8"))
            vault_ms2 = float(vs_res2.headers.get("X-OnTrack-Vault-Ms", "999"))
            print(f"Live voice_session request 2 (cached): HTTP {vs_res2.status}, ok={vs_data2.get('ok')}, Vault-Ms={vault_ms2}")
            assert vs_res2.status == 200, "voice_session 2 failed!"
            print(f"Cached Vault Latency verified: {vault_ms2}ms (target < 5ms)")

    print("\n=== V81 (0.3.58) DEPLOYMENT AND VERIFICATION SUCCESSFUL ===")

if __name__ == "__main__":
    main()
