#!/usr/bin/env python3
"""Build-verified V1 site and native host publisher. Receipts gate success."""
import base64,datetime,hashlib,json,os,pathlib,subprocess,sys,tarfile,time,urllib.error,urllib.parse,urllib.request,zipfile,zlib
REPO="OnTrack-Development/OnTrack-voice"
RUN_SOURCE=os.environ.get("ONTRACK_BUILD_RUN",os.environ["GITHUB_RUN_ID"])
SHA_SOURCE=os.environ.get("ONTRACK_BUILD_SHA",os.environ["GITHUB_SHA"])
APK="OnTrack-Voice-0.3.26.apk"
APK_SHA=None
CERT="d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f"
RELEASE_TAG="ontrack-v1-verified-build-"+RUN_SOURCE
API="https://api.github.com/repos/"+REPO+"/"
HOST_REF="ontrack-host-link"
TARGET="appontrack-server11"
QUEUE="ops/host-link/jobs/"+TARGET+".json"
RESULT="ops/host-link/results/"+TARGET+"/"
OUT=pathlib.Path(".build/v1-one-shot")
RUN=RUN_SOURCE
GH=os.environ["GH_TOKEN"]
assert os.environ["GITHUB_REPOSITORY"]==REPO and os.environ["GITHUB_REF"]=="refs/heads/release/voice-v1-20261106"

def sha(x):return hashlib.sha256(x).hexdigest()
def call(path,method="GET",payload=None):
    raw=None if payload is None else json.dumps(payload).encode()
    req=urllib.request.Request(API+path,method=method,data=raw,headers={
        "Authorization":"Bearer "+GH,"Accept":"application/vnd.github+json",
        "X-GitHub-Api-Version":"2022-11-28","User-Agent":"OnTrack-v1-publisher","Content-Type":"application/json"})
    with urllib.request.urlopen(req,timeout=35) as response:
        b=response.read()
        return json.loads(b) if b else {}
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):return None

def release():
    try:
        d=call("releases/tags/"+RELEASE_TAG)
    except urllib.error.HTTPError as error:
        if error.code!=404:raise
        d=call("releases","POST",{"tag_name":RELEASE_TAG,
            "target_commitish":SHA_SOURCE,
            "name":"OnTrack Voice 0.3.26 signed host delivery",
            "body":"Internal verified Android release; host receipt required before any live claim.",
            "draft":False,"prerelease":True,"make_latest":"false"})
    assert d["tag_name"]==RELEASE_TAG and d["prerelease"] and not d["draft"]
    return d

def asset(name, expected, size, file=None):
    rel=release()
    present=[x for x in rel["assets"] if x["name"]==name]
    assert len(present)<=1
    if present:
        x=present[0]
        assert x.get("digest")=="sha256:"+expected and x["size"]==size,"asset mismatch"
    else:
        assert file is not None,"asset missing"
        data=pathlib.Path(file).read_bytes()
        assert sha(data)==expected and len(data)==size
        dest=rel["upload_url"].split("{",1)[0]+"?"+urllib.parse.urlencode({"name":name})
        request=urllib.request.Request(dest,method="POST",data=data,headers={
            "Authorization":"Bearer "+GH,"Accept":"application/vnd.github+json",
            "Content-Type":"application/zip","User-Agent":"OnTrack-v1-publisher"})
        try:
            with urllib.request.urlopen(request,timeout=150) as response:x=json.load(response)
        except urllib.error.HTTPError:
            found=[a for a in release()["assets"] if a["name"]==name]
            assert len(found)==1
            x=found[0]
        assert x["size"]==size and (not x.get("digest") or x["digest"]=="sha256:"+expected)
    request=urllib.request.Request(API+"releases/assets/"+str(x["id"]),headers={
        "Authorization":"Bearer "+GH,"Accept":"application/octet-stream",
        "X-GitHub-Api-Version":"2022-11-28","User-Agent":"OnTrack-v1-source"})
    try:
        with urllib.request.build_opener(NoRedirect()).open(request,timeout=20) as response:
            raise RuntimeError("private asset redirect unavailable")
    except urllib.error.HTTPError as e:
        if e.code not in (302,303):raise
        url=e.headers.get("Location","")
    p=urllib.parse.urlsplit(url)
    assert p.scheme=="https" and p.hostname and (
        p.hostname in ("release-assets.githubusercontent.com","objects.githubusercontent.com")
        or p.hostname.endswith(".blob.core.windows.net")),"untrusted GitHub CDN"
    req=urllib.request.Request(url,headers={"User-Agent":"OnTrack-v1-CDN-check"})
    with urllib.request.urlopen(req,timeout=120) as response: data=response.read(size+1)
    assert len(data)==size and sha(data)==expected,"private asset digest failed"
    return url,data

