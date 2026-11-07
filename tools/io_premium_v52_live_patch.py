#!/usr/bin/env python3
"""Atomic reviewed delta from previous IO Premium source to v52.

Run only on the authorized host from BEDIR. Never replace entire production
files with repository snapshots: preserve all unrelated host edits. All files
are staged in 0700 private directories and syntax-checked before any write.
"""
import difflib
import fcntl
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import time

ROOT=Path('/home/appontrack/public_html')
PRIVATE=Path('/home/appontrack/.ontrack-mobile')
STAGE=PRIVATE/'io-premium-v52-delta-stage'
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
PATHS=[
 '_core/IoPremiumProvider.php',
 '_core/Views.php',
 '_core/Actions.php',
 '_core/GoogleTenantKeys.php',
 '_core/TenantVoiceSession.php',
 'device/v1/tts.php',
]
def sha(data):
 return hashlib.sha256(data).hexdigest()

def as_text(path):
 if path.is_symlink() or not path.is_file():raise RuntimeError('unsafe_source_'+path.name)
 data=path.read_bytes()
 if b'\0' in data or len(data)>800000:raise RuntimeError('binary_or_large_'+path.name)
 return data.decode('utf-8-sig').replace('\r\n','\n')

def anchor_patch(live, old, new, label):
 if old==new:return live
 if old and live.count(old)==1:return live.replace(old,new,1)
 if new and new in live and (not old or old not in live):return live
 raise RuntimeError('reviewed_source_mismatch_'+label)

def apply_diff(live, before, after, label):
 original=before.splitlines(keepends=True)
 updated=after.splitlines(keepends=True)
 opcodes=difflib.SequenceMatcher(None,original,updated,autojunk=False).get_opcodes()
 # Reverse ensures source-before-context for each hunk is still unchanged.
 for tag,i1,i2,j1,j2 in reversed(opcodes):
  if tag=='equal':continue
  oldpart=''.join(original[i1:i2])
  newpart=''.join(updated[j1:j2])
  if oldpart and live.count(oldpart)==1:
   live=live.replace(oldpart,newpart,1)
   continue
  if newpart and newpart in live and (not oldpart or oldpart not in live):
   continue
  resolved=False
  for width in range(1,17):
   before_context=''.join(original[max(0,i1-width):i1])
   after_context=''.join(original[i2:i2+width])
   needle=before_context+oldpart+after_context
   replacement=before_context+newpart+after_context
   if needle and live.count(needle)==1:
    live=live.replace(needle,replacement,1);resolved=True;break
   if replacement and replacement in live and needle not in live:
    resolved=True;break
  if not resolved:raise RuntimeError('live_anchor_conflict_'+label+'_'+str(i1))
 return live

def dedupe_provider_ui(live):
 start=live.find('function render_voices(array $a):void{')
 end=live.find('function render_secret_controls(',start+1)
 if start<0 or end<0:raise RuntimeError('voice_screen_boundaries_missing')
 section=live[start:end]
 guard="    if($showProvider && in_array((string)$a['role'],['owner','admin'],true) && !active_support())"
 call="        echo render_io_premium_admin_provider();"
 pair=guard+'\\n'+call
 # Remove the duplicated entire conditional+echo pair, not only its echo:
 # dropping just the echo would wrongly gate the next catalog statement!
 double=pair+'\\n'+pair
 if double in section:
  section=section.replace(double,pair,1)
 # Self-heal the orphaned conditional left by older incomplete patchers.
 orphan=pair+'\\n'+guard+'\\n'
 if orphan in section:
  section=section.replace(orphan,pair+'\\n',1)
 if section.count(call)!=1 or section.count(guard)!=1:
  raise RuntimeError('unsafe_or_duplicate_premium_voice_panel')
 return live[:start]+section+live[end:]

