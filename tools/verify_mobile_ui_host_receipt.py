#!/usr/bin/env python3
"""Verify the ontrack-host-link worker actually installed a signed UI OTA."""
import hashlib
import json
import os
import sys
import time
import urllib.error
from pathlib import Path

sys.path.insert(0,str(Path(__file__).resolve().parent))
import queue_mobile_ota as publisher

folder=Path(sys.argv[1])
_,_,meta=publisher.verify(folder)
expected='mobile-ota-'+os.environ['GITHUB_RUN_ID']
for retry in range(60):
    _,queued=publisher.content('ops/host-link/jobs/'+publisher.TARGET+'.json')
    job=json.loads(queued)
    if job.get('id')!=expected:
        raise SystemExit('UI_HOST_QUEUE_CHANGED: refusing unverifiable publication')
    try:
        _,receipt=publisher.content('ops/host-link/results/'+publisher.TARGET+'/'+expected+'.json')
    except urllib.error.HTTPError as ex:
        if ex.code!=404: raise
        print('UI_HOST_ACK_PENDING',retry+1,flush=True)
        time.sleep(12)
        continue
    r=json.loads(receipt)
    assert r.get('target')==publisher.TARGET and r.get('job_id')==expected,'UI host identity mismatch'
    assert r.get('job_sha256')==hashlib.sha256(queued).hexdigest(),'UI host job hash mismatch'
    assert r.get('status')=='success' and r.get('exit_code')==0,'UI host deploy failed'
    data=json.loads(r.get('stdout','{}'))
    assert data.get('status')=='installed' and data.get('revision')==meta['revision'] and data.get('sha256')==meta['sha256'],'UI host revision/hash mismatch'
    print('ONTRACK_UI_OTA_SERVER_DEPLOYED: PASS',meta['revision'],meta['sha256'])
    break
else:
    raise SystemExit('UI_HOST_ACK_TIMEOUT: not claiming deployment')
