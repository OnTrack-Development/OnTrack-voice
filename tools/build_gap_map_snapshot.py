#!/usr/bin/env python3
"""Generate a source-backed architecture snapshot with per-gap file fingerprints.

The scanner makes SOURCE claims only. It cannot infer live host or call success.
"""
import datetime as dt
import fnmatch
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys

ROOT=Path(__file__).resolve().parents[1]
SEED=ROOT/"ops/gap-map/status.seed.json"
OUTPUT=ROOT/"dist/gap-map/status.json"
MAX_FILES_PER_GAP=120
SKIP_EXT={".png",".jpg",".jpeg",".gif",".webp",".zip",".apk",".aab",".mp3",".wav",".pdf",".jar",".class",".so",".keystore"}

def sha256(blob):
    return hashlib.sha256(blob).hexdigest()

def repository_paths():
    output=subprocess.run(["git","ls-files","-z"],cwd=ROOT,capture_output=True,check=True).stdout
    return [p for p in output.decode("utf-8","replace").split("\x00") if p]

def resolve(pattern, tracked):
    pattern=pattern.strip()
    if not pattern or " + " in pattern or " & " in pattern:
        return []
    if pattern in tracked:
        return [pattern]
    if pattern.endswith("/"):
        return [p for p in tracked if p.startswith(pattern) and not p.endswith("/")][:MAX_FILES_PER_GAP]
    if any(x in pattern for x in ("*","?","[")):
        return [p for p in tracked if fnmatch.fnmatch(p,pattern)][:MAX_FILES_PER_GAP]
    if "/" not in pattern:
        return [p for p in tracked if p.endswith("/"+pattern) or p==pattern][:MAX_FILES_PER_GAP]
    return []

def main():
    if not SEED.is_file():return 2
    seed=json.loads(SEED.read_text(encoding="utf-8"))
    tracked=repository_paths()
    repo_sha=(os.environ.get("GITHUB_SHA","") or subprocess.check_output(
        ["git","rev-parse","HEAD"],cwd=ROOT,text=True)).strip()
    if not re.fullmatch(r"[0-9a-f]{40}",repo_sha):
        print("invalid commit SHA",file=sys.stderr);return 2
    processed=[]
    for gap in seed["gaps"]:
        gap=dict(gap)
        paths=set()
        unresolved=[]
        for ref in gap.get("files",[]):
            matched=resolve(ref,tracked)
            paths.update(matched)
            if not matched:unresolved.append(ref)
        hashed=[]
        for p in sorted(paths)[:MAX_FILES_PER_GAP]:
            if Path(p).suffix.lower() in SKIP_EXT:continue
            item=ROOT/p
            if item.is_symlink() or not item.is_file():continue
            hashed.append((p,sha256(item.read_bytes())))
        if not hashed:hashed=[("__claims__",sha256(
            json.dumps(gap.get("files",[]),sort_keys=True).encode()))]
        # Source-only fingerprint independent of the current branch HEAD,
        # so metadata commits don't revoke an otherwise valid owner review.
        gap["basis_hash"]=sha256(
            json.dumps(hashed,separators=(",",":"),ensure_ascii=False).encode())
        gap["source_probe"]={
            "tracked_file_count":len(paths),"fingerprinted_file_count":len(hashed),
            "unresolved_source_references":unresolved[:12],
            "checked_as":"repo_source_not_production_runtime"
        }
        processed.append(gap)
    result=dict(seed)
    result["gaps"]=processed
    result["commit"]=repo_sha
    result["generated_at"]=dt.datetime.now(dt.timezone.utc).isoformat()
    result["source_scan"]={
        "scanner":"tools/build_gap_map_snapshot.py",
        "source_checked":True,"runtime_checked":False,
        "tracked_files":len(tracked),
        "vs_removed":not any(p.startswith("VS/") for p in tracked),
        "workflow_yaml_count":sum(p.startswith(".github/workflows/") and p.endswith((".yml",".yaml")) for p in tracked),
        "laravel_present":any(p.startswith("saas/app/Http/Controllers/") for p in tracked),
        "console_present":any(p.startswith("saas/console/_core/") for p in tracked),
        "tracked_static_mobile_hash":("saas/mobile/server/auth.sha256" in tracked),
        "critical_voice_path_latency_measurement":"not_instrumented",
        "live_host_signoff":"unverified",
        "simulation_live_calls":"not_executed"
    }
    report=ROOT/"dist/gap-map/simulation-report.json"
    if report.is_file():
        sim=json.loads(report.read_text(encoding="utf-8"))
        if sim.get("source_sha")==repo_sha:
            result["simulation"]=sim
    # Never persist PIN, hashed PIN, private keys, user data or token values.
    assert "pin" not in result or result.get("pin") is None
    OUTPUT.parent.mkdir(parents=True,exist_ok=True)
    encoded=json.dumps(result,ensure_ascii=False,indent=2)+"\n"
    if len(encoded.encode("utf-8"))>130000:
        raise RuntimeError("map snapshot exceeds bounded private API response")
    OUTPUT.write_text(encoded,encoding="utf-8")
    print("GAP_MAP_SOURCE_SNAPSHOT_GENERATED gaps=%d tasks=%d files=%d sha=%s"%
          (len(processed),len(result["tasks"]),len(tracked),repo_sha[:12]))
    return 0
if __name__=="__main__":sys.exit(main())
