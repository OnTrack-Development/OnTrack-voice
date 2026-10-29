#!/usr/bin/env python3
"""Create only an empty SaaS directory; emit evidence to stdout, never host docs."""
import argparse
import datetime as dt
import hashlib
import json
import os
from pathlib import Path
import stat


def runtime_snapshot(root):
    result = []
    for directory, dirs, files in os.walk(root, followlinks=False):
        dirs[:] = sorted(d for d in dirs if d not in ("saas", ".git", ".devbridge", "logs", "mail", "node_modules", "vendor") and not (Path(directory) / d).is_symlink())
        for name in sorted(files):
            path = Path(directory) / name
            if path.is_symlink() or not path.is_file():
                continue
            if path.suffix not in (".php", ".js", ".mjs", ".css", ".json") and path.name not in (".htaccess", "PING.txt", "README_AR.md"):
                continue
            if path.stat().st_size < 2000000:
                result.append({"path": str(path.relative_to(root)), "sha256": hashlib.sha256(path.read_bytes()).hexdigest()})
    return sorted(result, key=lambda item: item["path"])


def prepare(root, create=False, expected=None):
    root = Path(root).resolve(strict=True)
    target = root / "saas"
    if target.is_symlink():
        raise ValueError("Refusing a symlink target")
    if target.exists() and not target.is_dir():
        raise ValueError("saas exists and is not a directory; preserved")
    before = runtime_snapshot(root)
    if expected is not None and before != sorted(expected, key=lambda item: item["path"]):
        raise ValueError("Existing runtime differs from recorded inspection; no files changed")
    existed = target.exists()
    if not existed and create:
        target.mkdir(mode=0o755)
    after = runtime_snapshot(root)
    unchanged = before == after
    if not unchanged:
        raise RuntimeError("Observed runtime changed during preparation; inspect without rollback of unrelated activity")
    entries = sorted(p.name for p in target.iterdir()) if target.is_dir() else []
    return {
        "schema": 1, "operation": "prepare_empty_saas_workspace",
        "timestamp_utc": dt.datetime.now(dt.timezone.utc).isoformat(),
        "path": str(target), "existed_before": existed,
        "directory_created": bool(create and not existed), "exists_after": target.is_dir(),
        "mode": oct(stat.S_IMODE(target.stat().st_mode)) if target.exists() else None,
        "entries": entries, "documentation_files_written_on_host": 0,
        "memory_location": "GITHUB_ONLY", "application_implementation_started": False,
        "original_runtime_unchanged": unchanged, "preserved_runtime_files": after,
        "database_changes": False, "dependency_installs": False, "cron_changes": False,
        "result": "EMPTY_WORKSPACE_PREPARED" if target.is_dir() and not entries else ("EXISTING_CONTENT_PRESERVED" if entries else "DRY_RUN"),
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", default="/home/appontrack/public_html")
    parser.add_argument("--create", action="store_true")
    parser.add_argument("--baseline-json")
    args = parser.parse_args()
    expected = json.loads(args.baseline_json) if args.baseline_json else None
    print(json.dumps(prepare(args.root, create=args.create, expected=expected), ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