def read(path):
    item=call("contents/"+urllib.parse.quote(path,safe="/")+"?ref="+HOST_REF)
    return item,base64.b64decode(item["content"])

def receipt(jobid,jobbytes):
    try:
        _,content=read(RESULT+jobid+".json")
    except urllib.error.HTTPError as error:
        if error.code==404:return None
        raise
    d=json.loads(content)
    assert d.get("job_id")==jobid and d.get("target")==TARGET
    assert d.get("job_sha256")==sha(jobbytes),"receipt job sha mismatch"
    return d

def host_job(kind,cwd,program,args,check):
    jobid="v1-"+kind+"-"+RUN
    now=datetime.datetime.now(datetime.timezone.utc)
    job={"schema":1,"id":jobid,"target":TARGET,"action":"exec",
         "created_at":now.isoformat(),"expires_at":(now+datetime.timedelta(hours=2)).isoformat(),
         "cwd":cwd,"timeout_seconds":30,"argv":["/bin/python3","-c",program,*args]}
    payload=json.dumps(job,ensure_ascii=False,indent=2).encode()
    assert len(payload)<16384,"job too large"
    for count in range(36):
        old,raw=read(QUEUE)
        previous=json.loads(raw)
        result=receipt(previous["id"],raw)
        if previous["id"]==jobid:
            assert result and result["status"]=="success" and result["exit_code"]==0
            output=json.loads(result.get("stdout") or "{}")
            assert check(output)
            print("HOST_VERIFIED_EXISTING",kind,flush=True)
            return
        if result is None:
            print("HOST_BUSY",kind,count,flush=True)
            time.sleep(10)
            continue
        assert result["status"] in ("success","failed","expired","timeout","rejected")
        try:call("contents/"+QUEUE,"PUT",{
            "branch":HOST_REF,"sha":old["sha"],"message":"deploy: pinned v1 "+kind,
            "content":base64.b64encode(payload).decode()})
        except urllib.error.HTTPError as error:
            if error.code<500:raise
            _,posted=read(QUEUE)
            assert posted==payload,"ambiguous queue update"
        _,posted=read(QUEUE)
        assert posted==payload,"host queue readback differs"
        print("QUEUED_ONE",kind,jobid,flush=True)
        for poll in range(60):
            status=receipt(jobid,payload)
            if not status:
                time.sleep(10)
                continue
            if status["status"]!="success" or status["exit_code"]!=0:
                raise RuntimeError("HOST_"+kind+"_FAILED: "+str(status.get("stderr",""))[-700:])
            output=json.loads(status.get("stdout") or "{}")
            assert check(output),"host "+kind+" output mismatch"
            print("HOST_DEPLOY_VERIFIED",kind,json.dumps(output,ensure_ascii=False),flush=True)
            return
        raise RuntimeError("HOST_"+kind+"_ACK_TIMEOUT")
    raise RuntimeError("HOST_QUEUE_BLOCKED")

def program(files,main):
    body="\n".join(pathlib.Path(p).read_text(encoding="utf-8") for p in files)
    return inline_program(body,main)
def inline_program(body,main):
    data=base64.b64encode(zlib.compress((body+"\nimport sys\n"+main+"\n").encode(),9)).decode()
    return "import base64,zlib;exec(zlib.decompress(base64.b64decode('"+data+"')))"

def live(path):
    try:
        req=urllib.request.Request("https://app.ontrackegy.com/"+path,headers={
            "Cache-Control":"no-cache","Pragma":"no-cache","User-Agent":"OnTrack-v1-check"})
        with urllib.request.urlopen(req,timeout=15) as response:
            assert response.status==200
            return json.loads(response.read(32768))
    except (urllib.error.URLError,ValueError,OSError):
        return None

