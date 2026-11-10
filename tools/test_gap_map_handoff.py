#!/usr/bin/env python3
"""Guard the current SaaS repair handoff / architecture map against drift.

This checks source registries and persistent entrypoints. It does NOT pretend
to establish host or device success, nor auto-close issues from green CI.
"""
from pathlib import Path
import json
import re

ROOT=Path(__file__).resolve().parents[1]
HANDOFF=ROOT/"docs/audits/ARCHITECTURE_GAP_MAP_CURRENT_HANDOFF_AR.md"
TODO=ROOT/"docs/audits/SAAS_21_PARTIAL_CLOSURE_TODO_2026_11_10_AR.md"
SEED=ROOT/"ops/gap-map/status.seed.json"
ENTRIES=[
    ROOT/"README.md", ROOT/"AGENTS.md", ROOT/"CLAUDE.md",
    ROOT/"docs/memory/CHECKPOINT.md", ROOT/"docs/memory/SESSION_HANDOFF_AR.md",
    ROOT/"docs/ops/ONTRACK_DIRECT_SSH_DEPLOY_ACCESS_20261107_AR.md",
]
for file in [HANDOFF,TODO,SEED,*ENTRIES]:
    if not file.is_file():
        raise AssertionError(f"required_gap_handoff_missing: {file.relative_to(ROOT)}")
    if file.stat().st_size<100:
        raise AssertionError(f"empty_gap_handoff: {file.relative_to(ROOT)}")
body=HANDOFF.read_text(encoding="utf-8")
todo=TODO.read_text(encoding="utf-8")
seed=json.loads(SEED.read_text(encoding="utf-8"))
assert seed.get("schema")==2,"unsupported map data schema"
gaps=seed.get("gaps",[])
gap_ids=[g.get("id") for g in gaps]
assert gap_ids==list(dict.fromkeys(gap_ids)),"duplicate GAP IDs"
assert set(gap_ids)=={f"GAP-{i}" for i in range(1,9)},"map must include 8 documented gaps"
assert seed.get("vs")=="removed_never_restore","VS removal not persisted"
assert not (ROOT/"VS").exists(),"VS folder must not be reintroduced"
tasks=seed.get("tasks",[])
ids=[t.get("id") for t in tasks]
assert len(ids)==len(set(ids)),"duplicate task identifiers"
patched={t["id"] for t in tasks if t.get("stage")=="code_patch_evidenced"}
pending={t["id"] for t in tasks if t.get("stage")=="awaiting_code_gap_closure"}
deferred={t["id"] for t in tasks if t.get("stage")=="deferred_10"}
assert len(patched)==18 and len(pending)==3 and len(deferred)==10, (
    f"outdated task baseline; update registry and TODO together: {len(patched)}/{len(pending)}/{len(deferred)}")
assert pending=={"WEB-CORE-06","WEB-CORE-08","WEB-UX-01"},"pending identifiers differ from TODO"
assert not (patched & pending or patched & deferred or pending & deferred),"overlapping task stages"
assert all(t.get("fullClosure") is False for t in tasks),"do not infer FULL DONE from code fixtures"
for id_ in ids:
    if id_ not in todo:
        raise AssertionError(f"task missing in TODO: {id_}")
for gap_id in gap_ids:
    if gap_id not in todo and gap_id not in body:
        raise AssertionError(f"gap not mentioned in current plan: {gap_id}")
for entry in ENTRIES:
    text=entry.read_text(encoding="utf-8")
    if "ARCHITECTURE_GAP_MAP_CURRENT_HANDOFF_AR.md" not in text:
        raise AssertionError(f"future-chat entrypoint does not link map handoff: {entry.relative_to(ROOT)}")
if "150700" in todo or "150700" in body or "150700" in json.dumps(seed):
    raise AssertionError("map PIN must never appear in tracked documentation/registry")
required=[
    "ops/gap-map/status.seed.json",
    "WEB-CORE-06","WEB-CORE-08","WEB-UX-01",
    "GAP-7","GAP-8","architecture-map",
]
for name in required:
    if name not in body:
        raise AssertionError(f"missing critical handoff topic: {name}")
# Check status/owner confirmation API exists but do not call it on CI.
web=ROOT/"ops/gap-map/web"
for item in ["_auth.php","status.php","verify.php"]:
    if not (web/item).is_file():
        raise AssertionError(f"missing map source component: {item}")
assert "gap_map_authorize(true)" in (web/"verify.php").read_text(encoding="utf-8")
print("ONTRACK_GAP_MAP_HANDOFF_8_GAPS_21_SAAS_TASKS_AND_ACCESS_PASS")
