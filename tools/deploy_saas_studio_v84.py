#!/usr/bin/env python3
"""Atomically deploy only the v84 SOG Studio/Flow shadowed-action URL repair.

V78 PHP authorization, CSRF, flow publish, billing and existing account state
are untouched. Run via the approved SSH UID 2379 with private rollback copy.
"""
import fcntl,hashlib,json,os,stat,subprocess,tempfile,time
from pathlib import Path
HOME=Path('/home/appontrack')
PRIVATE=HOME/'.ontrack-mobile'
STAGE=PRIVATE/'saas-studio-v84'
ROOT=HOME/'public_html'
FILES=('assets/js/app.js','assets/js/flow-graph.js')
def sha(raw:bytes)->str:return hashlib.sha256(raw).hexdigest()
def atomic(path:Path,raw:bytes,mode:int)->None:
    fd,temp=tempfile.mkstemp(prefix='.studio-v84-',dir=str(path.parent))
    try:
        with os.fdopen(fd,'wb') as file:
            file.write(raw);file.flush();os.fsync(file.fileno())
        os.chmod(temp,mode)
        os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)
def main():
    if os.getuid()!=2379:raise RuntimeError('unauthorized_host_user')
    for folder in (ROOT,ROOT/'assets',ROOT/'assets/js',PRIVATE,STAGE,STAGE/'assets',STAGE/'assets/js'):
        if folder.is_symlink() or not folder.is_dir():raise RuntimeError('unsafe_stage_or_live_folder')
    old={};new={};modes={}
    for name in FILES:
        src=STAGE/name;dst=ROOT/name
        if src.is_symlink() or dst.is_symlink() or not src.is_file() or not dst.is_file():
            raise RuntimeError('staged_file_missing_or_symlinked')
        data=src.read_bytes()
        if not 12000<len(data)<250000:raise RuntimeError('unexpected_javascript_size')
        if b"getAttribute('action')" not in data:raise RuntimeError('action_attribute_fix_missing')
        if b'form.action' in data or b'editorForm.action' in data or b'id.form.action' in data:
            raise RuntimeError('shadowed_action_still_in_browser_source')
        old[name]=dst.read_bytes();new[name]=data;modes[name]=stat.S_IMODE(dst.stat().st_mode)
    with (PRIVATE/'deploy.lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX)
        for name in FILES:
            if (ROOT/name).read_bytes()!=old[name]:raise RuntimeError('concurrent_host_edit')
        backup=STAGE/('backup-'+str(int(time.time()))+'-'+str(os.getpid()))
        backup.mkdir(mode=0o700)
        for name in FILES:
            path=backup/name
            path.parent.mkdir(parents=True,mode=0o700,exist_ok=True)
            path.write_bytes(old[name]);os.chmod(path,0o600)
        changed=[]
        try:
            for name in FILES:
                if old[name]!=new[name]:
                    atomic(ROOT/name,new[name],modes[name]);changed.append(name)
            for name in FILES:
                if sha((ROOT/name).read_bytes())!=sha(new[name]):
                    raise RuntimeError('published_file_sha_mismatch')
        except BaseException:
            for name in reversed(changed):atomic(ROOT/name,old[name],modes[name])
            raise
    print('V84_SOG_STUDIO_HIDDEN_ACTION_SHADOW_FIX_PUBLISHED_AND_HASH_VERIFIED_PASS',flush=True)
    print(json.dumps({'status':'published','changed':changed,
      'backup':str(backup),'source_sha256':{name:sha(new[name]) for name in FILES},
      'database_mutations':0,'phone_settings_modified':False,
      'cron_unchanged':True,'android_ota_unchanged':True},separators=(',',':')),flush=True)
if __name__=='__main__':main()
