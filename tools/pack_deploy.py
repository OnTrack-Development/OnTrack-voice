#!/usr/bin/env python3
"""Pack the saas/ application overlay into host-link exec job files.

Produces one job per base64 chunk (<16KB each) that appends to a bundle file,
plus a final job that reassembles, extracts, and runs migrations.
Usage: python tools/pack_deploy.py <saas_dir> <out_dir> [first_job_index]
"""
import base64
import io
import json
import os
import sys
import tarfile
import time
from pathlib import Path

INCLUDE = ["app", "routes", "resources", "database/migrations", "public/assets", "IMPLEMENTATION_STATUS.md"]
EXCLUDE_SUFFIXES = (".log",)
CHUNK = 8000


def build_tar(saas: Path) -> bytes:
    buf = io.BytesIO()
    with tarfile.open(fileobj=buf, mode="w:gz", compresslevel=9) as tar:
        for item in INCLUDE:
            path = saas / item
            if path.is_file():
                tar.add(path, arcname=item)
                continue
            if not path.is_dir():
                continue
            for root, dirs, files in os.walk(path):
                dirs.sort()
                for name in sorted(files):
                    full = Path(root) / name
                    if full.suffix in EXCLUDE_SUFFIXES:
                        continue
                    tar.add(full, arcname=str(full.relative_to(saas)))
    return buf.getvalue()


def main() -> int:
    saas = Path(sys.argv[1]).resolve()
    out = Path(sys.argv[2]).resolve()
    start = int(sys.argv[3]) if len(sys.argv) > 3 else 1
    out.mkdir(parents=True, exist_ok=True)

    payload = base64.b64encode(build_tar(saas)).decode("ascii")
    chunks = [payload[i:i + CHUNK] for i in range(0, len(payload), CHUNK)]
    now = int(time.time())
    files = []

    for idx, chunk in enumerate(chunks):
        job_id = "deploy-app-%d-%02d" % (start, idx + 1)
        script = "mkdir -p \"$HOME/deploy\" && printf '%%s' '%s' >> \"$HOME/deploy/bundle.b64\" && wc -c \"$HOME/deploy/bundle.b64\"" % chunk
        job = {
            "schema": 1, "id": job_id, "target": "appontrack-server11", "action": "exec",
            "created_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime(now)),
            "expires_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime(now + 3600)),
            "cwd": "/home/appontrack", "argv": ["/bin/sh", "-c", script], "timeout_seconds": 20,
        }
        path = out / (job_id + ".json")
        path.write_text(json.dumps(job, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        files.append((job_id, path.stat().st_size))

    final_id = "deploy-app-%d-final" % start
    final_script = (
        "set -e; cd \"$HOME/deploy\"; base64 -d bundle.b64 > bundle.tar.gz; "
        "rm -rf unpacked; mkdir -p unpacked; tar xzf bundle.tar.gz -C unpacked; "
        "cp -a unpacked/. /home/appontrack/public_html/saas/; "
        "cd /home/appontrack/public_html/saas; "
        "/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear >/dev/null 2>&1 || true; "
        "/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force 2>&1 | tail -15; "
        "echo '---ROUTES---'; /opt/cpanel/ea-php83/root/usr/bin/php artisan route:list --except-vendor 2>&1 | head -20; "
        "rm -f \"$HOME/deploy/bundle.b64\" \"$HOME/deploy/bundle.tar.gz\"; echo DEPLOY_DONE"
    )
    job = {
        "schema": 1, "id": final_id, "target": "appontrack-server11", "action": "exec",
        "created_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime(now)),
        "expires_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime(now + 3600)),
        "cwd": "/home/appontrack", "argv": ["/bin/sh", "-c", final_script], "timeout_seconds": 30,
    }
    path = out / (final_id + ".json")
    path.write_text(json.dumps(job, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    files.append((final_id, path.stat().st_size))

    print("payload_bytes=%d chunks=%d" % (len(payload), len(chunks)))
    for job_id, size in files:
        print("%-28s %6d" % (job_id, size))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())