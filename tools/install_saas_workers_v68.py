#!/usr/bin/env python3
"""Install only the three SaaS workers missing from current crontab.
Never restart a stopped host-link, replace other jobs or modify credentials.
"""
from pathlib import Path
import os
import subprocess

PHP="/opt/cpanel/ea-php83/root/usr/bin/php"
ROOT=Path("/home/appontrack/public_html")
TASKS=[
    ("operational-worker.php","* * * * *"),
    ("flow-ai-worker.php","* * * * *"),
    ("jobs/knowledge_sync.php","*/5 * * * *"),
]
def cron_read():
    result=subprocess.run(["crontab","-l"],capture_output=True,text=True,timeout=15)
    if result.returncode==0:return result.stdout
    if result.returncode==1 and "no crontab for" in result.stderr.lower():return ""
    raise RuntimeError("crontab_read_denied")
def main():
    if os.getuid()!=2379 or not ROOT.is_dir() or not Path(PHP).is_file():
        raise RuntimeError("wrong_host_user_or_php")
    old=cron_read()
    to_add=[]
    exists=[]
    for target,spec in TASKS:
        path=ROOT/target
        if not path.is_file() or path.is_symlink():
            raise RuntimeError("worker_missing_"+target)
        lint=subprocess.run([PHP,"-l",str(path)],capture_output=True,timeout=20)
        if lint.returncode!=0:raise RuntimeError("worker_php_lint_failed_"+target)
        scheduled=[x for x in old.splitlines() if str(path) in x and not x.lstrip().startswith("#")]
        if scheduled:
            exists.append(target)
            continue  # NEVER overwrite a pre-existing operator schedule.
        to_add.append(spec+" "+PHP+" -q "+str(path)+
            " >/dev/null 2>&1 # OnTrack-SaaS-v68")
    if to_add:
        next_contents=old.rstrip("\n")+"\n"+"\n".join(to_add)+"\n"
        installed=subprocess.run(["crontab","-"],input=next_contents,text=True,
            capture_output=True,timeout=15)
        if installed.returncode!=0:raise RuntimeError("worker_crontab_install_failed")
    actual=cron_read()
    for added in to_add:
        if actual.splitlines().count(added)!=1:
            raise RuntimeError("new_worker_cron_not_verified")
    for line in old.splitlines():
        if actual.splitlines().count(line)<old.splitlines().count(line):
            raise RuntimeError("existing_cron_entry_modified")
    print("V68_ALL_OPERATIONAL_FLOW_KNOWLEDGE_WORKERS_SCHEDULED_PASS")
    print("added_count="+str(len(to_add)))
    print("already_scheduled="+",".join(exists))
    print("host_link_preserved=True")
    print("secrets_unchanged=True")
if __name__=="__main__":main()
