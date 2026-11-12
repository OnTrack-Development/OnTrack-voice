#!/usr/bin/env python3
"""Direct SSH delivery of original-signer signed OnTrack UI+APK v54.

Never replaces an existing version number with different bytes. Immovable
signed release files first, signed latest.json pointers last; backups kept
outside the webroot. No Host-Link, GitHub artifacts, passwords or root.
"""
import base64
import fcntl
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import time

HOME=Path('/home/appontrack')
ROOT=HOME/'public_html/mobile/updates'
NATIVE=ROOT/'native'
PRIVATE=HOME/'.ontrack-mobile'
STAGE=PRIVATE/'io-premium-v54-stage'
ORIGINAL_CERT='d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f'

def sha(data): return hashlib.sha256(data).hexdigest()

def atomic(path,data):
    fd,temp=tempfile.mkstemp(dir=str(path.parent),prefix='.ontrack-signed-v54-')
    try:
        with os.fdopen(fd,'wb') as file:
            file.write(data);file.flush();os.fsync(file.fileno())
        os.chmod(temp,0o644)
        os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)

def verify(scope):
    folder=STAGE/scope
    if not folder.is_dir() or folder.is_symlink():raise RuntimeError('untrusted_stage_'+scope)
    manifest=(folder/'latest.json').read_bytes()
    cert=(folder/'signing-cert.der').read_bytes()
    if sha(cert)!=ORIGINAL_CERT:raise RuntimeError('original_signing_certificate_changed')
    envelope=json.loads(manifest)
    signed_payload=envelope['payload'].encode('utf-8')
    signature=base64.b64decode(envelope['signature'],validate=True)
    meta=json.loads(signed_payload)
    with tempfile.TemporaryDirectory(dir=str(PRIVATE)) as temp:
        work=Path(temp)
        (work/'certificate.der').write_bytes(cert)
        (work/'payload').write_bytes(signed_payload)
        (work/'sig').write_bytes(signature)
        with (work/'public.pem').open('wb') as output:
            subprocess.run(['openssl','x509','-inform','DER','-in',str(work/'certificate.der'),'-pubkey','-noout'],check=True,stdout=output,stderr=subprocess.DEVNULL,timeout=6)
        subprocess.run(['openssl','dgst','-sha256','-verify',str(work/'public.pem'),'-signature',str(work/'sig'),str(work/'payload')],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,timeout=6)
    if meta.get('schema')!=1 or meta.get('channel')!='stable':raise RuntimeError('invalid_signed_channel_'+scope)
    if scope=='native':
        if meta.get('package')!='com.ontrack.voiceproof' or meta.get('version_code')!=54 or meta.get('version_name')!='0.3.31':
            raise RuntimeError('wrong_native_signed_release')
        path=folder/'OnTrack-Voice-0.3.31.apk'
        if meta.get('file')!='ontrack-voice-v54-'+meta.get('sha256','')+'.apk':raise RuntimeError('wrong_immutable_native_filename')
    else:
        if meta.get('runtime')!=1 or meta.get('revision')!=54 or meta.get('min_native')!=34 or meta.get('max_native')!=54:
            raise RuntimeError('wrong_signed_ui_release')
        if meta.get('file')!='ui-'+meta.get('sha256','')+'.html':raise RuntimeError('wrong_immutable_ui_filename')
        path=folder/meta['file']
    if path.is_symlink() or not path.is_file():raise RuntimeError('release_file_missing_'+scope)
    data=path.read_bytes()
    if len(data)!=meta.get('size') or sha(data)!=meta.get('sha256'):
        raise RuntimeError('release_bytes_invalid_'+scope)
    if scope=='native' and not 1024*1024<len(data)<30*1024*1024:
        raise RuntimeError('unexpected_native_bytes')
    return {'manifest':manifest,'meta':meta,'bytes':data}

def current(pointer):
    if pointer.is_symlink():raise RuntimeError('unsafe_manifest_pointer')
    if not pointer.is_file():return None,None
    data=pointer.read_bytes()
    return data,json.loads(json.loads(data)['payload'])

def main():
    if os.getuid()!=2379 or PRIVATE.is_symlink() or not PRIVATE.is_dir():
        raise RuntimeError('wrong_account')
    for p in (ROOT,NATIVE):
        if not p.is_dir() or p.is_symlink():raise RuntimeError('unsafe_ota_target')
    ui=verify('ui')
    native=verify('native')
    old_ui,old_ui_meta=current(ROOT/'latest.json')
    old_native,old_native_meta=current(NATIVE/'latest.json')
    if old_ui_meta:
        if int(old_ui_meta['revision'])>54 or (int(old_ui_meta['revision'])==54 and old_ui!=ui['manifest']):
            raise RuntimeError('existing_signed_ui_54_conflicts')
    if old_native_meta:
        if int(old_native_meta['version_code'])>54 or (int(old_native_meta['version_code'])==54 and old_native!=native['manifest']):
            raise RuntimeError('existing_native_54_conflicts')
    with (PRIVATE/'deploy.lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        # Recheck inside lock to protect against another writer.
        locked_ui,locked_ui_meta=current(ROOT/'latest.json')
        locked_native,locked_native_meta=current(NATIVE/'latest.json')
        if locked_ui!=old_ui or locked_native!=old_native:
            raise RuntimeError('ota_pointer_changed_during_staging')
        backup=PRIVATE/'io-premium-v54-backups'/str(int(time.time()))
        backup.mkdir(parents=True,mode=0o700)
        if old_ui is not None:(backup/'ui-latest.json').write_bytes(old_ui)
        if old_native is not None:(backup/'native-latest.json').write_bytes(old_native)
        changed_ui=False
        changed_native=False
        try:
            # Install immutable HTML and publish its signed pointer FIRST.
            for scope,target in ((ui,ROOT),(native,NATIVE)):
                meta=scope['meta']
                immutable=target/meta['file']
                if immutable.is_symlink():raise RuntimeError('unsafe_immutable_file')
                if immutable.exists():
                    if sha(immutable.read_bytes())!=meta['sha256']:
                        raise RuntimeError('immutable_file_sha_conflict')
                else:atomic(immutable,scope['bytes'])
                if sha(immutable.read_bytes())!=meta['sha256']:
                    raise RuntimeError('file_copy_integrity_failure')
                atomic(target/'latest.json',scope['manifest'])
                if target==ROOT:changed_ui=True
                else:changed_native=True
            if (ROOT/'latest.json').read_bytes()!=ui['manifest'] or (NATIVE/'latest.json').read_bytes()!=native['manifest']:
                raise RuntimeError('signed_ota_manifest_readback_failure')
            print(json.dumps({'status':'published','ui_revision':54,'native_version_code':54,'native_version_name':'0.3.31',
                'sha256':native['meta']['sha256'],'native_bytes':native['meta']['size'],
                'apk_file':native['meta']['file'],'backup_dir':str(backup),
                'host_link_used':False,'signer_fingerprint_verified':True},separators=(',',':')))
        except BaseException:
            if changed_native:
                if old_native is not None:atomic(NATIVE/'latest.json',old_native)
                else:(NATIVE/'latest.json').unlink(missing_ok=True)
            if changed_ui:
                if old_ui is not None:atomic(ROOT/'latest.json',old_ui)
                else:(ROOT/'latest.json').unlink(missing_ok=True)
            raise

if __name__=='__main__':main()
