import os
import subprocess
from pathlib import Path

key = Path(os.environ["USERPROFILE"]) / ".ssh" / "ontrack_publish_ed25519"
script = """
set -e
DB_PASS=$(grep '^DB_PASSWORD=' /home/appontrack/iovoice-app/.env | cut -d= -f2 | tr -d '"' | tr -d "'")
mysql -h 127.0.0.1 -P 3306 -u appontrack_iovoice -p"$DB_PASS" appontrack_iovoice -e 'SELECT id, subject, latest_message FROM support_tickets ORDER BY id DESC LIMIT 1;'
"""

cmd = ["ssh", "-p", "7195", "-i", str(key), "-o", "StrictHostKeyChecking=no", "appontrack@65.21.208.232", script]
res = subprocess.run(cmd, capture_output=True, text=True)
print("STDOUT:\n", res.stdout)
if res.stderr:
    print("STDERR:\n", res.stderr)
