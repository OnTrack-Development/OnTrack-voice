#!/usr/bin/env python3
"""Publish only verified, signed OnTrack v56 UI/Android OTA over authorized SSH.
Never accesses Keystore, Github token, any customer data, or stopped host-link.
Stage is not public. The signed UI and APK pointers are updated atomically
under the EXISTING private deploy.lock, after both payloads are validated.
"""
from pathlib import Path
import base64, fcntl, hashlib, json, os, subprocess, tempfile

PRIVATE=Path('/home/appontrack/.ontrack-mobile')
STAGE=PRIVATE/'native-v56'
WEB=Path('/home/appontrack/public_html')
UPDATES=WEB/'mobile/updates'
NATIVE=UPDATES/'native'
CERT='d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f'

def digest(value:bytes)->str:return hashlib.sha256(value).hexdigest()

def load_signed(folder:Path):
    manifest=folder/'latest.json'
    certificate=folder/'signing-cert.der'
    if manifest.is_symlink() or certificate.is_symlink():
        raise RuntimeError('unsafe_signed_manifest')
    raw=manifest.read_bytes()
    cert=certificate.read_bytes()
    if digest(cert)!=CERT:raise RuntimeError('wrong_original_android_signer')
    content=json.loads(raw)
    payload=content['payload']
    if not isinstance(payload,str) or len(payload)>12000:
        raise RuntimeError('signed_payload_invalid')
    signature=base64.b64decode(content['signature'],validate=True)
    meta=json.loads(payload)
    with tempfile.TemporaryDirectory(dir=str(PRIVATE)) as folder:
        temp=Path(folder)
        (temp/'certificate.der').write_bytes(cert)
        (temp/'payload').write_bytes(payload.encode('utf-8'))
        (temp/'signature').write_bytes(signature)
        public=subprocess.check_output(['openssl','x509','-inform','DER','-in',
            str(temp/'certificate.der'),'-pubkey','-noout'],timeout=5)
        (temp/'key.pem').write_bytes(public)
        subprocess.run(['openssl','dgst','-sha256','-verify',str(temp/'key.pem'),
            '-signature',str(temp/'signature'),str(temp/'payload')],
            check=True,stdout=subprocess.DEVNULL,timeout=5)
    return raw,meta

def atomic(path:Path,content:bytes,mode:int=0o644):
    fd,temp=tempfile.mkstemp(prefix='.v56-native-',dir=str(path.parent))
    try:
        with os.fdopen(fd,'wb') as file:
            file.write(content);file.flush();os.fsync(file.fileno())
        os.chmod(temp,mode);os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)

def check_directory(path:Path):
    if path.is_symlink() or not path.is_dir():raise RuntimeError('unsafe_host_dir_'+path.name)

