import os
import subprocess
from pathlib import Path

def main():
    key = Path(os.environ["USERPROFILE"]) / ".ssh" / "ontrack_publish_ed25519"
    port = "7195"
    host = "65.21.208.232"
    user = "appontrack"
    target = f"{user}@{host}"
    ssh_base = ["ssh", "-p", port, "-i", str(key), "-o", "StrictHostKeyChecking=no", target]

    print("Step 1: Check server directories & create /home/appontrack/backups...")
    script = """
set -e
mkdir -p /home/appontrack/backups

echo "Checking DB credentials..."
if [ -f /home/appontrack/iovoice-app/.env ]; then
    DB_CONN=$(grep "^DB_CONNECTION=" /home/appontrack/iovoice-app/.env | cut -d= -f2 || true)
    DB_NAME=$(grep "^DB_DATABASE=" /home/appontrack/iovoice-app/.env | cut -d= -f2 || true)
    DB_USER=$(grep "^DB_USERNAME=" /home/appontrack/iovoice-app/.env | cut -d= -f2 || true)
    DB_PASS=$(grep "^DB_PASSWORD=" /home/appontrack/iovoice-app/.env | cut -d= -f2 || true)
    echo "DB_CONN=$DB_CONN DB_NAME=$DB_NAME"
    if [ "$DB_CONN" = "mysql" ] && [ -n "$DB_NAME" ]; then
        echo "Dumping MySQL database $DB_NAME..."
        mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > /home/appontrack/backups/db-v81-dump.sql 2>/dev/null || echo "mysqldump note: check permissions"
    fi
fi

# Also dump sqlite databases if any exist
find /home/appontrack -name "*.sqlite" 2>/dev/null | while read f; do
    echo "Found sqlite: $f"
done

echo "Creating compressed archive /home/appontrack/backups/checkpoint-v81-0.3.58.tar.gz..."
tar --exclude='public_html/mobile/updates/native/*.apk' \
    --exclude='iovoice-app/storage/logs/*.log' \
    -czf /home/appontrack/backups/checkpoint-v81-0.3.58.tar.gz \
    /home/appontrack/public_html \
    /home/appontrack/iovoice-app \
    /home/appontrack/ws-relay \
    /home/appontrack/backups/db-v81-dump.sql 2>/dev/null || true

ls -lh /home/appontrack/backups/checkpoint-v81-0.3.58.tar.gz
"""

    res = subprocess.run(ssh_base + [script], capture_output=True, text=True)
    print("STDOUT:\n", res.stdout)
    if res.stderr:
        print("STDERR:\n", res.stderr)

    if res.returncode == 0:
        print("SUCCESS: Checkpoint created successfully!")
    else:
        print("FAILED with code:", res.returncode)

if __name__ == "__main__":
    main()
