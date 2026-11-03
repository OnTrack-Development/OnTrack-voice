#!/usr/bin/env python3
"""Prepare a signed native update manifest for the exact built Android APK."""
import hashlib,json,shutil,sys
from pathlib import Path

source=Path(sys.argv[1])
output=Path(sys.argv[2]);output.mkdir(parents=True,exist_ok=True)
config=json.loads(Path("saas/mobile/ota/native_release.json").read_text())
data=source.read_bytes()
assert isinstance(config.get("version_name"),str) and __import__("re").fullmatch(r"0\\.[0-9]+\\.[0-9]+(?:-[a-z0-9-]+)?",config["version_name"])
assert source.name==f"OnTrack-Voice-{config['version_name']}.apk", "Refusing APK not matching signed native release version"
assert 1024<len(data)<=150*1024*1024
assert config["version_code"]>=15 and config["min_installed_version"]<config["version_code"]
hash=hashlib.sha256(data).hexdigest()
config.update({"sha256":hash,"size":len(data),"file":f"ontrack-voice-v{config['version_code']}-{hash}.apk"})
(output/"payload.json").write_text(json.dumps(config,sort_keys=True,separators=(',',':')))
print("Signed native APK manifest prepared",config["version_code"],hash)