def atomic(path,data,mode):
 fd,tmp=tempfile.mkstemp(dir=str(path.parent),prefix='.voice-v52-')
 try:
  with os.fdopen(fd,'wb') as f:
   f.write(data);f.flush();os.fsync(f.fileno())
  os.chmod(tmp,mode)
  os.replace(tmp,path)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)

def main():
 if os.getuid()!=2379 or ROOT.is_symlink() or not ROOT.is_dir() or PRIVATE.is_symlink() or not PRIVATE.is_dir():
  raise RuntimeError('host_identity_or_root_not_verified')
 if STAGE.is_symlink() or not STAGE.is_dir():raise RuntimeError('private_stage_missing')
 lockfile=PRIVATE/'io-premium-v52-publish.lock'
 with lockfile.open('a') as lock:
  fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
  plans={}
  for rel in PATHS:
   old_source=as_text(STAGE/'old'/rel)
   new_source=as_text(STAGE/'new'/rel)
   dst=ROOT/rel
   if dst.parent.is_symlink() or dst.is_symlink():raise RuntimeError('unsafe_live_'+rel)
   live=as_text(dst)
   patched=apply_diff(live,old_source,new_source,rel)
   if rel=='_core/Views.php':patched=dedupe_provider_ui(patched)
   if rel=='_core/IoPremiumProvider.php' and patched.count('function io_premium_access_allowed(')!=1:
    raise RuntimeError('premium_access_helper_missing')
   if rel=='_core/Actions.php' and patched.count("case 'save_io_premium_access':")!=1:
    raise RuntimeError('premium_access_action_missing')
   if rel=='device/v1/tts.php' and 'premium_voice_not_granted' in patched:
    raise RuntimeError('legacy_tts_grant_still_blocks_global_voice')
   if patched.count('<?php')!=1:raise RuntimeError('php_prefix_mismatch_'+rel)
   data=patched.encode('utf-8')
   # Verify syntax of each computed production delta, not just GitHub source.
   fd,tmp=tempfile.mkstemp(dir=str(STAGE),suffix='.php')
   try:
    with os.fdopen(fd,'wb') as f:f.write(data)
    check=subprocess.run([PHP,'-l',tmp],capture_output=True,timeout=12)
    if check.returncode!=0:raise RuntimeError('php_syntax_failed_'+rel)
   finally:os.unlink(tmp)
   plans[rel]=(dst,live.encode('utf-8'),data,dst.stat().st_mode & 0o777)
  stamp=str(int(time.time()))+'-'+str(os.getpid())
  backup=PRIVATE/'io-premium-v52-backups'/stamp
  backup.mkdir(parents=True,mode=0o700,exist_ok=False)
  updated=[]
  try:
   for rel,(dst,was,will,mode) in plans.items():
    orig=backup/rel;orig.parent.mkdir(parents=True,exist_ok=True)
    orig.write_bytes(was);orig.chmod(0o600)
   for rel,(dst,was,will,mode) in plans.items():
    current=dst.read_bytes().replace(b'\r\n',b'\n')
    if sha(current)!=sha(was):raise RuntimeError('concurrent_host_edit_'+rel)
    if was!=will:
     atomic(dst,will,mode);updated.append(rel)
    if sha(dst.read_bytes().replace(b'\r\n',b'\n'))!=sha(will):
     raise RuntimeError('post_deploy_sha_mismatch_'+rel)
   result={'status':'PUBLISHED','changed':updated,'rollback_path':str(backup),
           'secrets_untouched':True,'site_scope':'six_php_files_only',
           'files':{rel:sha(v[2]) for rel,v in plans.items()}}
   print(json.dumps(result,ensure_ascii=False,separators=(',',':')),flush=True)
  except BaseException:
   for rel in reversed(updated):
    dst,was,_,mode=plans[rel]
    if sha(dst.read_bytes().replace(b'\r\n',b'\n'))!=sha(was):
     atomic(dst,was,mode)
   raise
if __name__=='__main__':main()
