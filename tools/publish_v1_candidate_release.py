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
import sys

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
    try:
        with urllib.request.urlopen(req, timeout=150) as resp:
            data = resp.read()
            return json.loads(data) if data else {}
    except urllib.error.HTTPError as error:
        detail = error.read(4096).decode("utf-8", errors="replace")
        path = urllib.parse.urlsplit(url).path
        print(f"GITHUB_API_FAILED method={method} endpoint={path} status={error.code} detail={detail}", file=sys.stderr)
        raise

def digest(path):
    hash_value = hashlib.sha256()
    with path.open("rb") as source:
        for chunk in iter(lambda: source.read(1 << 20), b""):
            hash_value.update(chunk)
    return hash_value.hexdigest()

def destination():
    try:
        release = request(f"{API}/releases/tags/{TAG}")
        assert release["prerelease"] and not release["draft"], "candidate_release_disabled"
        return release, False
    except urllib.error.HTTPError as error:
        if error.code != 404:
            raise
    try:
        release = request(f"{API}/releases", method="POST", obj={
            "tag_name": TAG,
            "target_commitish": SOURCE_SHA,
            "name": f"OnTrack Voice internal candidate (build {RUN_ID})",
            "body": "TEST BUILD ONLY; not v1.0.0, no host deployment or device call verification.",
            "draft": False,
            "prerelease": True,
            "make_latest": "false",
        })
        assert release["tag_name"] == TAG
        return release, False
    except urllib.error.HTTPError as error:
        if error.code != 403:
            raise
        # GitHub can forbid creating new release tags while allowing uploads to
        # an already approved private prerelease. Never overwrite its assets.
        old = request(f"{API}/releases/tags/ontrack-native-v48")
        assert old["tag_name"] == "ontrack-native-v48"
        assert old["prerelease"] and not old["draft"] and not old.get("immutable", False)
        print("CANDIDATE_FALLBACK_TO_EXISTING_PRIVATE_PRERELEASE", file=sys.stderr)
        return old, True

def asset_name(original, fallback):
    return f"OnTrack-V1-Candidate-{RUN_ID}-{original}" if fallback else original

def upload(release, name, data):
    url = release["upload_url"].split("{", 1)[0] + "?" + urllib.parse.urlencode({"name": name})
    mime = "application/vnd.android.package-archive" if name.endswith(".apk") else "application/octet-stream"
    result = request(url, method="POST", raw=data, content_type=mime)
    assert result["name"] == name and result["size"] == len(data), "upload_verification_failed"
    expected = hashlib.sha256(data).hexdigest()
    if result.get("digest"):
        assert result["digest"] == "sha256:" + expected, "asset_digest_mismatch"
    return result

def preflight():
    assert os.environ["GITHUB_REPOSITORY"] == REPO
    assert os.environ["GITHUB_REF"] == "refs/heads/feat/voice-v1-minimal-console-20261106"
    release, fallback = destination()
    name = f"OnTrack-v1-candidate-upload-check-{RUN_ID}.json"
    proof = json.dumps({"run_id": RUN_ID, "source_sha": SOURCE_SHA,
                        "purpose": "upload-permission-probe"}).encode("utf-8")
    existing = [a for a in release.get("assets", []) if a["name"] == name]
    if existing:
        item = existing[0]
        assert item["size"] == len(proof), "existing_probe_size_mismatch"
    else:
        item = upload(release, name, proof)
    # Leave a small auditable receipt if DELETE is forbidden. It is not an APK.
    try:
        request(f"{API}/releases/assets/{item['id']}", method="DELETE")
    except urllib.error.HTTPError as error:
        if error.code not in (403, 404, 405):
            raise
    print(json.dumps({"status": "GITHUB_RELEASE_UPLOAD_PREFLIGHT_PASS",
                      "url": release["html_url"], "fallback": fallback, "run": RUN_ID}))

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
    release, fallback = destination()
    existing = {asset["name"]: asset for asset in release.get("assets", [])}
    results = {}
    for path in (apk, checksum, report, provenance, record):
        expected_hash = digest(path)
        name = asset_name(path.name, fallback)
        if name in existing:
            item = existing[name]
            assert item["size"] == path.stat().st_size, "existing_asset_size_mismatch"
            if item.get("digest"):
                assert item["digest"] == "sha256:" + expected_hash, "existing_asset_hash_mismatch"
        else:
            item = upload(release, name, path.read_bytes())
        results[name] = {"sha256": expected_hash, "bytes": path.stat().st_size,
                         "asset_url": item.get("browser_download_url")}
    print(json.dumps({"status":"PRIVATE_CANDIDATE_RELEASE_VERIFIED",
                      "tag": release["tag_name"], "url": release["html_url"],
                      "fallback": fallback, "assets": results}))

if __name__ == "__main__":
    if len(sys.argv) == 2 and sys.argv[1] == "--preflight":
        preflight()
    elif len(sys.argv) == 1:
        main()
    else:
        raise SystemExit("usage: publish_v1_candidate_release.py [--preflight]")
