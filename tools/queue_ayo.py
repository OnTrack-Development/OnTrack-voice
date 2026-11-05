#!/usr/bin/env python3
"""Queue isolated AYO demo release via existing OnTrack GitHub host-link worker."""
import base64
import datetime
import hashlib
import json
import os
import pathlib
import time
import urllib.error
import urllib.parse
import urllib.request

REPO='OnTrack-Development/OnTrack-voice'
BASE='https://api.github.com/repos/'+REPO+'/'
BRANCH='ontrack-host-link'
TARGET='appontrack-server11'
QUEUE='ops/host-link/jobs/'+TARGET+'.json'


def api(path,method='GET',payload=None):
    headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],
             'Accept':'application/vnd.github+json',
             'User-Agent':'OnTrack-AYO-Publisher'}
    req=urllib.request.Request(BASE+path,headers=headers,method=method,
            data=json.dumps(payload).encode() if payload is not None else None)
    with urllib.request.urlopen(req,timeout=12) as response:
        return json.load(response)


def read(path):
    r=api('contents/'+urllib.parse.quote(path,safe='/')+'?ref='+BRANCH)
    return r,base64.b64decode(r['content'])


def prior_done():
    for n in range(30):
        entry,raw=read(QUEUE)
        prev=json.loads(raw)
        try:
            _,receipt=read('ops/host-link/results/'+TARGET+'/'+prev['id']+'.json')
            result=json.loads(receipt)
            if result.get('job_id')==prev['id'] and result.get('job_sha256')==hashlib.sha256(raw).hexdigest() and result.get('status') in ('success','failed','expired','rejected','timeout'):
                return entry,prev
        except urllib.error.HTTPError as ex:
            if ex.code != 404:
                raise
        print('Host queue busy',prev['id'],n,flush=True)
        time.sleep(10)
    raise RuntimeError('Host queue occupied; did not overwrite')


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,url):
        return None


def signed_artifact(id_):
    req=urllib.request.Request(BASE+'actions/artifacts/'+str(id_)+'/zip',headers={'Authorization':'Bearer '+os.environ['GH_TOKEN']})
    try:
        urllib.request.build_opener(NoRedirect()).open(req,timeout=12)
    except urllib.error.HTTPError as e:
        if e.code not in (302,303):
            raise
        url=e.headers.get('Location','')
        host=urllib.parse.urlsplit(url).hostname or ''
        if url.startswith('https://') and (host.endswith('.blob.core.windows.net') or host.endswith('.actions.githubusercontent.com')):
            return url
    raise RuntimeError('No trusted artifact URL')


def main():
    if os.environ['GITHUB_REF']!='refs/heads/feat/ayo-php-host-demo-20261105':
        raise RuntimeError('Unexpected deployment branch')
    folder=pathlib.Path('dist/ayo')
    digest=hashlib.sha256((folder/'release.tar.gz').read_bytes()).hexdigest()
    if digest!=(folder/'release.sha256').read_text().strip():
        raise RuntimeError('Artifact mismatch')
    queue,old=prior_done()
    id_='ayo-php-'+os.environ['GITHUB_RUN_ID']
    if old['id']==id_:
        print('Already queued');return
    arts=api('actions/runs/'+os.environ['GITHUB_RUN_ID']+'/artifacts?per_page=100')['artifacts']
    candidates=[a for a in arts if a['name']=='OnTrack-AYO-PHP' and not a['expired']]
    if len(candidates)!=1:
        raise RuntimeError('Expected single AYO artifact')
    artifact=signed_artifact(candidates[0]['id'])
    src=pathlib.Path('tools/deploy_ayo.py').read_text()
    script=src+'\nimport sys\ndeploy(*sys.argv[1:])\n'
    now=datetime.datetime.now(datetime.timezone.utc)
    job={'schema':1,'id':id_,'target':TARGET,'action':'exec','created_at':now.isoformat(),
         'expires_at':(now+datetime.timedelta(hours=2)).isoformat(),
         'cwd':'/home/appontrack/public_html','timeout_seconds':30,
         'argv':['/bin/python3','-c',script,artifact,digest,os.environ['GITHUB_SHA']]}
    body=json.dumps(job,indent=2).encode()
    if len(body)>16000:
        raise RuntimeError('Queue request too large')
    api('contents/'+QUEUE,'PUT',{'branch':BRANCH,'sha':queue['sha'],
        'message':'deploy: isolated AYO PHP '+os.environ['GITHUB_SHA'][:12],
        'content':base64.b64encode(body).decode()})
    print(json.dumps({'queued':True,'id':id_,'sha256':digest}))

if __name__=='__main__':main()
