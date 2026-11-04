#!/usr/bin/env python3
"""Fail release if its authenticated Android shell daemon is missing from APK.

On 2026-11-04 the Windows builder wrote jemrec-capture.jar after prepare.py
renamed CaptureDaemon.ASSET and Gradle to ontrack-voiceproof-capture.jar.
Gradle silently skipped the absent file, still signed/published the APK, and
the live SIM call failed: FileNotFoundException(ontrack-voiceproof-capture.jar).
Verify actual release APK bytes, not successful Java/D8 compilation alone.
"""
from __future__ import annotations

import argparse
import hashlib
import tempfile
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

EXPECTED = "ontrack-voiceproof-capture.jar"
ASSET = "assets/" + EXPECTED
LEGACY = "assets/jemrec-capture.jar"


def verify(apk: Path, built_jar: Path) -> dict:
    source = built_jar.read_bytes()
    if not source:
        raise AssertionError("empty shell capture jar")
    with ZipFile(built_jar) as jar:
        if jar.namelist().count("classes.dex") != 1:
            raise AssertionError("built shell capture jar lacks exactly one classes.dex")
        if not jar.read("classes.dex"):
            raise AssertionError("empty shell capture classes.dex")
    with ZipFile(apk) as container:
        names = container.namelist()
        if names.count(ASSET) != 1:
            raise AssertionError(
                f"release APK must include exactly one {ASSET}; found {names.count(ASSET)}"
            )
        if LEGACY in names:
            raise AssertionError("old unreferenced jemrec-capture.jar remains in APK")
        packaged = container.read(ASSET)
    if packaged != source:
        raise AssertionError("APK shell capture asset differs from the D8-built jar")
    return {"status": "PASS", "asset": ASSET, "bytes": len(source),
            "sha256": hashlib.sha256(source).hexdigest()}


def self_test() -> None:
    with tempfile.TemporaryDirectory(prefix="ontrack-capture-test-") as tmp:
        root = Path(tmp)
        jar, apk = root / EXPECTED, root / "release.apk"
        with ZipFile(jar, "w", compression=ZIP_DEFLATED) as package:
            package.writestr("classes.dex", b"dex\n035\x00nonempty test classes")
        with ZipFile(apk, "w", compression=ZIP_DEFLATED) as package:
            package.writestr(ASSET, jar.read_bytes())
        assert verify(apk, jar)["status"] == "PASS"
        for corrupted in ("missing", "legacy", "mismatched"):
            with ZipFile(apk, "w", compression=ZIP_DEFLATED) as package:
                if corrupted == "legacy":
                    package.writestr(LEGACY, jar.read_bytes())
                elif corrupted == "mismatched":
                    package.writestr(ASSET, b"broken")
                # Missing: empty APK ZIP
            try:
                verify(apk, jar)
            except AssertionError:
                pass
            else:
                raise AssertionError("release gate failed to reject " + corrupted)
    print("SHELL_CAPTURE_ASSET_NEGATIVE_EVALS_PASS: missing, legacy, and mismatched")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("apk", nargs="?", type=Path)
    parser.add_argument("jar", nargs="?", type=Path)
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()
    if args.self_test:
        self_test()
    else:
        if not args.apk or not args.jar:
            parser.error("provide release APK and built ontrack capture jar")
        import json
        print(json.dumps(verify(args.apk, args.jar), sort_keys=True))


if __name__ == "__main__":
    main()
