#!/usr/bin/env python3
"""Install only Fish TTS typed failover (one PHP change, one new PHP helper).
Requires SSH as the authorized hosting user and a PRIVATE 0700 staging dir.
Stops on unexpected host drift, preserves existing config and rolls back.
"""
from pathlib import Path
import difflib,fcntl,hashlib,json,os,stat,subprocess,tempfile,time

ROOT=Path('/home/appontrack/public_html')
STAGE=Path('/home/appontrack/.ontrack-mobile/fish-fallback-v54')
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'

def sha(b):return hashlib.sha256(b).hexdigest()
def text(path):
    if path.is_symlink() or not path.is_file():raise RuntimeError('missing_or_symlinked_source_'+path.name)
    return path.read_text(encoding='utf-8-sig').replace('\r\n','\n')
def patch(live,old,new):
    initial=old.splitlines(keepends=True)
    final=new.splitlines(keepends=True)
    for tag,i,j,k,l in reversed(difflib.SequenceMatcher(None,initial,final,autojunk=False).get_opcodes()):
        if tag=='equal':continue
        before=''.join(initial[i:j])
        after=''.join(final[k:l])
        if before and live.count(before)==1:
            live=live.replace(before,after,1)
            continue
        if after and after in live and before not in live:continue
        found=False
        for radius in range(1,10):
            prefix=''.join(initial[max(0,i-radius):i])
            suffix=''.join(initial[j:j+radius])
            match=prefix+before+suffix
            replace=prefix+after+suffix
            if match and live.count(match)==1:
                live=live.replace(match,replace,1);found=True;break
            if replace and replace in live and match not in live:
                found=True;break
        if not found:raise RuntimeError('production_tts_drift_hunk_'+str(i))
    return live

def atomic(path,data,mode):
    fd,tmp=tempfile.mkstemp(dir=str(path.parent),prefix='.fish-v54-')
    try:
        with os.fdopen(fd,'wb') as f:
            f.write(data);f.flush();os.fsync(f.fileno())
        os.chmod(tmp,mode)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

def lint(contents,name):
    fd,tmp=tempfile.mkstemp(dir=str(STAGE),suffix='.php')
    try:
        with os.fdopen(fd,'wb') as file:file.write(contents)
        result=subprocess.run([PHP,'-l',tmp],capture_output=True,timeout=20)
        if result.returncode!=0:raise RuntimeError('php_lint_rejected_'+name)
    finally:os.unlink(tmp)

def main():
    if os.getuid()!=2379 or ROOT.is_symlink() or not ROOT.is_dir():
        raise RuntimeError('unauthorized_host_or_root')
    if STAGE.is_symlink() or not STAGE.is_dir() or stat.S_IMODE(STAGE.stat().st_mode)!=0o700:
        raise RuntimeError('private_0700_stage_required')
    target=ROOT/'device/v1/tts.php'
    helper=ROOT/'device/v1/FishTtsFallbackPolicy.php'
    if target.is_symlink() or helper.is_symlink() or not target.is_file():
        raise RuntimeError('unsafe_tts_destination')
    patched=patch(text(target),text(STAGE/'old_tts.php'),text(STAGE/'new_tts.php'))
    policy=text(STAGE/'FishTtsFallbackPolicy.php')
    if patched.count("require_once __DIR__.'/FishTtsFallbackPolicy.php';")!=1:
        raise RuntimeError('helper_include_must_be_exactly_once')
    if patched.count('FishTtsFallbackPolicy::classify(')!=1:
        raise RuntimeError('fish_classifier_missing')
    if policy.count('final class FishTtsFallbackPolicy')!=1:
        raise RuntimeError('provider_class_missing')
    old=target.read_bytes()
    new=patched.encode('utf-8')
    h=policy.encode('utf-8')
    lint(new,'tts')
    lint(h,'policy')
    if helper.exists() and helper.read_bytes()!=h:raise RuntimeError('conflicting_existing_helper')
    # Preexisting but identical deployment is idempotent.
    if helper.exists() and old==new:
        print(json.dumps({'result':'VERIFIED_NO_CHANGE','tts_sha256':sha(new),
                          'helper_sha256':sha(h),'secrets_untouched':True}));return
    lock=STAGE/'publish.lock'
    with lock.open('a') as lf:
        fcntl.flock(lf,fcntl.LOCK_EX|fcntl.LOCK_NB)
        backup=STAGE/('backup-'+str(int(time.time()))+'-'+str(os.getpid()))
        backup.mkdir(mode=0o700)
        (backup/'tts.php').write_bytes(old)
        (backup/'tts.php').chmod(0o600)
        mode=stat.S_IMODE(target.stat().st_mode)
        touched=[]
        try:
            if not helper.exists():
                atomic(helper,h,0o644);touched.append('helper')
            if sha(target.read_bytes())!=sha(old):raise RuntimeError('concurrent_tts_edit')
            if old!=new:
                atomic(target,new,mode);touched.append('tts')
            if sha(target.read_bytes())!=sha(new) or sha(helper.read_bytes())!=sha(h):
                raise RuntimeError('post_install_hash_mismatch')
            print(json.dumps({'result':'PUBLISHED',
                 'tts_sha256':sha(new),'helper_sha256':sha(h),
                 'changed':touched,'backup':str(backup),
                 'secrets_untouched':True,'admin_settings_untouched':True}))
        except BaseException:
            if 'tts' in touched:atomic(target,old,mode)
            if 'helper' in touched and helper.exists():helper.unlink()
            raise

if __name__=='__main__':main()
