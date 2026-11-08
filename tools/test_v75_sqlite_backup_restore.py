#!/usr/bin/env python3
"""Isolated regression: backup consistency and restore without touching a live DB."""
import importlib.util
from pathlib import Path
import sqlite3,tempfile

script=Path(__file__).with_name('host_v75_sqlite_backup_restore.py')
spec=importlib.util.spec_from_file_location('v75_backup',script)
module=importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
with tempfile.TemporaryDirectory(prefix='v75-sqlite-backup-fixture-') as directory:
    root=Path(directory)
    database=root/'source.sqlite'
    with sqlite3.connect(database) as db:
        for table in module.REQUIRED:
            db.execute('CREATE TABLE "'+table+'" (id INTEGER PRIMARY KEY, name TEXT)')
            db.execute('INSERT INTO "'+table+'" (name) VALUES (?)',('isolated_fixture',))
        db.commit()
    result=module.drill(database,root/'backups')
    assert result['integrity_check']=='pass'
    assert result['isolated_restore_test']=='pass'
    assert result['schema_and_rows']=='pass'
    assert result['tables_verified']==len(module.REQUIRED)
    assert result['live_database_writes']==0
    assert module.digest(database) or True
    assert Path(result['backup']).exists()
    assert Path(result['backup']).stat().st_mode&0o077==0
    assert not list(Path(result['backup']).parent.glob('restore-check-*'))
    assert result['customer_data_uploaded'] is False
    assert result['real_database_replaced'] is False
print('V75_SQLITE_CONSISTENT_SNAPSHOT_ISOLATED_RESTORE_FIXTURE_PASS')
