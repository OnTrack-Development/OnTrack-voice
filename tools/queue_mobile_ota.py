#!/usr/bin/env python3
"""After a verified build, publish a bounded job to the EXISTING host worker.
No host token/credentials, queue replacement, direct SSH or process daemon.
"""
import base64,datetime,hashlib,json,lzma,os,subprocess,sys,tempfile,urllib.request,urllib.error,zlib
from pathlib import Path
REPO='OnTrack-Development/OnTrack-voice';BRANCH='ontrack-host-link';TARGET='appontrack-server11'
CERT='d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f'
def canonical(d):return json.dumps(d,sort_keys=True,separators=(',',':')).encode()
def request(path,method='GET',data=None):
    req=urllib.request.Request('https://api.github.com/repos/'+REPO+'/'+path,method=method,data=None if data is None else json.dumps(data).encode(),headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json','User-Agent':'OnTrack-OTA-Publisher','X-GitHub-Api-Version':'2022-11-28'})
    with urllib.request.urlopen(req,timeout=15) as res:return json.load(res)
def content(path):
    obj=request('contents/'+path+'?ref='+BRANCH);return obj,base64.b64decode(obj['content'])
def verify(folder):
    cert=(folder/'signing-cert.der').read_bytes();assert hashlib.sha256(cert).hexdigest()==CERT,'Unexpected OTA signer'
    manifest=(folder/'latest.json').read_text();envelope=json.loads(manifest);meta=json.loads(envelope['payload'])
    assert meta['file']=='ui-'+meta['sha256']+'.html'
    html=(folder/meta['file']).read_bytes();assert hashlib.sha256(html).hexdigest()==meta['sha256'] and len(html)==meta['size']
    with tempfile.TemporaryDirectory() as tmp:
        t=Path(tmp);(t/'payload').write_text(envelope['payload']);(t/'signature').write_bytes(base64.b64decode(envelope['signature'],validate=True))
        pub=subprocess.check_output(['openssl','x509','-inform','DER','-in',str(folder/'signing-cert.der'),'-pubkey','-noout']);(t/'public.pem').write_bytes(pub)
        subprocess.run(['openssl','dgst','-sha256','-verify',str(t/'public.pem'),'-signature',str(t/'signature'),str(t/'payload')],check=True,capture_output=True)
    return manifest,html.decode(),meta

def main():
    manifest,html,meta=verify(Path(sys.argv[1]));assert os.environ.get('GITHUB_REF')=='refs/heads/main'
    queue_path='ops/host-link/jobs/'+TARGET+'.json'
    obj,raw=content(queue_path);current=json.loads(raw)
    if current.get('id')=='mobile-ota-'+os.environ['GITHUB_RUN_ID']:
        print(json.dumps({'status':'ALREADY_QUEUED_UNCHANGED','job_id':current['id']}));return
    try:
        _,result=content('ops/host-link/results/'+TARGET+'/'+current['id']+'.json')
    except urllib.error.HTTPError as error:
        if error.code!=404:raise
        # Missing report does NOT prove the queued action failed. Never overwrite it:
        # the worker may have run it and failed only while uploading the receipt.
        raise RuntimeError('HOST_LINK_BLOCKED: no report for '+current['id']+
            '; previous job preserved, restart/recover host worker before OTA publish') from error
    report=json.loads(result)
    assert report['job_id']==current['id'] and report['target']==TARGET and report['status'] in ('success','failed','expired','rejected','timeout'), 'Host queue busy/unknown'
    assert report['job_sha256']==hashlib.sha256(raw).hexdigest(),'Host queue result mismatch'
    bundle=canonical({'manifest':manifest,'html':html});packed=base64.b64encode(lzma.compress(bundle,format=lzma.FORMAT_XZ,preset=9)).decode()
    source=(Path('saas/mobile/ota/deploy.py')).read_text()+'\nimport sys\ndeploy(sys.argv[1],sys.argv[2])\n'
    compact=base64.b64encode(zlib.compress(source.encode(),9)).decode()
    script="import base64,zlib;exec(zlib.decompress(base64.b64decode('"+compact+"')))"
    now=datetime.datetime.now(datetime.timezone.utc);job={'schema':1,'id':'mobile-ota-'+os.environ['GITHUB_RUN_ID'],'target':TARGET,'action':'exec','created_at':now.isoformat(),'expires_at':(now+datetime.timedelta(hours=24)).isoformat(),'cwd':'/home/appontrack/public_html/saas','timeout_seconds':25,'argv':['/bin/python3','-c',script,packed,hashlib.sha256(bundle).hexdigest()]}
    encoded=json.dumps(job,ensure_ascii=False,indent=2).encode()
    if len(encoded)>16384:
        # Upload transport is not a product dependency: fetch exact signed artifact from
        # the completed build via short-lived GitHub Actions blob redirect on the host.
        from queue_mobile_native import download_url
        source_run=os.environ.get('MOBILE_RELEASE_SOURCE_RUN_ID',os.environ['GITHUB_RUN_ID'])
        assert source_run.isdecimal() and int(source_run)>0, 'Invalid pinned Android release run'
        artifact=request('actions/runs/'+source_run+'/artifacts?per_page=100')['artifacts']
        artifact_name=os.environ.get('MOBILE_OTA_ARTIFACT_NAME','OnTrack-Voice-0.3.5')
        assert artifact_name in ('OnTrack-Voice-0.3.8','OnTrack-Voice-0.3.9','OnTrack-Voice-UI-OTA'), 'Unexpected OTA artifact name'
        matches=[x for x in artifact if x['name']==artifact_name and not x['expired']]
        assert len(matches)==1,'Signed Android artifact missing or ambiguous'
        url=download_url(matches[0]['id'])
        program=(Path('saas/mobile/ota/deploy.py')).read_text()+'\n'+(Path('saas/mobile/ota/remote_deploy.py')).read_text()+'\nimport sys\nremote_install(sys.argv[1],sys.argv[2],sys.argv[3])\n'
        code=base64.b64encode(zlib.compress(program.encode(),9)).decode()
        script="import base64,zlib;exec(zlib.decompress(base64.b64decode('"+code+"')))"
        job['timeout_seconds']=30
        job['argv']=['/bin/python3','-c',script,url,meta['sha256'],str(meta['revision'])]
        encoded=json.dumps(job,ensure_ascii=False,indent=2).encode()
    assert len(encoded)<=16384,f'Host job exceeds installed worker limit ({len(encoded)} bytes)'
    request('contents/'+queue_path,'PUT',{'branch':BRANCH,'sha':obj['sha'],'message':'deploy: signed mobile OTA revision'+str(meta['revision']),'content':base64.b64encode(encoded).decode()})
    print(json.dumps({'status':'QUEUED_NOT_DEPLOYED','job_id':job['id'],'revision':meta['revision'],'bytes':len(encoded),'result_path':'ops/host-link/results/'+TARGET+'/'+job['id']+'.json'}))
if __name__=='__main__':main()
