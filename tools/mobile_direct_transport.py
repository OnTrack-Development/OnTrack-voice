#!/usr/bin/env python3
"""Deliver signed native APK and signed UI 45 without GitHub Actions artifact quota.

Release assets are PRIVATE intermediary files. The signed native and UI
manifests remain the trust anchors. The hosting worker receives only short-
lived GitHub CDN redirects, never repository tokens or signing credentials.
"""
import base64
import hashlib
import json
import os
import pathlib
import urllib.error
import urllib.parse
import urllib.request
import zipfile

REPO="OnTrack-Development/OnTrack-voice"
TAG="ontrack-native-v48"
PREFIX="https://api.github.com/repos/"+REPO+"/"
ASSETS=("OnTrack-Voice-0.3.25.apk","OnTrack-Voice-UI47.zip")
ALLOWED_CDN=("release-assets.githubusercontent.com","objects.githubusercontent.com")

def api(endpoint,method="GET",payload=None):
    body=None if payload is None else json.dumps(payload).encode("utf-8")
    request=urllib.request.Request(PREFIX+endpoint,data=body,method=method,headers={
        "Authorization":"Bearer "+os.environ["GH_TOKEN"],
        "Accept":"application/vnd.github+json",
        "X-GitHub-Api-Version":"2022-11-28",
        "User-Agent":"OnTrack-Signed-Android-Release-Transport"})
    with urllib.request.urlopen(request,timeout=20) as response:return json.load(response)

def _assert_source():
    source=os.environ["GITHUB_SHA"]
    assert os.environ.get("GITHUB_REF")=="refs/heads/feat/voice-production-gap-20261103"
    assert len(source)==40 and all(c in "0123456789abcdef" for c in source)
    assert os.environ.get("GITHUB_REPOSITORY")==REPO
    return source

def _release(create=False):
    try:r=api("releases/tags/"+TAG)
    except urllib.error.HTTPError as error:
        if error.code!=404 or not create:raise
        r=api("releases","POST",{
            "tag_name":TAG,"target_commitish":_assert_source(),
            "name":"OnTrack Voice 0.3.25 - signed APK and UI47",
            "body":"Private build transport. Only signed manifests and exact SHA256 assets are eligible for publication.",
            "draft":False,"prerelease":True,"make_latest":"false"})
    assert r["tag_name"]==TAG and not r["draft"]
    return r

def _archive(folder):
    name=folder/ASSETS[1]
    if name.exists():name.unlink()
    with zipfile.ZipFile(name,"w",compression=zipfile.ZIP_DEFLATED,compresslevel=8) as out:
        for p in sorted((folder/"ota").iterdir()):
            if p.is_file():out.write(p,"ota/"+p.name)
    with zipfile.ZipFile(name,"r") as z:
        assert "ota/latest.json" in z.namelist() and "ota/signing-cert.der" in z.namelist()
    return name

def publish(folder):
    _assert_source()
    folder=pathlib.Path(folder)
    import sys
    sys.path.insert(0,str(pathlib.Path(__file__).parent))
    import queue_mobile_native as native
    import queue_mobile_ota as ui
    signed=folder/ASSETS[0]
    _,_,native_meta=native.verify(folder/"native")
    _,_,ui_meta=ui.verify(folder/"ota")
    assert native_meta["version_code"]==48 and native_meta["version_name"]=="0.3.25"
    assert ui_meta["revision"]==47 and ui_meta["min_native"]<=48<=ui_meta["max_native"]
    assert signed.stat().st_size==native_meta["size"]
    assert hashlib.sha256(signed.read_bytes()).hexdigest()==native_meta["sha256"]
    bundle=_archive(folder)
    release=_release(create=True)
    results={}
    for path in (signed,bundle):
        name=path.name;size=path.stat().st_size;digest=hashlib.sha256(path.read_bytes()).hexdigest()
        existing=[x for x in release.get("assets",[]) if x["name"]==name]
        assert len(existing)<=1,"duplicate_existing_asset"
        if existing:
            asset=existing[0]
            assert asset["size"]==size and asset.get("digest")=="sha256:"+digest,"immutable_asset_conflict"
        else:
            url=release["upload_url"].split("{",1)[0]+"?"+urllib.parse.urlencode({"name":name})
            data=path.read_bytes()
            request=urllib.request.Request(url,data=data,method="POST",headers={
                "Authorization":"Bearer "+os.environ["GH_TOKEN"],
                "Accept":"application/vnd.github+json","Content-Type":
                "application/vnd.android.package-archive" if name.endswith(".apk") else "application/zip",
                "User-Agent":"OnTrack-Signed-Android-Release-Transport"})
            try:
                with urllib.request.urlopen(request,timeout=120) as response:asset=json.load(response)
            except urllib.error.HTTPError:
                latest=_release()
                found=[x for x in latest.get("assets",[]) if x["name"]==name]
                assert len(found)==1,"release_asset_not_confirmed"
                asset=found[0]
            assert asset["name"]==name and asset["size"]==size
            assert not asset.get("digest") or asset["digest"]=="sha256:"+digest
        results[name]={"sha256":digest,"bytes":size,"asset_id":asset["id"]}
    print(json.dumps({"status":"SIGNED_RELEASE_ASSETS_VERIFIED","tag":TAG,"assets":results}))

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):return None

def asset_url(name,expected_sha,expected_size):
    _assert_source()
    assert name in ASSETS
    release=_release()
    matching=[x for x in release.get("assets",[]) if x["name"]==name]
    assert len(matching)==1,"signed_release_asset_missing"
    asset=matching[0]
    assert asset["size"]==expected_size and asset.get("digest")=="sha256:"+expected_sha,"signed_asset_digest_mismatch"
    request=urllib.request.Request(PREFIX+"releases/assets/"+str(asset["id"]),headers={
        "Authorization":"Bearer "+os.environ["GH_TOKEN"],
        "Accept":"application/octet-stream",
        "X-GitHub-Api-Version":"2022-11-28","User-Agent":"OnTrack-OTA-Host-Publisher"})
    try:
        with urllib.request.build_opener(NoRedirect()).open(request,timeout=12) as response:
            raise RuntimeError("release_redirect_unavailable_HTTP_"+str(response.status))
    except urllib.error.HTTPError as e:
        if e.code not in (302,303):raise
        url=e.headers.get("Location","")
    host=urllib.parse.urlsplit(url)
    assert host.scheme=="https" and host.hostname and (
        host.hostname in ALLOWED_CDN or host.hostname.endswith(".blob.core.windows.net")
    ),"unexpected_release_cdn"
    # Prove CDN bytes before issuing a short-lived grant to the hosting worker.
    digest=hashlib.sha256();total=0
    with urllib.request.urlopen(urllib.request.Request(url,headers={"User-Agent":"OnTrack-OTA-Source-Proof"}),timeout=60) as response:
        assert response.status==200
        while True:
            chunk=response.read(262144)
            if not chunk:break
            total+=len(chunk)
            assert total<=expected_size
            digest.update(chunk)
    assert total==expected_size and digest.hexdigest()==expected_sha,"release_cdn_digest_mismatch"
    return url

if __name__=="__main__":
    import sys
    assert len(sys.argv)==3 and sys.argv[1]=="publish"
    publish(sys.argv[2])
