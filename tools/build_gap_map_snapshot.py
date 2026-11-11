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

def detect_source_findings(tracked):
    """Conservative, reproducible static observations, never runtime claims."""
    entries=set(tracked)
    findings=[]
    def add(id_,title,linked,area,severity,source_paths,reason):
        findings.append({
            "id":id_,"title":title,"linked_gap":linked,"area":area,
            "severity":severity,"status":"source_observation_requires_review",
            "confidence":"tracked_paths_only_or_literal_source_check",
            "evidence_paths":source_paths[:12],"explanation":reason,
            "runtime_verified":False
        })
    laravel="saas/routes/web.php" in entries and any(
        p.startswith("saas/app/Http/Controllers/") for p in entries)
    console="saas/console/_core/Bootstrap.php" in entries and any(
        p.startswith("saas/console/database/schema.") for p in entries)
    if laravel and console:
        add("SRC-001","Laravel وConsole في نفس المصدر","GAP-1","saas","critical",
            ["saas/routes/web.php","saas/console/_core/Bootstrap.php",
             "saas/console/database/schema.mysql.sql","saas/database/migrations/"],
            "تعدد مصادر الشيفرة وSchema مؤكد؛ التشغيل المتزامن على الإنتاج غير مثبت.")
    api=["saas/console/device/v1/index.php","saas/mobile/server/common.php",
         "api/live_session.php","saas/routes/web.php"]
    exists=[p for p in api if p in entries]
    if len(exists)>=3:
        add("SRC-002","واجهات API متعددة تحتاج Contract Matrix","GAP-2","shared",
            "warning",exists,"وجود مسارات منفصلة مؤكد؛ حجم الترافيك والاستهلاك الحقيقي غير مقاس.")
    if "saas/mobile/server/auth.sha256" in entries:
        add("SRC-003","ملف Hash للموبايل متتبع في Git","GAP-5","shared",
            "critical",["saas/mobile/server/auth.sha256"],
            "رصد اسم ملف متتبع فقط، دون قراءة قيمته أو استنتاج أنه مكشوف عبر HTTP.")
    diagnostics=[p for p in ["test.php","omnira_api_tester.php","api/diag.php"] if p in entries]
    if diagnostics:
        add("SRC-004","مسارات تشخيص على Webroot تحتاج بوابة موثقة","GAP-5","saas",
            "warning",diagnostics,
            "وجود ملفات المصدر مؤكد؛ حماية MaintenanceAuth مفحوصة آليًا لكن تعرضها الفعلي للعامة يحتاج اختبار Host.")
    bootstrap=ROOT/"saas/console/_core/Bootstrap.php"
    if bootstrap.is_file() and "'/saas/console'" in bootstrap.read_text(encoding="utf-8",errors="replace"):
        add("SRC-005","روابط فرعية قديمة في مسار الدخول","GAP-7","saas",
            "warning",["saas/console/_core/Bootstrap.php"],
            "مرجع المسار موجود بالكود، لكن 302 Redirect من الدومين الرئيسي يحتاج اختبار Host؛ الإصلاح مؤجل.")
    voice=ROOT/"saas/console/_core/TenantVoiceSession.php"
    if voice.is_file() and "generativelanguage.googleapis.com" in voice.read_text(encoding="utf-8",errors="replace"):
        add("SRC-006","اتصال Google على مسار إنشاء جلسة صوت","GAP-8","shared",
            "warning",["saas/console/_core/TenantVoiceSession.php"],
            "طلب خارجي مؤكد؛ زمن الشبكة على 3G/4G/5G غير مقاس ولا يعني أنه اتصال غير ضروري.")
    if any(p.startswith("VS/") for p in tracked):
        add("SRC-007","عودة مجلد VS المحذوف إلى المصدر","GAP-3","mobile",
            "critical",["VS/"],
            "خرق قرار المالك بمنع إعادة VS؛ يجب حذف المسارات الجديدة بعد مراجعة مصدرها.")
    return findings

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
    result["findings"]=detect_source_findings(tracked)
    result["finding_count"]=len(result["findings"])
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