def prepare():
    global APK_SHA
    apk_source=pathlib.Path(".build/release")/APK
    data=apk_source.read_bytes()
    APK_SHA=sha(data)
    build_report=json.loads((pathlib.Path(".build/release")/"size-report.json").read_text(encoding="utf-8-sig"))
    assert 100000<len(data)<=15728640
    assert build_report["sha256"]==APK_SHA and build_report["release"]=="0.3.26"
    assert str(build_report["source_build_run"])==RUN_SOURCE and build_report["signing_cert_verified"] is True
    OUT.mkdir(parents=True,exist_ok=True)
    _,downloaded=asset(APK,APK_SHA,len(data),apk_source)
    assert downloaded==data
    apk_file=OUT/APK
    apk_file.write_bytes(data)
    sdk=pathlib.Path(os.environ["LOCALAPPDATA"])/"Android"/"Sdk"
    signer=sdk/"build-tools"/"36.0.0"/"apksigner.bat"
    analyzer=sdk/"cmdline-tools"/"latest"/"bin"/"apkanalyzer.bat"
    checks=subprocess.check_output([str(signer),"verify","--print-certs",str(apk_file)],text=True).lower()
    assert CERT in checks,"APK signing cert mismatch"
    subprocess.run([str(signer),"verify","--verbose",str(apk_file)],check=True)
    assert subprocess.check_output([str(analyzer),"manifest","version-code",str(apk_file)],text=True).strip()=="49"
    key=pathlib.Path.home()/".android"/"debug.keystore"
    assert key.is_file()
    exported=OUT/"signing-cert.der"
    subprocess.run(["keytool","-exportcert","-alias","androiddebugkey","-keystore",str(key),"-storepass","android","-file",str(exported)],check=True)
    assert sha(exported.read_bytes())==CERT
    git_ssl=pathlib.Path("C:/Program Files/Git/usr/bin")
    assert (git_ssl/"openssl.exe").is_file()
    os.environ["PATH"]=str(git_ssl)+os.pathsep+os.environ["PATH"]
    os.environ["GITHUB_SHA"]=SHA_SOURCE
    sys.path.insert(0,str(pathlib.Path("tools").resolve()))
    import queue_mobile_ota,queue_mobile_native
    folder=OUT/"artifact"
    folder.mkdir(exist_ok=True)
    (folder/APK).write_bytes(data)
    subprocess.run(["python","tools/prepare_mobile_ota.py",str(folder/"ota")],check=True)
    prior=live("mobile/updates/latest.json")
    if prior:
        old=json.loads(prior["payload"])
        meta=json.loads((folder/"ota"/"payload.json").read_text())
        if old["revision"]>=meta["revision"]:
            meta["revision"]=old["revision"]+1
            assert meta["revision"]<10000
            (folder/"ota"/"payload.json").write_text(json.dumps(meta,sort_keys=True,separators=(",",":")))
            print("SAFE_UI_REVISION",meta["revision"],flush=True)
    subprocess.run(["java","tools/SignMobileOta.java",str(key),str(folder/"ota")],check=True)
    subprocess.run(["python","tools/prepare_mobile_native.py",str(folder/APK),str(folder/"native")],check=True)
    subprocess.run(["java","tools/SignMobileOta.java",str(key),str(folder/"native")],check=True)
    _,_,ui=queue_mobile_ota.verify(folder/"ota")
    _,_,native=queue_mobile_native.verify(folder/"native")
    assert native["sha256"]==APK_SHA and native["version_code"]==49 and native["size"]==len(data)
    assert ui["min_native"]<=49<=ui["max_native"]
    prev=live("mobile/updates/native/latest.json")
    if prev:
        old=json.loads(prev["payload"])
        assert old["version_code"]<=49 and (old["version_code"]!=49 or old["sha256"]==APK_SHA),"native version conflict"
    # Package only the reviewed SaaS console, identical to the existing host publisher archive.
    tarfile_path=OUT/"release.tar.gz"
    with tarfile.open(tarfile_path,"w:gz",format=tarfile.USTAR_FORMAT) as tf:
        tf.add("saas/console",arcname="console",recursive=True)
    compressed=tarfile_path.read_bytes()
    assert len(compressed)<=4*1024*1024
    with tarfile.open(tarfile_path,"r:gz") as tf:
        members=tf.getmembers()
        assert len(members)<=250
        assert all(not (m.issym() or m.islnk()) and m.size<1024*1024 for m in members)
        assert all(not any(x in (".env",".git",".ontrack-saas","secrets") for x in pathlib.PurePosixPath(m.name).parts) for m in members)
    webzip=OUT/("OnTrack-V1-SaaS-"+SHA_SOURCE[:12]+".zip")
    with zipfile.ZipFile(webzip,"w",zipfile.ZIP_DEFLATED,compresslevel=7) as z:z.write(tarfile_path,"release.tar.gz")
    uizip=OUT/("OnTrack-V1-UI-"+SHA_SOURCE[:12]+".zip")
    with zipfile.ZipFile(uizip,"w",zipfile.ZIP_DEFLATED,compresslevel=7) as z:
        for p in sorted((folder/"ota").iterdir()):
            if p.is_file():z.write(p,"ota/"+p.name)
    print("APK_REUSE_AND_SIGNED_UI_PREPARED",json.dumps({"apk_sha":APK_SHA,"ui_revision":ui["revision"],"site_sha":sha(compressed)}),flush=True)

