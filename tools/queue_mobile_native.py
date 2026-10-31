#!/usr/bin/env python3
"""Queue immutable native APK publication through existing one-job OnTrack host worker.
GitHub credential stays in Actions only. Worker receives a short-lived artifact
download URL, never GH_TOKEN or signing private key.
"""
import base64,datetime,hashlib,json,os,subprocess,sys,time,urllib.parse,urllib.request,urllib.error
from pathlib import Path

REPO="OnTrack-Development/OnTrack-voice"
BRANCH="ontrack-host-link"
TARGET="appontrack-server11"
QUEUE=f"ops/host-link/jobs/{TARGET}.json"
API=f"https://api.github.com/repos/{REPO}/"
EXPECTED_CERT="d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f"

def api(path,method="GET",data=None):
    request=urllib.request.Request(API+path,method=method,
        data=None if data is None else json.dumps(data).encode(),
        headers={"Authorization":"Bearer "+os.environ["GH_TOKEN"],"Accept":"application/vnd.github+json",
                 "User-Agent":"OnTrack-Native-OTA-Publisher","X-GitHub-Api-Version":"2022-11-28"})
    with urllib.request.urlopen(request,timeout=15) as r:return json.load(r)

def read(path):
    value=api("contents/"+path+"?ref="+BRANCH)
    return value,base64.b64decode(value["content"])

def verify(folder):
    cert=(folder/"signing-cert.der").read_bytes()
    assert hashlib.sha256(cert).hexdigest()==EXPECTED_CERT
    envelope=(folder/"latest.json").read_bytes()
    data=json.loads(envelope);payload=data["payload"];meta=json.loads(payload)
    assert meta["schema"]==1 and meta["channel"]=="stable" and meta["version_code"]==12
    assert meta["file"]==f"ontrack-voice-v{meta['version_code']}-{meta['sha256']}.apk"
    assert meta["package"]=="com.ontrack.voiceproof"
    with __import__("tempfile").TemporaryDirectory() as td:
        p=Path(td);(p/"cert.der").write_bytes(cert);(p/"payload").write_text(payload)
        (p/"sig").write_bytes(base64.b64decode(data["signature"],validate=True))
        (p/"pub").write_bytes(subprocess.check_output(["openssl","x509","-inform","DER","-in",str(p/"cert.der"),"-pubkey","-noout"]))
        subprocess.run(["openssl","dgst","-sha256","-verify",str(p/"pub"),"-signature",str(p/"sig"),str(p/"payload")],check=True,capture_output=True)
    return envelope,cert,meta

def wait_queue():
    for attempt in range(18):
        obj,raw=read(QUEUE);current=json.loads(raw)
        try:
            _,result=read(f"ops/host-link/results/{TARGET}/{current['id']}.json")
            report=json.loads(result)
            if (report["job_id"]==current["id"] and report["status"] in ("success","failed","expired","rejected","timeout")
                    and report["job_sha256"]==hashlib.sha256(raw).hexdigest()):
                return obj,current
        except urllib.error.HTTPError as e:
            if e.code!=404:raise
        print("Host worker busy; waiting for prior job",current["id"],attempt+1,flush=True)
        time.sleep(12)
    raise RuntimeError("Host worker did not finish previous job; native OTA not queued")

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):return None

def download_url(artifact_id):
    request=urllib.request.Request(API+f"actions/artifacts/{artifact_id}/zip",
        headers={"Authorization":"Bearer "+os.environ["GH_TOKEN"],"User-Agent":"OnTrack-Native-OTA"})
    try:urllib.request.build_opener(NoRedirect()).open(request,timeout=12)
    except urllib.error.HTTPError as e:
        if e.code not in (302,303):raise
        url=e.headers.get("Location")
        parsed=urllib.parse.urlsplit(url or "")
        assert parsed.scheme=="https" and parsed.hostname and (
            parsed.hostname.endswith(".blob.core.windows.net") or
            parsed.hostname.endswith(".actions.githubusercontent.com"))
        return url
    raise ValueError("Expected signed artifact download redirect")

def main():
    folder=Path(sys.argv[1]);envelope,cert,meta=verify(folder)
    assert os.environ.get("GITHUB_REF")=="refs/heads/main"
    obj,current=wait_queue()
    job_id="mobile-native-"+os.environ["GITHUB_RUN_ID"]
    if current["id"]==job_id:
        print(json.dumps({"status":"ALREADY_QUEUED","id":job_id}));return
    items=api("actions/runs/"+os.environ["GITHUB_RUN_ID"]+"/artifacts?per_page=100")["artifacts"]
    matches=[x for x in items if x["name"]=="OnTrack-Voice-0.2.1" and not x["expired"]]
    assert len(matches)==1,"Release artifact not found or duplicated"
    url=download_url(matches[0]["id"])
    script=(Path("saas/mobile/ota/native_deploy.py")).read_text()+"\nimport sys\ndeploy(*sys.argv[1:])\nROOT.chmod(0o755) # hosting worker has a private umask; public native release files need traversable directory\n"
    now=datetime.datetime.now(datetime.timezone.utc)
    task={"schema":1,"id":job_id,"target":TARGET,"action":"exec","created_at":now.isoformat(),
          "expires_at":(now+datetime.timedelta(hours=3)).isoformat(),
          "cwd":"/home/appontrack/public_html/saas","timeout_seconds":30,
          "argv":["/bin/python3","-c",script,url,base64.b64encode(envelope).decode(),base64.b64encode(cert).decode(),meta["sha256"]]}
    body=json.dumps(task,ensure_ascii=False,indent=2).encode()
    assert len(body)<=16384,f"host queue payload exceeds limit: {len(body)}"
    api("contents/"+QUEUE,"PUT",{"branch":BRANCH,"sha":obj["sha"],
        "message":f"deploy: signed OnTrack Android native update v{meta['version_code']}",
        "content":base64.b64encode(body).decode()})
    print(json.dumps({"status":"QUEUED_NOT_PUBLISHED","id":job_id,"bytes":len(body),
        "report":f"ops/host-link/results/{TARGET}/{job_id}.json"}))

if __name__=="__main__":main()
