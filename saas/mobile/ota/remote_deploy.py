"""Publish an immutable signed OTA UI from an Actions artifact on the authorized host.
The URL is a short-lived GitHub Actions blob redirect. No GitHub token on host.
"""
import base64,hashlib,io,json,subprocess,tempfile,urllib.parse,urllib.request,zipfile,lzma
from pathlib import Path

PINNED_CERT="d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f"

def remote_install(url, expected_ui_sha, expected_revision):
    parsed=urllib.parse.urlsplit(url)
    if parsed.scheme!="https" or not parsed.hostname or not (
        parsed.hostname.endswith(".blob.core.windows.net") or
        parsed.hostname.endswith(".actions.githubusercontent.com")
    ):raise ValueError("Untrusted artifact host")
    req=urllib.request.Request(url,headers={"User-Agent":"OnTrack-Mobile-OTA-Host"})
    with urllib.request.urlopen(req,timeout=20) as response:
        limit=30*1024*1024
        data=response.read(limit+1)
        if len(data)>limit:raise ValueError("Artifact too large")
    with zipfile.ZipFile(io.BytesIO(data),"r") as archive:
        infos={i.filename:i for i in archive.infolist()}
        for name in infos:
            if name.startswith("/") or ".." in Path(name).parts:raise ValueError("Unsafe archive")
        for needed in ("ota/latest.json","ota/signing-cert.der"):
            if needed not in infos:raise ValueError("Missing signed OTA artifact member")
        cert=archive.read("ota/signing-cert.der")
        if hashlib.sha256(cert).hexdigest()!=PINNED_CERT:raise ValueError("Untrusted OTA certificate")
        manifest=archive.read("ota/latest.json").decode("utf-8")
        envelope=json.loads(manifest);meta=json.loads(envelope["payload"])
        if str(meta["revision"])!=str(expected_revision) or meta["sha256"]!=expected_ui_sha:raise ValueError("Wrong release")
        name="ota/"+meta["file"]
        if name not in infos or infos[name].file_size>524288:raise ValueError("Missing OTA file")
        html=archive.read(name)
        if len(html)!=meta["size"] or hashlib.sha256(html).hexdigest()!=expected_ui_sha:raise ValueError("OTA hash mismatch")
    with tempfile.TemporaryDirectory() as temp:
        t=Path(temp)
        (t/"cert").write_bytes(cert)
        (t/"payload").write_bytes(envelope["payload"].encode())
        (t/"signature").write_bytes(base64.b64decode(envelope["signature"],validate=True))
        (t/"public").write_bytes(subprocess.check_output(["openssl","x509","-inform","DER","-in",str(t/"cert"),"-pubkey","-noout"],timeout=5))
        subprocess.run(["openssl","dgst","-sha256","-verify",str(t/"public"),"-signature",str(t/"signature"),str(t/"payload")],check=True,capture_output=True,timeout=5)
    bundle=json.dumps({"manifest":manifest,"html":html.decode("utf-8")},sort_keys=True,separators=(",",":")).encode()
    packed=base64.b64encode(lzma.compress(bundle,preset=9)).decode()
    deploy(packed,hashlib.sha256(bundle).hexdigest())
