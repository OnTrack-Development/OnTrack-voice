#!/usr/bin/env python3
"""Create a consistent PRIVATE SQLite backup and verify isolated restore.

Read-only source connection with SQLite's online backup API. No live DB
replacement, no account changes, no Cron, no offsite upload, no secrets in logs.
"""
from pathlib import Path
from contextlib import closing
import hashlib
import json
import os
import shutil
import sqlite3
import tempfile
import time
import urllib.parse

HOST_PRIVATE=Path('/home/appontrack/.ontrack-saas')
HOST_SOURCE=HOST_PRIVATE/'ontrack-console.sqlite'
HOST_DEST=HOST_PRIVATE/'backup-drills'
REQUIRED=('accounts','users','memberships','subscriptions','device_bindings','outbox','wa_ai_jobs')

def digest(file:Path)->str:
    sha=hashlib.sha256()
    with file.open('rb') as stream:
        for block in iter(lambda:stream.read(1024*1024),b''):
            sha.update(block)
    return sha.hexdigest()

def ro(path:Path)->sqlite3.Connection:
    uri='file:'+urllib.parse.quote(str(path),safe='/')+'?mode=ro'
    return sqlite3.connect(uri,uri=True,timeout=10.0)

def integrity(db:sqlite3.Connection)->bool:
    result=db.execute('PRAGMA integrity_check').fetchall()
    return len(result)==1 and result[0][0]=='ok'

def signatures(db:sqlite3.Connection)->dict:
    table_names={row[0] for row in db.execute(
        "SELECT name FROM sqlite_master WHERE type='table'")}
    missing=set(REQUIRED)-table_names
    if missing:raise RuntimeError('required_backup_schema_missing')
    return {name:int(db.execute('SELECT COUNT(*) FROM "'+name+'"').fetchone()[0])
            for name in REQUIRED}

def drill(source:Path,destination_root:Path)->dict:
    if source.is_symlink() or not source.is_file():
        raise RuntimeError('unsafe_live_sqlite_source')
    if source.stat().st_size<4096 or source.stat().st_size>512*1024*1024:
        raise RuntimeError('sqlite_backup_size_outside_guardrails')
    if destination_root.is_symlink():
        raise RuntimeError('backup_dir_symlink_forbidden')
    destination_root.mkdir(mode=0o700,parents=True,exist_ok=True)
    os.chmod(destination_root,0o700)
    required_space=max(80*1024*1024,source.stat().st_size*3)
    if shutil.disk_usage(destination_root).free<required_space:
        raise RuntimeError('insufficient_safe_backup_storage')
    folder=destination_root/('backup-'+time.strftime('%Y%m%dT%H%M%SZ',time.gmtime())+'-'+os.urandom(4).hex())
    folder.mkdir(mode=0o700)
    snapshot=folder/'ontrack-console.sqlite'
    original_umask=os.umask(0o077)
    try:
        with closing(ro(source)) as live:
            with closing(sqlite3.connect(str(snapshot),timeout=15.0)) as saved:
                live.backup(saved,pages=256,sleep=0.15)
                saved.commit()
        os.chmod(snapshot,0o600)
        with closing(ro(snapshot)) as backed:
            if not integrity(backed):raise RuntimeError('snapshot_integrity_failed')
            snapshot_counts=signatures(backed)
            snapshot_schema=tuple(backed.execute(
                "SELECT type,name,sql FROM sqlite_master WHERE sql IS NOT NULL ORDER BY type,name"))
        with tempfile.TemporaryDirectory(prefix='restore-check-',dir=str(folder)) as temporary:
            restored=Path(temporary)/'ontrack-console.sqlite'
            shutil.copyfile(snapshot,restored)
            os.chmod(restored,0o600)
            if digest(snapshot)!=digest(restored):
                raise RuntimeError('restored_copy_hash_mismatch')
            with closing(ro(restored)) as recovery:
                if not integrity(recovery):raise RuntimeError('restored_integrity_failed')
                if signatures(recovery)!=snapshot_counts:
                    raise RuntimeError('restored_tables_counts_mismatch')
                restored_schema=tuple(recovery.execute(
                    "SELECT type,name,sql FROM sqlite_master WHERE sql IS NOT NULL ORDER BY type,name"))
                if restored_schema!=snapshot_schema:
                    raise RuntimeError('restored_schema_mismatch')
        return {'backup':str(snapshot),'backup_sha256':digest(snapshot),
                'backup_bytes':snapshot.stat().st_size,
                'integrity_check':'pass','schema_and_rows':'pass',
                'tables_verified':len(REQUIRED),'isolated_restore_test':'pass',
                'live_database_writes':0,'customer_data_uploaded':False,
                'real_database_replaced':False}
    except BaseException:
        shutil.rmtree(folder,ignore_errors=True)
        raise
    finally:
        os.umask(original_umask)

def main():
    if os.getuid()!=2379 or not HOST_PRIVATE.is_dir() or HOST_PRIVATE.is_symlink():
        raise RuntimeError('unauthorized_or_unexpected_host')
    # Fail closed if production starts using MySQL: never back up a legacy
    # SQLite file and pretend it covers the currently active MySQL database.
    config=HOST_PRIVATE/'database.json'
    if config.is_file():
        settings=json.loads(config.read_text())
        if settings.get('driver')=='mysql':
            raise RuntimeError('active_database_is_mysql_use_mysql_snapshot_procedure')
    result=drill(HOST_SOURCE,HOST_DEST)
    print('V75_PRIVATE_SQLITE_ONLINE_BACKUP_AND_ISOLATED_RESTORE_PASS',flush=True)
    print(json.dumps(result,separators=(',',':')),flush=True)
if __name__=='__main__':main()
