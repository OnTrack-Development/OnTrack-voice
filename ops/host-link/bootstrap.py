#!/usr/bin/env python3
import base64, getpass, hashlib, json, os, urllib.request
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None
ref = "08363de8125d454e5df3180cab86f0113c947ba6"
token = getpass.getpass("GitHub token (hidden): ")
url = "https://api.github.com/repos/OnTrack-Development/OnTrack-voice/contents/ops/host-link/install.py?ref=" + ref
req = urllib.request.Request(url, headers={"Authorization": "Bearer " + token, "User-Agent": "OnTrack-Host-Link"})
with urllib.request.build_opener(NoRedirect()).open(req, timeout=20) as response:
    code = base64.b64decode(json.load(response)["content"])
if hashlib.sha256(code).hexdigest() != "1d507b26ddf9c508312df0ba35f6c05a851dbba53d88a24b4b8ebc3507845556":
    raise SystemExit("Installer checksum mismatch")
os.environ["ONTRACK_HOST_TOKEN"] = token
os.environ["ONTRACK_HOST_SOURCE_REF"] = ref
exec(compile(code, "ontrack-host-install", "exec"), {"__name__": "__main__"})
