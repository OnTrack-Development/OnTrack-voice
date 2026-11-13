#!/usr/bin/env python3
"""Signed OnTrack Voice v70 and SaaS file-only release, host-safe with rollback.

Runner: package OUTPUT.zip. On authorized host: deploy PRIVATE_STAGE SOURCE_SHA.
No DB, OAuth, host-link, or other services are touched.
"""
import base64,hashlib,json,os,subprocess,sys,tempfile,zipfile
from pathlib import Path
CERT='d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f'
PRIVATE=Path('/home/appontrack/.ontrack-mobile')
WEB=Path('/home/appontrack/public_html')
APP=Path('/home/appontrack/iovoice-app')
SITE={
 'saas/console/_core/Bootstrap.php':WEB/'saas/console/_core/Bootstrap.php',
 'saas/console/_core/Views.php':WEB/'saas/console/_core/Views.php',
 'saas/app/Http/Controllers/Enterprise/ActionController.php':APP/'app/Http/Controllers/Enterprise/ActionController.php',
 'saas/app/Services/Voice/VoiceCatalogService.php':APP/'app/Services/Voice/VoiceCatalogService.php',
 'saas/resources/views/enterprise/layout.blade.php':APP/'resources/views/enterprise/layout.blade.php',
 'saas/resources/views/enterprise/tabs/admin.blade.php':APP/'resources/views/enterprise/tabs/admin.blade.php',
}
def digest(data):return hashlib.sha256(data).hexdigest()
def atomic(path,data):
 fd,tmp=tempfile.mkstemp(prefix='.ontrack-v70-',dir=path.parent)
 try:
  with os.fdopen(fd,'wb') as f:f.write(data);f.flush();os.fsync(f.fileno())
  os.chmod(tmp,0o644);os.replace(tmp,path)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
def package(out):
 commit=os.environ.get('GITHUB_SHA','')
 if len(commit)!=40:raise ValueError('missing build sha')
 out.parent.mkdir(parents=True,exist_ok=True)
 with zipfile.ZipFile(out,'w',compression=zipfile.ZIP_DEFLATED) as z:
  hashes={}
  for path in SITE:
   data=Path(path).read_bytes();hashes[path]=digest(data);z.writestr(path,data)
  z.writestr('site-manifest.json',json.dumps({'commit':commit,'hashes':hashes},sort_keys=True))
 print(json.dumps({'site_commit':commit,'files':len(SITE),'archive_sha256':digest(out.read_bytes())}))
def signed(folder):
 cert=(folder/'signing-cert.der').read_bytes()
 if digest(cert)!=CERT:raise ValueError('wrong signing certificate')
 env=(folder/'latest.json').read_bytes();box=json.loads(env);payload=box['payload'];meta=json.loads(payload)
 if meta.get('schema')!=1 or meta.get('channel')!='stable':raise ValueError('bad release channel')
 with tempfile.TemporaryDirectory(dir=PRIVATE) as td:
  t=Path(td);(t/'cert').write_bytes(cert);(t/'payload').write_bytes(payload.encode())
  (t/'sig').write_bytes(base64.b64decode(box['signature'],validate=True))
  (t/'pub').write_bytes(subprocess.check_output(['openssl','x509','-inform','DER','-in',str(t/'cert'),'-pubkey','-noout'],timeout=8))
  subprocess.run(['openssl','dgst','-sha256','-verify',str(t/'pub'),'-signature',str(t/'sig'),str(t/'payload')],check=True,timeout=8,stdout=subprocess.DEVNULL)
 return env,meta
