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
    assert meta["schema"]==1 and meta["channel"]=="stable" and meta["version_code"]==34
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
                    and report["job_sha256"]==hashlib.sha256(raw).hexdigest()
                    and report.get("target")==TARGET):
                return obj,current,report
        except urllib.error.HTTPError as e:
            if e.code!=404:raise
        print("Host worker busy; waiting for prior job",current["id"],attempt+1,flush=True)
        time.sleep(12)
    raise RuntimeError("Host worker did not finish previous job; native OTA not queued")

def require_successful_ui_release(current, report, run_id, ui_envelope):
    """Never publish native APK when its paired UI OTA failed on the real host.

    A green Actions job means only that the UI job was QUEUED. The host receipt
    is authoritative; require the same run, target, exit status, revision and
    signed-artifact hash before any native publication is queued.
    """
    # A signed UI-only release may already be installed with the identical HTML
    # hash and revision. Reuse its exact host receipt to avoid a same-revision
    # manifest conflict; never trust an arbitrary or unverified UI rollout.
    ui_run=os.environ.get("MOBILE_UI_ALREADY_PUBLISHED_RUN_ID",str(run_id))
    if not ui_run.isdecimal() or int(ui_run)<=0:
        raise RuntimeError("UI_OTA_PROOF_SOURCE_INVALID")
    if current.get("id") != "mobile-ota-" + ui_run:
        raise RuntimeError("UI_OTA_HOST_ACK_MISSING: exact installed UI job required")
    if report.get("job_id") != current["id"] or report.get("target") != TARGET:
        raise RuntimeError("UI_OTA_HOST_ACK_MISMATCH")
    if report.get("status") != "success" or report.get("exit_code") != 0:
        raise RuntimeError("UI_OTA_HOST_DEPLOY_FAILED: native release blocked")
    try:
        expected = json.loads(ui_envelope["payload"])
        receipt = json.loads(report["stdout"])
        if (receipt.get("status") != "installed"
                or receipt.get("scope") != "mobile/updates"
                or type(receipt.get("revision")) is not int
                or receipt["revision"] != expected["revision"]
                or receipt.get("sha256") != expected["sha256"]):
            raise ValueError("receipt metadata mismatch")
    except (KeyError, ValueError, TypeError, json.JSONDecodeError) as exc:
        raise RuntimeError("UI_OTA_HOST_PROOF_INVALID: native release blocked") from exc


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
    assert os.environ.get("GITHUB_REF")=="refs/heads/feat/voice-production-gap-20261103"
    obj,current,report=wait_queue()
    ui_manifest=folder.parent/"ota"/"latest.json"
    if not ui_manifest.is_file():
        raise RuntimeError("UI_OTA_SIGNED_ARTIFACT_MISSING: native release blocked")
    require_successful_ui_release(current,report,os.environ["GITHUB_RUN_ID"],json.loads(ui_manifest.read_text()))
    job_id="mobile-native-"+os.environ["GITHUB_RUN_ID"]
    if current["id"]==job_id:
        print(json.dumps({"status":"ALREADY_QUEUED","id":job_id}));return
    source_run=os.environ.get("MOBILE_RELEASE_SOURCE_RUN_ID",os.environ["GITHUB_RUN_ID"])
    assert source_run.isdecimal() and int(source_run)>0,"Invalid pinned release source run"
    items=api("actions/runs/"+source_run+"/artifacts?per_page=100")["artifacts"]
    matches=[x for x in items if x["name"]=="OnTrack-Voice-0.3.11" and not x["expired"]]
    assert len(matches)==1,"Release artifact not found or duplicated"
    url=download_url(matches[0]["id"])
    script=(Path("saas/mobile/ota/native_deploy.py")).read_text()+"\nimport sys\ndeploy(*sys.argv[1:])\nROOT.chmod(0o755) # hosting worker has a private umask; public native release files need traversable directory\n"
    now=datetime.datetime.now(datetime.timezone.utc)
    task={"schema":1,"id":job_id,"target":TARGET,"action":"exec","created_at":now.isoformat(),
          "expires_at":(now+datetime.timedelta(hours=3)).isoformat(),
          "cwd":"/home/appontrack/public_html","timeout_seconds":30,
          "argv":["/bin/python3","-c",script,url,base64.b64encode(envelope).decode(),base64.b64encode(cert).decode(),meta["sha256"]]}
    body=json.dumps(task,ensure_ascii=False,indent=2).encode()
    assert len(body)<=16384,f"host queue payload exceeds limit: {len(body)}"
    api("contents/"+QUEUE,"PUT",{"branch":BRANCH,"sha":obj["sha"],
        "message":f"deploy: signed OnTrack Android native update v{meta['version_code']}",
        "content":base64.b64encode(body).decode()})
    print(json.dumps({"status":"QUEUED_NOT_PUBLISHED","id":job_id,"bytes":len(body),
        "report":f"ops/host-link/results/{TARGET}/{job_id}.json"}))

if __name__=="__main__":main()