def publish():
    global APK_SHA
    APK_SHA=sha((OUT/APK).read_bytes())
    sys.path.insert(0,str(pathlib.Path("tools").resolve()))
    import queue_mobile_ota,queue_mobile_native
    folder=OUT/"artifact"
    apk=OUT/APK
    assert sha(apk.read_bytes())==APK_SHA
    ui_envelope,_,ui=queue_mobile_ota.verify(folder/"ota")
    native_envelope,cert,native=queue_mobile_native.verify(folder/"native")
    assert native["sha256"]==APK_SHA
    webzip=OUT/("OnTrack-V1-SaaS-"+SHA_SOURCE[:12]+".zip")
    uizip=OUT/("OnTrack-V1-UI-"+SHA_SOURCE[:12]+".zip")
    web_url,_=asset(webzip.name,sha(webzip.read_bytes()),webzip.stat().st_size,webzip)
    ui_url,_=asset(uizip.name,sha(uizip.read_bytes()),uizip.stat().st_size,uizip)
    apk_url,_=asset(APK,APK_SHA,apk.stat().st_size)
    original=pathlib.Path("tools/deploy_saas_console.py").read_text(encoding="utf-8")
    a="(u.hostname.endswith('.blob.core.windows.net') or u.hostname.endswith('.actions.githubusercontent.com'))"
    b="(u.hostname.endswith('.blob.core.windows.net') or u.hostname.endswith('.actions.githubusercontent.com') or u.hostname in ('release-assets.githubusercontent.com','objects.githubusercontent.com'))"
    assert original.count(a)==1
    install=inline_program(original.replace(a,b),"deploy(*sys.argv[1:])")
    tar_sha=sha((OUT/"release.tar.gz").read_bytes())
    host_job("saas","/home/appontrack",install,[web_url,tar_sha,SHA_SOURCE],
             lambda x:x.get("result")=="deployed" and x.get("source")==SHA_SOURCE[:12] and x.get("android_compat") is True)
    if live("mobile/updates/latest.json")==json.loads(ui_envelope):
        print("SIGNED_UI_ALREADY_PUBLIC",flush=True)
    else:
        ui_prog=program(["saas/mobile/ota/deploy.py","saas/mobile/ota/remote_deploy.py"],"remote_install(*sys.argv[1:])")
        host_job("ui","/home/appontrack/public_html/saas",ui_prog,[ui_url,ui["sha256"],str(ui["revision"])],
                 lambda x:x.get("status")=="installed" and x.get("sha256")==ui["sha256"] and x.get("revision")==ui["revision"])
    if live("mobile/updates/native/latest.json")==json.loads(native_envelope):
        print("SIGNED_APK_ALREADY_PUBLIC",flush=True)
    else:
        native_prog=program(["saas/mobile/ota/native_deploy.py"],"deploy(*sys.argv[1:]);ROOT.chmod(0o755)")
        host_job("apk","/home/appontrack/public_html",native_prog,
                 [apk_url,base64.b64encode(native_envelope).decode(),base64.b64encode(cert).decode(),APK_SHA],
                 lambda x:x.get("status")=="published" and x.get("sha256")==APK_SHA and x.get("version")==49)
    assert live("mobile/updates/latest.json")==json.loads(ui_envelope),"public UI manifest mismatch"
    assert live("mobile/updates/native/latest.json")==json.loads(native_envelope),"public native manifest mismatch"
    print("SITE_AND_APK_PUBLISHED_AND_VERIFIED",json.dumps({
        "site":"https://app.ontrackegy.com/","apk":"https://app.ontrackegy.com/mobile/updates/native/"+native["file"],
        "sha256":APK_SHA,"revision":ui["revision"]}),flush=True)