def deploy(stage,commit):
 import fcntl  # Linux host only; packaging also runs on Windows
 if os.getuid()!=2379 or PRIVATE.is_symlink() or WEB.is_symlink() or APP.is_symlink():raise ValueError('wrong owner/host')
 if not PRIVATE.is_dir() or not WEB.is_dir() or not APP.is_dir():raise ValueError('missing canonical host paths')
 if stage.is_symlink() or not stage.resolve().is_relative_to(PRIVATE.resolve()):raise ValueError('unapproved stage')
 with zipfile.ZipFile(stage/'site.zip') as z:
  if set(z.namelist())!=set(SITE)|{'site-manifest.json'}:raise ValueError('wrong site file list')
  info=json.loads(z.read('site-manifest.json'))
  if info['commit']!=commit or set(info['hashes'])!=set(SITE):raise ValueError('wrong commit / site list')
  site={p:z.read(p) for p in SITE}
  if any(digest(v)!=info['hashes'][p] or len(v)>2000000 for p,v in site.items()):raise ValueError('bad site hash')
 ui,um=signed(stage/'ui');native,nm=signed(stage/'native')
 if um.get('revision')!=70 or um.get('min_native',99)>69 or um.get('max_native',0)<70:raise ValueError('wrong UI revision')
 if nm.get('version_code')!=70 or nm.get('version_name')!='0.3.47' or nm.get('package')!='com.ontrack.voiceproof':raise ValueError('wrong native version')
 apk=(stage/'native/OnTrack-Voice-0.3.47.apk').read_bytes();html=(stage/'ui'/um['file']).read_bytes()
 if digest(apk)!=nm['sha256'] or len(apk)!=nm['size'] or nm['file']!='ontrack-voice-v70-'+digest(apk)+'.apk':raise ValueError('bad APK digest')
 if digest(html)!=um['sha256'] or len(html)!=um['size'] or um['file']!='ui-'+digest(html)+'.html':raise ValueError('bad HTML digest')
 php='/opt/cpanel/ea-php83/root/usr/bin/php'
 if not Path(php).is_file():raise ValueError('missing PHP83')
 for rel,data in site.items():
  dest=SITE[rel];base=WEB if rel.startswith('saas/console/') else APP
  if not dest.is_file() or dest.is_symlink() or not dest.resolve().is_relative_to(base.resolve()):raise ValueError('unsafe/missing '+str(dest))
  if rel.endswith('.php') and not rel.endswith('.blade.php'):
   with tempfile.NamedTemporaryFile(dir=PRIVATE,suffix='.php',delete=True) as tmp:
    tmp.write(data);tmp.flush();subprocess.run([php,'-l',tmp.name],check=True,timeout=20,stdout=subprocess.DEVNULL)
 uiroot=WEB/'mobile/updates';nroot=uiroot/'native'
 if not uiroot.is_dir() or not nroot.is_dir() or uiroot.is_symlink() or nroot.is_symlink():raise ValueError('missing OTA directory')
 ul=uiroot/'latest.json';nl=nroot/'latest.json'
 for path,key,blob in [(ul,'revision',ui),(nl,'version_code',native)]:
  if path.is_symlink() or not path.is_file():raise ValueError('missing manifest pointer')
  current=json.loads(json.loads(path.read_text())['payload']);version=current.get(key,0)
  if version>70 or (version==70 and path.read_bytes()!=blob):raise ValueError('OTA downgrade/reuse blocked')
 entries=list((SITE[rel],data) for rel,data in site.items())
 entries.extend([(uiroot/um['file'],html),(ul,ui),(nroot/nm['file'],apk),(nroot/'latest.apk',apk),(nl,native)])
 for target,data in entries:
  if target.is_symlink() or not target.parent.is_dir():raise ValueError('unsafe target '+str(target))
  if target.name in (um['file'],nm['file']) and target.exists() and digest(target.read_bytes())!=digest(data):raise ValueError('immutable content conflict')
 backup=PRIVATE/'v70-backups'/commit;backup.mkdir(parents=True,exist_ok=True,mode=0o700);os.chmod(backup,0o700)
 journal=[]
 with (PRIVATE/'deploy.lock').open('a') as lock:
  fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
  try:
   for i,(target,data) in enumerate(entries):
    old=target.read_bytes() if target.is_file() else None
    if old==data:continue
    if old is not None:(backup/('%02d-'%i+target.name)).write_bytes(old)
    journal.append((target,old));atomic(target,data)
    if digest(target.read_bytes())!=digest(data):raise ValueError('written SHA mismatch')
   subprocess.run([php,str(APP/'artisan'),'view:clear'],cwd=APP,check=True,timeout=30,stdout=subprocess.DEVNULL)
  except Exception:
   for target,old in reversed(journal):
    try:atomic(target,old) if old is not None else target.unlink(missing_ok=True)
    except Exception:pass
   raise
 print(json.dumps({'status':'SITE_UI_NATIVE_PUBLISHED','sha':commit,'site_files':len(SITE),'native_version':'0.3.47','native_version_code':70,'native_sha256':digest(apk),'ui_revision':70,'ui_sha256':digest(html),'rollback_dir':str(backup)}))
if __name__=='__main__':
 if len(sys.argv)==3 and sys.argv[1]=='package':package(Path(sys.argv[2]))
 elif len(sys.argv)==4 and sys.argv[1]=='deploy':deploy(Path(sys.argv[2]),sys.argv[3])
 else:raise SystemExit('usage: package ZIP | deploy STAGE COMMIT')
