#!/usr/bin/env python3
"""Recover already built signed APK directly to private GitHub Release.

No Gradle, no re-signing, no GitHub Actions artifact upload. This uses a
pin-sharp verified local candidate produced by the original Windows runner.
"""
import datetime
import hashlib
import json
import os
import pathlib
import subprocess
import urllib.error
import urllib.parse
import urllib.request

REPO = "OnTrack-Development/OnTrack-voice"
SOURCE_RUN = "42399912294"
SOURCE_SHA = "fd92d33d5dce5ca3eaf92cd0412209cbf961c02b"
EXPECTED_APK_SHA256 = "9c340d1f2ecef3bf3dbc65a59f177382732f89bd76f283d04410cca8b7639689"
EXPECTED_BYTES = 11954381
CERT = "d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f"
NAME = "OnTrack-Voice-0.3.27.apk"
TAG = "ontrack-io-premium-0.3.27-signed-42399912294"
ROOT = pathlib.Path("D:/ai/OnTrack-Voice-Builds/v1-candidates") / SOURCE_RUN
API = "https://api.github.com/repos/" + REPO + "/"
TOKEN = os.environ["GH_TOKEN"]

def digest(data):
    return hashlib.sha256(data).hexdigest()

def request(url, method="GET", data=None, extra=None):
    headers = {
        "Authorization": "Bearer " + TOKEN,
        "Accept": "application/vnd.github+json",
        "X-GitHub-Api-Version": "2022-11-28",
        "User-Agent": "OnTrack-IOPremium-Signed-Candidate-Recovery",
    }
    headers.update(extra or {})
    return urllib.request.Request(url, data=data, headers=headers, method=method)

def api(path, method="GET", body=None):
    raw = None if body is None else json.dumps(body, separators=(",", ":")).encode("utf-8")
    with urllib.request.urlopen(request(API + path, method, raw, {"Content-Type": "application/json"}), timeout=35) as rsp:
        return json.load(rsp)

def candidate():
    path = ROOT / NAME
    data = path.read_bytes()
    if len(data) != EXPECTED_BYTES or digest(data) != EXPECTED_APK_SHA256:
        raise RuntimeError("preserved APK digest/size differs from verified candidate")
    report = json.loads((ROOT / "size-report.json").read_text(encoding="utf-8-sig"))
    assert report["sha256"] == EXPECTED_APK_SHA256
    assert report["source_build_run"] == int(SOURCE_RUN) or str(report["source_build_run"]) == SOURCE_RUN
    assert report["release"] == "0.3.27" and report["signing_cert_verified"] is True
    source = json.loads((ROOT / "ontrack-build-source.json").read_text(encoding="utf-8-sig"))
    assert source["device_audio_verified"] is False, "candidate metadata must not overclaim physical audio test"
    sdk = pathlib.Path(os.environ["LOCALAPPDATA"]) / "Android" / "Sdk"
    signer = sdk / "build-tools" / "36.0.0" / "apksigner.bat"
    analyzer = sdk / "cmdline-tools" / "latest" / "bin" / "apkanalyzer.bat"
    check = subprocess.check_output([str(signer), "verify", "--print-certs", str(path)], text=True).lower()
    assert CERT in check, "candidate not signed by installed identity"
    subprocess.run([str(signer), "verify", "--verbose", str(path)], check=True)
    assert subprocess.check_output([str(analyzer), "manifest", "version-code", str(path)], text=True).strip() == "50"
    assert subprocess.check_output([str(analyzer), "manifest", "application-id", str(path)], text=True).strip() == "com.ontrack.voiceproof"
    run = api("actions/runs/" + SOURCE_RUN)
    assert run["head_sha"] == SOURCE_SHA and run["head_branch"] == "feat/v1-io-premium-20261107"
    assert run["status"] == "completed"
    jobs = api("actions/runs/" + SOURCE_RUN + "/jobs?per_page=30")["jobs"]
    must_pass = {
        "Run targeted OnTrack Android unit and daemon tests (matching Linux release gate)",
        "Compile Android minified release APK with code and resource shrinking",
        "Verify exact shell-side D8 jar is present inside RELEASE APK",
        "Sign APK with original identity and verify final manifest",
        "Preserve verified signed candidate outside volatile runner workspace",
    }
    actual = {step["name"] for job in jobs for step in job.get("steps", [])
              if step.get("conclusion") == "success"}
    if not must_pass <= actual:
        raise RuntimeError("original build's signed APK gates did not pass")
    print("PRESERVED_APK_VERIFIED", json.dumps({"bytes": len(data), "sha256": digest(data), "source_run": SOURCE_RUN}), flush=True)
    return data

def release():
    try:
        existing = api("releases/tags/" + TAG)
    except urllib.error.HTTPError as exc:
        if exc.code != 404:
            raise
        existing = api("releases", "POST", {
            "tag_name": TAG,
            "target_commitish": SOURCE_SHA,
            "name": "OnTrack Voice 0.3.27 - IO Premium signed candidate",
            "body": ("Signed Android 0.3.27, versionCode 50, original signing identity; "
                     "verified in run " + SOURCE_RUN + ". The original workflow failed "
                     "only because GitHub Actions artifact storage quota was exhausted. "
                     "No automatic production OTA is implied, and physical SIM audio "
                     "end-to-end remains to be verified."),
            "draft": False,
            "prerelease": True,
            "make_latest": "false",
        })
    assert existing["tag_name"] == TAG and existing.get("prerelease") and not existing.get("draft")
    assert existing["target_commitish"] == SOURCE_SHA or existing.get("tag_name") == TAG
    return existing

def upload(data):
    info = release()
    matches = [a for a in info.get("assets", []) if a["name"] == NAME]
    assert len(matches) <= 1
    if matches:
        item = matches[0]
        if item.get("size") != len(data) or (item.get("digest") and item["digest"] != "sha256:" + digest(data)):
            raise RuntimeError("existing release asset has wrong digest")
    else:
        url = info["upload_url"].split("{", 1)[0] + "?" + urllib.parse.urlencode({"name": NAME})
        try:
            with urllib.request.urlopen(
                request(url, "POST", data, {"Content-Type": "application/vnd.android.package-archive"}),
                timeout=160
            ) as response:
                item = json.load(response)
        except urllib.error.HTTPError as err:
            # Network request can be ambiguous; verify the remote asset before retry.
            if err.code not in (409, 422, 502, 503, 504):
                raise
            matches = [a for a in release().get("assets", []) if a["name"] == NAME]
            if len(matches) != 1:
                raise
            item = matches[0]
    assert item["size"] == EXPECTED_BYTES
    if item.get("digest"):
        assert item["digest"] == "sha256:" + EXPECTED_APK_SHA256
    print("SIGNED_CANDIDATE_RELEASE_UPLOADED", json.dumps({
        "release": info["html_url"],
        "asset": item["browser_download_url"],
        "asset_id": item["id"],
        "sha256": EXPECTED_APK_SHA256,
        "bytes": EXPECTED_BYTES,
        "production_ota_published": False,
    }), flush=True)

if __name__ == "__main__":
    upload(candidate())