def resume_native():
    """Resume ONLY the signed native APK delivery from preserved successful run."""
    global APK_SHA
    root=pathlib.Path("D:/ai/OnTrack-Voice-Builds/v1-candidates")/RUN_SOURCE
    apk=root/APK
    report=json.loads((root/"size-report.json").read_text(encoding="utf-8-sig"))
    data=apk.read_bytes()
    APK_SHA=sha(data)
    assert 100000<len(data)<=15728640
    assert report["sha256"]==APK_SHA and report["release"]=="0.3.26"
    assert str(report["source_build_run"])==RUN_SOURCE and report["signing_cert_verified"] is True
    sdk=pathlib.Path(os.environ["LOCALAPPDATA"])/"Android"/"Sdk"
    signer=sdk/"build-tools"/"36.0.0"/"apksigner.bat"
    analyzer=sdk/"cmdline-tools"/"latest"/"bin"/"apkanalyzer.bat"
    sig=subprocess.check_output([str(signer),"verify","--print-certs",str(apk)],text=True).lower()
    assert CERT in sig,"preserved APK certificate mismatch"
    subprocess.run([str(signer),"verify","--verbose",str(apk)],check=True)
    assert subprocess.check_output([str(analyzer),"manifest","version-code",str(apk)],text=True).strip()=="49"
    assert subprocess.check_output([str(analyzer),"manifest","application-id",str(apk)],text=True).strip()=="com.ontrack.voiceproof"
    key=pathlib.Path.home()/".android"/"debug.keystore"
    assert key.is_file()
    apk_url,downloaded=asset(APK,APK_SHA,len(data))
    assert downloaded==data,"release asset differs from signed candidate"
    OUT.mkdir(parents=True,exist_ok=True)
    folder=OUT/"resume-native"
    subprocess.run(["python","tools/prepare_mobile_native.py",str(apk),str(folder)],check=True)
    subprocess.run(["java","tools/SignMobileOta.java",str(key),str(folder)],check=True)
    sys.path.insert(0,str(pathlib.Path("tools").resolve()))
    import queue_mobile_native
    native_envelope,cert,native=queue_mobile_native.verify(folder)
    assert native["sha256"]==APK_SHA and native["version_code"]==49 and native["size"]==len(data)
    existing=live("mobile/updates/native/latest.json")
    if existing==json.loads(native_envelope):
        print("SIGNED_APK_ALREADY_PUBLIC",flush=True)
    else:
        assert existing is None or json.loads(existing["payload"])["version_code"]<=49
        native_prog=program(["saas/mobile/ota/native_deploy.py"],"deploy(*sys.argv[1:]);ROOT.chmod(0o755)")
        host_job("apk","/home/appontrack/public_html",native_prog,
                 [apk_url,base64.b64encode(native_envelope).decode(),base64.b64encode(cert).decode(),APK_SHA],
                 lambda x:x.get("status")=="published" and x.get("sha256")==APK_SHA and x.get("version")==49)
    assert live("mobile/updates/native/latest.json")==json.loads(native_envelope),"native live manifest mismatch"
    ui=live("mobile/updates/latest.json")
    assert ui and json.loads(ui["payload"])["revision"]>=48,"signed UI missing"
    with urllib.request.urlopen("https://app.ontrackegy.com/",timeout=15) as page:
        assert page.status==200,"SaaS site not reachable"
    print("SITE_AND_APK_PUBLISHED_AND_VERIFIED",json.dumps({
        "site":"https://app.ontrackegy.com/",
        "apk":"https://app.ontrackegy.com/mobile/updates/native/"+native["file"],
        "sha256":APK_SHA,"ui_revision":json.loads(ui["payload"])["revision"]}),flush=True)


