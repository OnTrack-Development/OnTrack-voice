#!/usr/bin/env python3
"""Check host worker success for an exact SaaS artifact and release run. No secrets in logs."""
import base64
import hashlib
import json
import os
import time
import urllib.error
import urllib.request

REPO="OnTrack-Development/OnTrack-voice"
TARGET="appontrack-server11"
BRANCH="ontrack-host-link"
BASE="https://api.github.com/repos/"+REPO+"/"
RUN=os.environ["GITHUB_RUN_ID"]
EXPECTED="saas-console-"+RUN
HEADERS={
    "Authorization":"Bearer "+os.environ["GH_TOKEN"],
    "Accept":"application/vnd.github+json",
    "User-Agent":"OnTrack-Owner-SaaS-Receipt",
}
def get(path):
    request=urllib.request.Request(BASE+"contents/"+path+"?ref="+BRANCH,headers=HEADERS)
    with urllib.request.urlopen(request,timeout=12) as r:obj=json.load(r)
    return base64.b64decode(obj["content"])
for attempt in range(50):
    raw=get("ops/host-link/jobs/"+TARGET+".json")
    job=json.loads(raw)
    if job.get("id")!=EXPECTED:
        raise SystemExit("SaaS_HOST_QUEUE_MISMATCH: expected job no longer active")
    try:
        receipt=get("ops/host-link/results/"+TARGET+"/"+EXPECTED+".json")
    except urllib.error.HTTPError as e:
        if e.code!=404:raise
        print("SaaS_HOST_ACK_PENDING",attempt+1,flush=True)
        time.sleep(12)
        continue
    report=json.loads(receipt)
    if report.get("job_id")!=EXPECTED or report.get("target")!=TARGET or report.get("job_sha256")!=hashlib.sha256(raw).hexdigest():
        raise SystemExit("SaaS_HOST_RECEIPT_INTEGRITY_FAILED")
    if report.get("status")!="success" or report.get("exit_code")!=0:
        raise SystemExit("SaaS_HOST_DEPLOY_FAILED:"+str(report.get("status")))
    info=json.loads(report.get("stdout") or "{}")
    if info.get("result")!="deployed" or info.get("source")!=os.environ["GITHUB_SHA"][:12] or info.get("php")!="8.3":
        raise SystemExit("SaaS_HOST_DEPLOY_PAYLOAD_MISMATCH")
    print("SAAS_CONSOLE_HOST_DEPLOYED: PASS",json.dumps({
        "source":info["source"],"files":info.get("files"),"rewritten":info.get("rewritten")
    }))
    break
else:
    raise SystemExit("SaaS_HOST_DEPLOY_TIMEOUT")
