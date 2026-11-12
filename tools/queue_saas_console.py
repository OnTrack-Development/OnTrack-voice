#!/usr/bin/env python3
"""Queue pinned no-secret SaaS console artifact to existing OnTrack worker."""
import base64,datetime,hashlib,json,os,pathlib,time,urllib.error,urllib.parse,urllib.request
REPO="OnTrack-Development/OnTrack-voice"
BASE="https://api.github.com/repos/"+REPO+"/"
BRANCH="ontrack-host-link"
TARGET="appontrack-server11"
QUEUE="ops/host-link/jobs/"+TARGET+".json"
def api(path,method="GET",data=None):
    hdr={"Authorization":"Bearer "+os.environ["GH_TOKEN"],"Accept":"application/vnd.github+json",
         "X-GitHub-Api-Version":"2022-11-28","User-Agent":"OnTrack-SaaS-Publisher"}
    req=urllib.request.Request(BASE+path,method=method,headers=hdr,
        data=json.dumps(data).encode() if data is not None else None)
    with urllib.request.urlopen(req,timeout=12) as r:return json.load(r)
def read(path):
    item=api("contents/"+urllib.parse.quote(path,safe="/")+"?ref="+BRANCH)
    return item,base64.b64decode(item["content"])
def await_previous():
    for attempt in range(30):
        current,raw=read(QUEUE)
        job=json.loads(raw)
        try:
            _,rec=read("ops/host-link/results/"+TARGET+"/"+job["id"]+".json")
            report=json.loads(rec)
            if (report.get("job_id")==job["id"] and
                report.get("status") in ("success","failed","timeout","expired","rejected") and
                report.get("job_sha256")==hashlib.sha256(raw).hexdigest()):
                return current,job
        except urllib.error.HTTPError as ex:
            if ex.code!=404:raise
        print("Waiting for host worker prior job:",job["id"],attempt+1,flush=True)
        time.sleep(12)
    raise RuntimeError("Host worker has unresolved previous job; refusing to overwrite")
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,url):return None
def artifact_url(artifact_id):
    req=urllib.request.Request(BASE+"actions/artifacts/"+str(artifact_id)+"/zip",
        headers={"Authorization":"Bearer "+os.environ["GH_TOKEN"],"User-Agent":"OnTrack-Console-Publisher"})
    try:urllib.request.build_opener(NoRedirect()).open(req,timeout=12)
    except urllib.error.HTTPError as err:
        if err.code not in (302,303):raise
        url=err.headers.get("Location","")
        domain=urllib.parse.urlsplit(url).hostname or ""
        if url.startswith("https://") and (
            domain.endswith(".blob.core.windows.net") or domain.endswith(".actions.githubusercontent.com")):
            return url
        raise RuntimeError("Artifact redirect domain rejected")
    raise RuntimeError("Expected GitHub artifact redirect")
def main():
    if os.environ["GITHUB_REF"]!="refs/heads/feat/voice-production-gap-20261103":
        raise ValueError("Voice 0.3.10 reviewed release branch only")
    folder=pathlib.Path("dist/saas-console")
    sha=hashlib.sha256((folder/"release.tar.gz").read_bytes()).hexdigest()
    assert sha==(folder/"release.sha256").read_text().strip()
    queue,prior=await_previous()
    job_id="saas-console-"+os.environ["GITHUB_RUN_ID"]
    if prior["id"]==job_id:
        print("Already queued",job_id);return
    artifacts=api("actions/runs/"+os.environ["GITHUB_RUN_ID"]+"/artifacts?per_page=100")["artifacts"]
    matches=[a for a in artifacts if a["name"]=="OnTrack-SaaS-Console" and not a["expired"]]
    if len(matches)!=1:raise RuntimeError("Missing uniquely named console archive")
    url=artifact_url(matches[0]["id"])
    installer=pathlib.Path("tools/deploy_saas_console.py").read_text()
    script=installer+"\nimport sys\ndeploy(*sys.argv[1:])\n"
    now=datetime.datetime.now(datetime.timezone.utc)
    job={"schema":1,"id":job_id,"target":TARGET,"action":"exec","created_at":now.isoformat(),
         "expires_at":(now+datetime.timedelta(hours=3)).isoformat(),
         "cwd":"/home/appontrack","timeout_seconds":30,
         "argv":["/bin/python3","-c",script,url,sha,os.environ["GITHUB_SHA"]]}
    body=json.dumps(job,indent=2).encode()
    if len(body)>16384:raise ValueError("Host job payload too large")
    api("contents/"+QUEUE,"PUT",{"branch":BRANCH,"sha":queue["sha"],
        "message":"deploy: OnTrack SaaS console "+os.environ["GITHUB_SHA"][:12],
        "content":base64.b64encode(body).decode()})
    print(json.dumps({"status":"queued","id":job_id,"sha256":sha,
                      "report":"ops/host-link/results/"+TARGET+"/"+job_id+".json"}))
if __name__=="__main__":main()
