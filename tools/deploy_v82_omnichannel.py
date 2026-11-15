import os
import sys
import tarfile
import subprocess
from pathlib import Path

def main():
    saas_dir = Path(r"C:\OnTrack-voice\saas")
    ssh_key = Path(os.environ["USERPROFILE"]) / ".ssh" / "ontrack_publish_ed25519"
    server_host = "appontrack@65.21.208.232"
    server_port = "7195"

    archive_path = Path(r"C:\OnTrack-voice\v82_omnichannel_update.tar.gz")
    if archive_path.exists():
        archive_path.unlink()

    files_and_dirs = [
        "database/migrations/2026_11_15_000001_create_support_multi_agent_tables.php",
        "app/Models/Support",
        "app/Services/Support",
        "app/Services/Knowledge",
        "app/Services/Channels",
        "app/Services/Billing/FeatureGateService.php",
        "app/Http/Controllers/Api/BridgeController.php",
        "app/Http/Controllers/App/AgentStudioController.php",
        "app/Http/Controllers/Enterprise/WorkspaceController.php",
        "routes/web.php",
        "resources/views/app/agents/web_call.blade.php",
        "resources/views/enterprise/layout.blade.php",
        "resources/views/enterprise/tabs",
        "storage/connectors/whmcs/ontrack_bridge",
        "public/downloads/ontrack_bridge.zip",
        "public/widget/ontrack-widget.js",
    ]

    print(f"Creating archive {archive_path}...")
    with tarfile.open(archive_path, "w:gz") as tar:
        for rel in files_and_dirs:
            full = saas_dir / rel
            if full.exists():
                tar.add(full, arcname=rel)
                print(f"  + Added: {rel}")
            else:
                print(f"  ! Missing: {rel}")

    print(f"Archive size: {archive_path.stat().st_size} bytes")

    # SCP archive to server
    print("Uploading archive to server...")
    scp_cmd = [
        "scp", "-P", server_port, "-i", str(ssh_key),
        "-o", "StrictHostKeyChecking=no",
        str(archive_path),
        f"{server_host}:/home/appontrack/v82_omnichannel_update.tar.gz"
    ]
    res = subprocess.run(scp_cmd, capture_output=True, text=True)
    if res.returncode != 0:
        print("SCP failed:\n", res.stderr)
        sys.exit(1)
    print("Upload complete.")

    # Run deployment commands on server
    remote_script = """
set -e
echo "=== Step 1: Extracting update to iovoice-app ==="
cd /home/appontrack/iovoice-app
tar -xzf /home/appontrack/v82_omnichannel_update.tar.gz

echo "=== Step 2: Syncing public assets to public_html ==="
mkdir -p /home/appontrack/public_html/downloads
mkdir -p /home/appontrack/public_html/widget
cp -f /home/appontrack/iovoice-app/public/downloads/ontrack_bridge.zip /home/appontrack/public_html/downloads/ontrack_bridge.zip
cp -f /home/appontrack/iovoice-app/public/widget/ontrack-widget.js /home/appontrack/public_html/widget/ontrack-widget.js

echo "=== Step 3: Running migrations with EA PHP 8.2 ==="
/opt/cpanel/ea-php82/root/usr/bin/php artisan migrate --force

echo "=== Step 4: Clearing and caching configs/views ==="
/opt/cpanel/ea-php82/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php82/root/usr/bin/php artisan view:cache

echo "=== Step 5: Verifying permissions & files ==="
ls -lh /home/appontrack/public_html/downloads/ontrack_bridge.zip
ls -lh /home/appontrack/public_html/widget/ontrack-widget.js

echo "=== Deployment Succeeded ==="
"""

    print("Executing remote deployment commands...")
    ssh_cmd = [
        "ssh", "-p", server_port, "-i", str(ssh_key),
        "-o", "StrictHostKeyChecking=no",
        server_host,
        remote_script
    ]
    res_ssh = subprocess.run(ssh_cmd, capture_output=True, text=True)
    print("STDOUT:\n", res_ssh.stdout)
    if res_ssh.stderr:
        print("STDERR:\n", res_ssh.stderr)

    if res_ssh.returncode != 0:
        print("Deployment failed with code", res_ssh.returncode)
        sys.exit(1)

    print("Verifying live HTTP endpoints...")
    verify_script = """
python3 -c "
import urllib.request, ssl
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

urls = [
    ('Public Web Call', 'https://app.ontrackegy.com/aivoiceagent/1?share=live'),
    ('WHMCS Module Download', 'https://app.ontrackegy.com/downloads/ontrack_bridge.zip'),
    ('Zoie.ai Widget JS', 'https://app.ontrackegy.com/widget/ontrack-widget.js'),
    ('Bridge Status API', 'https://app.ontrackegy.com/api/v1/bridge/status'),
]

for name, url in urls:
    try:
        req = urllib.request.Request(url, headers={'User-Agent': 'OnTrackDeployVerifier/1.0'})
        with urllib.request.urlopen(req, timeout=10, context=ctx) as r:
            print(f'[{name}] URL: {url} -> HTTP {r.status} OK (Content-Length: {r.headers.get(\"Content-Length\", \"chunked\")})')
    except urllib.error.HTTPError as e:
        print(f'[{name}] URL: {url} -> HTTP {e.code} (Expected for auth: {e.code in (200, 401)})')
    except Exception as e:
        print(f'[{name}] URL: {url} -> ERROR: {e}')
"
"""
    ssh_verify = [
        "ssh", "-p", server_port, "-i", str(ssh_key),
        "-o", "StrictHostKeyChecking=no",
        server_host,
        verify_script
    ]
    res_v = subprocess.run(ssh_verify, capture_output=True, text=True)
    print("Verification output:\n", res_v.stdout)
    if res_v.stderr:
        print("Verification STDERR:\n", res_v.stderr)

if __name__ == "__main__":
    main()