def main():
    if os.getuid()!=2379:raise RuntimeError('wrong_host_user')
    for x in (PRIVATE,STAGE,WEB,UPDATES,NATIVE):check_directory(x)
    ui_bytes,ui=load_signed(STAGE/'ota')
    native_bytes,app=load_signed(STAGE/'native')
    if (ui['schema']!=1 or ui['channel']!='stable' or ui['runtime']!=1
        or ui['revision']!=56 or ui['min_native']>56 or ui['max_native']<56
        or app['schema']!=1 or app['channel']!='stable'
        or app['package']!='com.ontrack.voiceproof'
        or app['version_code']!=56 or app['version_name']!='0.3.33'):
        raise RuntimeError('native_v56_ui_56_manifest_contract_invalid')
    ui_filename=ui['file']
    apk_filename=app['file']
    if (ui_filename!='ui-'+ui['sha256']+'.html' or
        apk_filename!='ontrack-voice-v56-'+app['sha256']+'.apk'):
        raise RuntimeError('immutable_filename_invalid')
    html=STAGE/'ota'/ui_filename
    apk=STAGE/'OnTrack-Voice-0.3.33.apk'
    if any(x.is_symlink() for x in (html,apk)):
        raise RuntimeError('staged_payload_symlink')
    ui_file=html.read_bytes();apk_file=apk.read_bytes()
    if (len(ui_file)!=ui['size'] or digest(ui_file)!=ui['sha256']
        or len(apk_file)!=app['size'] or digest(apk_file)!=app['sha256']
        or not (1024<=len(apk_file)<=150*1024*1024)
        or not (1024<=len(ui_file)<=524288)):
        raise RuntimeError('signed_payload_sha_or_size_mismatch')
    if apk_file[:2]!=b'PK':raise RuntimeError('not_an_android_apk')
    # Byte-for-byte identical manifests for already published revisions are
    # idempotent. Never overwrite/downgrade other signed releases.
    with (PRIVATE/'deploy.lock').open('a') as handle:
        fcntl.flock(handle,fcntl.LOCK_EX|fcntl.LOCK_NB)
        current_ui=UPDATES/'latest.json';current_apk=NATIVE/'latest.json'
        for target,meta,raw,field in (
            (current_ui,ui,ui_bytes,'revision'),
            (current_apk,app,native_bytes,'version_code')):
            if target.is_symlink():raise RuntimeError('current_manifest_symlink')
            if target.exists():
                before=target.read_bytes()
                previous=json.loads(json.loads(before)['payload'])
                if previous[field]>meta[field]:raise RuntimeError('release_downgrade_blocked')
                if previous[field]==meta[field] and before!=raw:
                    raise RuntimeError('immutable_release_conflict')
        ui_dest=UPDATES/ui_filename
        apk_dest=NATIVE/apk_filename
        for target,payload in ((ui_dest,ui_file),(apk_dest,apk_file)):
            if target.is_symlink():raise RuntimeError('immutable_asset_symlink')
            if target.exists() and digest(target.read_bytes())!=digest(payload):
                raise RuntimeError('existing_immutable_asset_mismatch')
        backup=STAGE/'backup'
        backup.mkdir(mode=0o700,exist_ok=True)
        old_ui=current_ui.read_bytes() if current_ui.exists() else None
        old_native=current_apk.read_bytes() if current_apk.exists() else None
        if old_ui:atomic(backup/'ui-latest-previous.json',old_ui,0o600)
        if old_native:atomic(backup/'native-latest-previous.json',old_native,0o600)
        for dest,data in ((ui_dest,ui_file),(apk_dest,apk_file)):
            if not dest.exists():atomic(dest,data)
        try:
            atomic(UPDATES/'.htaccess',
                b'Options -Indexes\n<IfModule mod_headers.c>\n<Files "latest.json">\nHeader set Cache-Control "no-store"\n</Files>\n</IfModule>\n')
            atomic(NATIVE/'.htaccess',
                b'Options -Indexes\nAddType application/vnd.android.package-archive .apk\n<IfModule mod_headers.c>\n<Files "latest.json">\nHeader set Cache-Control "no-store"\n</Files>\n</IfModule>\n')
            atomic(current_ui,ui_bytes)
            atomic(current_apk,native_bytes)
        except BaseException:
            if old_ui is not None:atomic(current_ui,old_ui)
            elif current_ui.exists():current_ui.unlink()
            if old_native is not None:atomic(current_apk,old_native)
            elif current_apk.exists():current_apk.unlink()
            raise
    print('V68_ANDROID_0_3_33_SIGNED_NATIVE_56_AND_UI_56_DIRECT_PUBLISH_PASS',flush=True)
    print(json.dumps({'status':'published','version_code':56,'version_name':'0.3.33',
        'revision':56,'apk_sha256':app['sha256'],'apk_bytes':len(apk_file),
        'ui_sha256':ui['sha256'],'preserved_signer':True,
        'host_link_unchanged':True,'dialing_opt_in_required':True},
        separators=(',',':')),flush=True)

if __name__=='__main__':main()
