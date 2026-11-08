#!/usr/bin/env python3
"""Diagnose WhatsApp CLI safely; never dispatch when production has queued jobs.

Run only over the approved SSH account. Isolated smoke uses a fresh disposable
SQLite namespace. Live run happens only after a fresh read-only zero-job probe.
"""
from pathlib import Path
import json, os, re, shutil, subprocess, tempfile, uuid

PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
WORKER='/home/appontrack/public_html/whatsapp-worker.php'
STAGE=Path('/home/appontrack/.ontrack-mobile/saas-wa-diagnostics-v73')

def exec_worker(env):
    return subprocess.run([PHP,'-q',WORKER],env=env,
                          capture_output=True,text=True,timeout=60)

def describe_error(proc):
    # Production exceptions must not leak raw SQL, credentials or tenant data.
    source=proc.stderr or ''
    match=re.search(r'(PDOException|RuntimeException|InvalidArgumentException|TypeError|ErrorException|ParseError|Error)',source)
    return match.group(1) if match else 'worker_nonzero_exit'

def main():
    if os.getuid()!=2379 or not Path(PHP).is_file() or not Path(WORKER).is_file():
        raise RuntimeError('unauthorized_worker_probe')
    namespace='v73_probe_'+uuid.uuid4().hex[:16]
    root=Path(tempfile.gettempdir())/('ontrack-saas-ci-'+namespace)
    if root.exists() or root.is_symlink():
        raise RuntimeError('unsafe_test_fixture_directory')
    env=dict(os.environ)
    env['ONTRACK_CI_NAMESPACE']=namespace
    try:
        isolated=exec_worker(env)
    finally:
        if root.is_dir() and not root.is_symlink():
            shutil.rmtree(root)
    print(json.dumps({'stage':'isolated','exit_code':isolated.returncode,
        'error_class':None if isolated.returncode==0 else describe_error(isolated)},
        separators=(',',':')),flush=True)
    if isolated.returncode!=0:
        raise RuntimeError('isolated_whatsapp_worker_failed')
    read=subprocess.run([PHP,str(STAGE/'jobs.php')],capture_output=True,
                        text=True,timeout=30)
    if read.returncode!=0:
        raise RuntimeError('read_only_guard_failed')
    data=json.loads(read.stdout)
    if (int(data.get('pending_jobs_total',-1))!=0 or
        int(data.get('potential_worker_jobs',-1))!=0 or
        int(data.get('inflight_jobs_total',-1))!=0):
        print(json.dumps({'stage':'live','result':'not_run_jobs_present',
            'no_dispatch_attempted':True},separators=(',',':')),flush=True)
        return
    # Nothing to send, and no ambiguous external dispatch in progress.
    production_env=dict(os.environ)
    production_env.pop('ONTRACK_CI_NAMESPACE',None)
    production_env.pop('ONTRACK_TEST_PRIVATE',None)
    live=exec_worker(production_env)
    after=subprocess.run([PHP,str(STAGE/'jobs.php')],capture_output=True,
                         text=True,timeout=30)
    heartbeat=False
    if after.returncode==0:
        heartbeat=bool(json.loads(after.stdout).get('whatsapp_heartbeat_seen'))
    print(json.dumps({'stage':'live','prechecked_no_jobs':True,
        'exit_code':live.returncode,
        'worker_heartbeat_seen_after':heartbeat,
        'error_class':None if live.returncode==0 else describe_error(live),
        'no_external_send_attempted':True},separators=(',',':')),flush=True)
    if live.returncode!=0 or not heartbeat:
        raise RuntimeError('production_whatsapp_worker_runtime_unhealthy')
if __name__=='__main__':main()
