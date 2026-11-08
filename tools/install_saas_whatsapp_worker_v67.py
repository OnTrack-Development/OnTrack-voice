#!/usr/bin/env python3
"""Install ONLY the opt-in OnTrack WhatsApp AI worker cron on authorized host.
Preserves all existing cron entries, never restarts Host-Link or monitoring.
Only explicitly owner-enabled WhatsApp channels are processed by the worker.
"""
from pathlib import Path
import os
import subprocess
import sys

PHP="/opt/cpanel/ea-php83/root/usr/bin/php"
WORKER="/home/appontrack/public_html/whatsapp-worker.php"
LINE=f"* * * * * {PHP} -q {WORKER} >/dev/null 2>&1 # OnTrack-WhatsApp-Worker-v67"

def read_cron():
    response=subprocess.run(["crontab","-l"],capture_output=True,text=True,timeout=15)
    if response.returncode==0:
        return response.stdout
    if response.returncode==1 and "no crontab for" in response.stderr.lower():
        return ""
    raise RuntimeError("cron_read_unavailable")

def main():
    if os.getuid()!=2379 or not Path(PHP).is_file() or not Path(WORKER).is_file():
        raise RuntimeError("unauthorized_host_or_worker_missing")
    lint=subprocess.run([PHP,"-l",WORKER],capture_output=True,timeout=20)
    if lint.returncode!=0:
        raise RuntimeError("whatsapp_worker_live_php_invalid")
    before=read_cron()
    matches=[x for x in before.splitlines() if WORKER in x and not x.lstrip().startswith("#")]
    if matches and matches!=[LINE]:
        raise RuntimeError("existing_worker_schedule_requires_manual_reconciliation")
    if not matches:
        proposed=before.rstrip("\n")+"\n"+LINE+"\n"
        write=subprocess.run(["crontab","-"],input=proposed,text=True,capture_output=True,timeout=20)
        if write.returncode!=0:
            raise RuntimeError("cron_install_denied")
    after=read_cron()
    if [x for x in after.splitlines() if WORKER in x and not x.lstrip().startswith("#")]!=[LINE]:
        raise RuntimeError("cron_worker_schedule_verification_failed")
    original=[x for x in before.splitlines() if x.strip()]
    current=[x for x in after.splitlines() if x.strip()]
    if len(current)!=len(original)+(0 if matches else 1):
        raise RuntimeError("unexpected_change_to_existing_cron_lines")
    print("V67_WHATSAPP_DURABLE_WORKER_CRON_INSTALLED_PASS")
    print("status=already_installed" if matches else "status=newly_installed")
    print("existing_cron_entries_preserved=True")
    print("host_link_not_touched=True")
    print("worker_enabled_channels_only=True")

if __name__=="__main__":
    main()
