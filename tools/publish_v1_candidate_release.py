#!/usr/bin/env python3
"""Upload one previously verified Android candidate to a private GitHub prerelease.

No host queue, OTA deployment, or client update occurs here.
"""
import hashlib
import json
import os
from pathlib import Path
import urllib.error
import urllib.parse
import urllib.request

REPO = "OnTrack-Development/OnTrack-voice"
RUN_ID = os.environ.get("CANDIDATE_BUILD_RUN_ID", os.environ["GITHUB_RUN_ID"])
SOURCE_SHA = os.environ.get("CANDIDATE_SOURCE_SHA", os.environ["GITHUB_SHA"])
TAG = "ontrack-v1-candidate-build-" + RUN_ID
NAME = "OnTrack-Voice-0.3.25.apk"
API = f"https://api.github.com/repos/{REPO}"

def request(url, method="GET", obj=None, raw=None, content_type="application/json"):
    body = json.dumps(obj).encode("utf-8") if obj is not None else raw
    req = urllib.request.Request(url, data=body, method=method, headers={
        "Authorization": "Bearer " + os.environ["GH_TOKEN"],
        "Accept": "application/vnd.github+json",
        "X-GitHub-Api-Version": "2022-11-28",
        "User-Agent": "OnTrack-private-candidate-transport",
        "Content-Type": content_type,
    })
    with urllib.request.urlopen(req, timeout=150) as resp:
        return json.load(resp)

def digest(path):
    hash_value = hashlib.sha256()
    with path.open("rb") as source:
        for chunk in iter(lambda: source.read(1 << 20), b""):
            hash_value.update(chunk)
    return hash_value.hexdigest()

def main():
    assert os.environ["GITHUB_REPOSITORY"] == REPO
    assert os.environ["GITHUB_REF"] == "refs/heads/feat/voice-v1-minimal-console-20261106"
    folder = Path(os.environ["GITHUB_WORKSPACE"]) / ".build" / "release"
    apk = folder / NAME
    report = folder / "size-report.json"
    checksum = folder / "SHA256SUMS.txt"
    provenance = folder / "ontrack-build-source.json"
    for required in (apk, report, checksum, provenance):
        if not required.is_file():
            raise RuntimeError(f"previous signed build was not retained: {required.name}")
    details = json.loads(report.read_text(encoding="utf-8-sig"))
    assert str(details["source_build_run"]) == RUN_ID, "candidate_run_mismatch"
    assert details["release"] == "0.3.25", "version_mismatch"
    assert details["signing_cert_verified"] is True, "signing_unverified"
    assert details["minified_release_bytes"] == apk.stat().st_size, "size_mismatch"
    assert 100000 < apk.stat().st_size <= 15728640, "apk_size_guard"
    sha = digest(apk)
    assert sha == details["sha256"], "apk_hash_mismatch"
    assert checksum.read_text(encoding="ascii").strip() == f"SHA256 {sha}  {NAME}", "checksum_mismatch"
    # The signed build metadata and immutable source SHA are kept alongside the APK.
    evidence = {"candidate_build_run": RUN_ID, "source_sha": SOURCE_SHA,
                "apk_sha256": sha, "apk_size": apk.stat().st_size,
                "version": "0.3.25", "version_code": 48,
                "candidate_only": True, "host_deployed": False,
                "physical_call_test_passed": False}
    record = folder / "CANDIDATE-RECEIPT.json"
    record.write_text(json.dumps(evidence, indent=2) + "\n", encoding="utf-8")
    try:
        release = request(f"{API}/releases/tags/{TAG}")
    except urllib.error.HTTPError as e:
        if e.code != 404:
            raise
        release = request(f"{API}/releases", method="POST", obj={
            "tag_name": TAG,
            "target_commitish": SOURCE_SHA,
            "name": f"OnTrack Voice internal signed candidate - run {RUN_ID}",
            "body": "Build-only APK (0.3.25 / code 48); NOT the complete v1.0.0 release. Not deployed. Requires device and security verification.",
            "draft": False,
            "prerelease": True,
            "make_latest": "false",
        })
    assert release["tag_name"] == TAG and release["prerelease"] is True
    existing = {asset["name"]: asset for asset in release.get("assets", [])}
    results = {}
    for path in (apk, checksum, report, provenance, record):
        expected_hash = digest(path)
        if path.name in existing:
            item = existing[path.name]
            assert item["size"] == path.stat().st_size, "existing_asset_size_mismatch"
            if item.get("digest"):
                assert item["digest"] == "sha256:" + expected_hash, "existing_asset_hash_mismatch"
        else:
            url = release["upload_url"].split("{", 1)[0] + "?" + urllib.parse.urlencode({"name": path.name})
            data = path.read_bytes()
            mime = "application/vnd.android.package-archive" if path.suffix == ".apk" else "application/octet-stream"
            item = request(url, method="POST", raw=data, content_type=mime)
            assert item["name"] == path.name and item["size"] == len(data)
            if item.get("digest"):
                assert item["digest"] == "sha256:" + expected_hash, "uploaded_asset_hash_mismatch"
        results[path.name] = {"sha256": expected_hash, "bytes": path.stat().st_size}
    print(json.dumps({"status":"PRIVATE_CANDIDATE_RELEASE_VERIFIED",
                      "tag": TAG, "url": release["html_url"], "assets": results}))

if __name__ == "__main__":
    main()