def publish_site_only():
    """Publish ONLY the SaaS console. Preserve signed Android APK and UI OTA verbatim."""
    native_before=live("mobile/updates/native/latest.json")
    ui_before=live("mobile/updates/latest.json")
    assert native_before is not None and ui_before is not None,"cannot confirm existing mobile pointers"
    OUT.mkdir(parents=True,exist_ok=True)
    tarfile_path=OUT/"site-release.tar.gz"
    with tarfile.open(tarfile_path,"w:gz",format=tarfile.USTAR_FORMAT) as tf:
        tf.add("saas/console",arcname="console",recursive=True)
    compressed=tarfile_path.read_bytes()
    assert len(compressed)<=4*1024*1024
    with tarfile.open(tarfile_path,"r:gz") as tf:
        members=tf.getmembers()
        assert len(members)<=250
        assert all((m.isdir() or m.isfile()) and not (m.issym() or m.islnk()) and m.size<1024*1024 for m in members)
        assert all(not any(x in (".env",".git",".ontrack-saas","secrets") for x in pathlib.PurePosixPath(m.name).parts) for m in members)
    webzip=OUT/("OnTrack-V1-SiteOnly-"+SHA_SOURCE[:12]+".zip")
    with zipfile.ZipFile(webzip,"w",zipfile.ZIP_DEFLATED,compresslevel=7) as z:
        z.write(tarfile_path,"release.tar.gz")
    url,remote=asset(webzip.name,sha(webzip.read_bytes()),webzip.stat().st_size,webzip)
    assert remote==webzip.read_bytes()
    original=pathlib.Path("tools/deploy_saas_console.py").read_text(encoding="utf-8")
    before="(u.hostname.endswith('.blob.core.windows.net') or u.hostname.endswith('.actions.githubusercontent.com'))"
    after="(u.hostname.endswith('.blob.core.windows.net') or u.hostname.endswith('.actions.githubusercontent.com') or u.hostname in ('release-assets.githubusercontent.com','objects.githubusercontent.com'))"
    assert original.count(before)==1
    reviewed=original.replace(before,after)
    preflight=inline_program(reviewed,"site_preflight(*sys.argv[1:])")
    host_job("siteqa","/home/appontrack",preflight,[url,sha(compressed),SHA_SOURCE],
        lambda obj:obj.get("result")=="preflight_pass" and obj.get("source")==SHA_SOURCE[:12] and obj.get("isolated_database") is True)
    script=inline_program(reviewed,"deploy(*sys.argv[1:])")
    host_job("saas","/home/appontrack",script,[url,sha(compressed),SHA_SOURCE],
        lambda obj:obj.get("result")=="deployed" and obj.get("source")==SHA_SOURCE[:12] and obj.get("android_compat") is True)
    assert live("mobile/updates/latest.json")==ui_before,"UI OTA pointer changed"
    assert live("mobile/updates/native/latest.json")==native_before,"native APK pointer changed"
    for route in ("","login","register"):
        with urllib.request.urlopen("https://app.ontrackegy.com/"+route,timeout=15) as page:
            assert page.status==200,"site route unavailable: "+route
    # SITE-21: independently probe the LIVE control planes. These deliberately
    # use no credentials. A login page returning 200 is not proof that private
    # APIs reject unpaired devices and unscoped integration clients.
    closed_routes=[
        ("GET","https://app.ontrackegy.com/api/v1/?op=sites",None,
         "api_key_required"),
        ("POST","https://app.ontrackegy.com/device/v1/",
         json.dumps({"operation":"engine_manifest","abi":"arm64-v8a"}).encode(),
         "paired_sim_required"),
    ]
    for method,target,payload,label in closed_routes:
        request=urllib.request.Request(target,data=payload,method=method,
            headers={"User-Agent":"OnTrack-Site21-Acceptance",
                     "Accept":"application/json",
                     "Content-Type":"application/json"})
        try:
            with urllib.request.urlopen(request,timeout=15) as resp:
                status=resp.status
        except urllib.error.HTTPError as failure:
            status=failure.code
        assert status==401,"SITE21 fail-closed check failed: "+label+" "+str(status)
    print("SITE21_HTTPS_NO_BEARER_DENIED_PASS",json.dumps({
        "routes_checked":2,"expected_unauthorized":401,
        "production_auth_ui":"unverified_without_owner_login"
    }),flush=True)
    print("SITE_ONLY_DEPLOY_VERIFIED",json.dumps({
        "site":"https://app.ontrackegy.com/","sha":SHA_SOURCE,
        "signed_mobile_unchanged":True,"source_archive_sha256":sha(compressed)}),flush=True)

if __name__=="__main__":
    assert len(sys.argv)==2 and sys.argv[1] in ("prepare","publish","resume-native","site-only")
    if sys.argv[1]=="prepare":prepare()
    elif sys.argv[1]=="resume-native":resume_native()
    elif sys.argv[1]=="site-only":publish_site_only()
    else:publish()
