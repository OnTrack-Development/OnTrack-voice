import os
import subprocess
from pathlib import Path

def main():
    ssh_key = Path(os.environ["USERPROFILE"]) / ".ssh" / "ontrack_publish_ed25519"
    php_code = """<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
$kernel->bootstrap();

$token = hash('sha256', config('app.key') . 'dev-preview-salt');
echo $token;
"""
    tmp = Path(r"C:\OnTrack-voice\tools\temp_token.php")
    tmp.write_text(php_code, encoding="utf-8")

    scp_cmd = ["scp", "-P", "7195", "-i", str(ssh_key), "-o", "StrictHostKeyChecking=no", str(tmp), "appontrack@65.21.208.232:/home/appontrack/iovoice-app/temp_token.php"]
    subprocess.run(scp_cmd, check=True)

    ssh_cmd = ["ssh", "-p", "7195", "-i", str(ssh_key), "-o", "StrictHostKeyChecking=no", "appontrack@65.21.208.232", "cd /home/appontrack/iovoice-app && /usr/local/bin/ea-php82 temp_token.php && rm -f temp_token.php"]
    res = subprocess.run(ssh_cmd, capture_output=True, text=True)
    token = res.stdout.strip()
    if tmp.exists():
        tmp.unlink()
    print("TOKEN:" + token)

if __name__ == "__main__":
    main()
