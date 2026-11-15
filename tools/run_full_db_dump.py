import os, subprocess
from pathlib import Path

key = Path(os.environ["USERPROFILE"]) / ".ssh" / "ontrack_publish_ed25519"
script = """
set -e
DB_PASS=$(grep "^DB_PASSWORD=" /home/appontrack/iovoice-app/.env | cut -d= -f2)
mysqldump -h 127.0.0.1 -u appontrack_iovoice -p"$DB_PASS" appontrack_iovoice > /home/appontrack/backups/db-v81-dump.sql
ls -lh /home/appontrack/backups/db-v81-dump.sql

# Update archive to include the full db dump
tar -czf /home/appontrack/backups/checkpoint-v81-0.3.58.tar.gz \
    --exclude='public_html/mobile/updates/native/*.apk' \
    --exclude='iovoice-app/storage/logs/*.log' \
    /home/appontrack/public_html \
    /home/appontrack/iovoice-app \
    /home/appontrack/ws-relay \
    /home/appontrack/backups/db-v81-dump.sql 2>/dev/null || true

ls -lh /home/appontrack/backups/checkpoint-v81-0.3.58.tar.gz
"""

cmd = ["ssh", "-p", "7195", "-i", str(key), "-o", "StrictHostKeyChecking=no", "appontrack@65.21.208.232", script]
res = subprocess.run(cmd, capture_output=True, text=True)
print("STDOUT:\n", res.stdout)
if res.stderr:
    print("STDERR:\n", res.stderr)
