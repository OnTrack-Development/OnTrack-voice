#!/usr/bin/env python3
"""Read-only preflight on appontrack host. Never print credentials or PII."""
import json,os,pathlib,subprocess
home=pathlib.Path('/home/appontrack')
root=home/'public_html'
names=[
 '_core/Bootstrap.php','_core/TenantVoiceSession.php','_core/DevicePairing.php',
 '_core/PublishedFlow.php','_core/VoiceCatalog.php',
 'device/v1/index.php','device/v1/tts.php','saas/console/device/v1/index.php',
 'saas/console/device/v1/tts.php','mobile/updates/latest.json',
 'mobile/updates/native/latest.json','saas/console/.htaccess','.htaccess',
]
v={}
for name in names:
 p=root/name
 v[name]={'exists':p.exists(),'is_symlink':p.is_symlink(),'real_path':str(p.resolve()) if p.exists() else None}
for pointer in ('mobile/updates/latest.json','mobile/updates/native/latest.json'):
 p=root/pointer
 if p.is_file() and not p.is_symlink():
  try:
   meta=json.loads(json.loads(p.read_text())['payload'])
   v[pointer]['revision']=meta.get('revision')
   v[pointer]['version_code']=meta.get('version_code')
   v[pointer]['sha256']=meta.get('sha256')
   v[pointer]['version_name']=meta.get('version_name')
   v[pointer]['size']=meta.get('size')
   v[pointer]['file']=meta.get('file')
   v[pointer]['source_commit']=meta.get('source_commit')
   v[pointer]['min_native']=meta.get('min_native')
   v[pointer]['max_native']=meta.get('max_native')
   if 'native' in pointer and meta.get('file'):
    import hashlib
    apk=root/'mobile/updates/native'/meta['file']
    v[pointer]['apk_exists']=apk.is_file()
    v[pointer]['apk_sha_matches']=hashlib.sha256(apk.read_bytes()).hexdigest()==meta['sha256'] if apk.is_file() and not apk.is_symlink() and apk.stat().st_size<30*1024*1024 else False
  except Exception:v[pointer]['manifest_valid']=False
for name in ('_core/Bootstrap.php','_core/TenantVoiceSession.php','device/v1/index.php'):
 p=root/name
 if p.is_file() and not p.is_symlink():
  s=p.read_text(errors='replace')
  v[name]['io_premium_already_deployed']='io.premium' in s
  if name.endswith('Bootstrap.php'):
   v[name]['schema_v27_file']=(home/'.ontrack-saas'/'schema.v27').exists()
fs=home/'.ontrack-saas'/'voice-lab'/'owner-1.json'
if fs.is_file() and not fs.is_symlink():
 j=json.loads(fs.read_text())
 v['fish_config']={'present':True,'has_key':bool(j.get('keys',{}).get('fish')),'has_voice_id':bool(j.get('settings',{}).get('voiceId')),'model':j.get('settings',{}).get('fishModel')}
else:v['fish_config']={'present':False}
try:
 r=subprocess.run(['crontab','-l'],capture_output=True,text=True,timeout=4)
 v['host_link_stop_exists']=(home/'ontrack-voice-link'/'STOP').exists()
except Exception:v['cron_check_error']=True
v['uid']=os.getuid()
v['root_writable']=os.access(root,os.W_OK)
v['native_writable']=os.access(root/'mobile/updates/native',os.W_OK)
v['php']=subprocess.run(['php','-v'],capture_output=True,text=True,timeout=5).stdout.splitlines()[0]
print(json.dumps(v,ensure_ascii=False,separators=(',',':')))
