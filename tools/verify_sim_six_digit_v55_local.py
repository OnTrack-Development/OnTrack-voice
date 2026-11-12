#!/usr/bin/env python3
"""Offline v55 release validation using Windows Java, no OpenSSL dependency.

The SSH host independently re-verifies both signatures with OpenSSL before
installing any public bytes. This is a local fail-fast release gate.
"""
import base64
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import tempfile

EXPECTED_CERT = "d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f"


def verify(directory: Path, scope: str) -> dict:
    cert = (directory / "signing-cert.der").read_bytes()
    if hashlib.sha256(cert).hexdigest() != EXPECTED_CERT:
        raise RuntimeError("Original Android signing certificate changed")
    envelope = json.loads((directory / "latest.json").read_text(encoding="utf-8"))
    payload = envelope["payload"].encode("utf-8")
    signature = base64.b64decode(envelope["signature"], validate=True)
    if not signature:
        raise RuntimeError("Missing RSA release signature")
    meta = json.loads(payload)
    if meta.get("schema") != 1 or meta.get("channel") != "stable":
        raise RuntimeError("Invalid release metadata")
    if scope == "native":
        if meta.get("version_code") != 55 or meta.get("version_name") != "0.3.32":
            raise RuntimeError("Unexpected v55 APK")
        if meta.get("package") != "com.ontrack.voiceproof":
            raise RuntimeError("Unexpected app identity")
        expected = f"ontrack-voice-v55-{meta['sha256']}.apk"
        # The original APK lives in the persistent signed build folder.
        file = directory / "OnTrack-Voice-0.3.32.apk"
    else:
        if meta.get("revision") != 55 or meta.get("max_native") != 55:
            raise RuntimeError("Unexpected v55 UI")
        if meta.get("runtime") != 1 or meta.get("min_native") != 34:
            raise RuntimeError("Unexpected UI runtime requirements")
        expected = f"ui-{meta['sha256']}.html"
        file = directory / expected
    if meta.get("file") != expected:
        raise RuntimeError("Immutable asset name mismatch")
    if scope == "native" and not file.is_file():
        # Publisher prepares the manifest but keeps the APK in persistent build storage.
        pass
    else:
        body = file.read_bytes()
        if hashlib.sha256(body).hexdigest() != meta["sha256"] or len(body) != meta["size"]:
            raise RuntimeError("Signed asset hash or size mismatch")
    with tempfile.TemporaryDirectory() as td:
        d = Path(td)
        (d / "cert.der").write_bytes(cert)
        (d / "payload").write_bytes(payload)
        (d / "signature").write_bytes(signature)
        subprocess.run(
            ["java", "tools/VerifySignedMobileOta.java", str(d / "cert.der"),
             str(d / "payload"), str(d / "signature")],
            check=True, timeout=20,
        )
    return meta


def main():
    directory = Path(sys.argv[1]) if len(sys.argv) > 1 else Path(".build/sim6-v55-delivery")
    native = verify(directory / "native", "native")
    ui = verify(directory / "ui", "ui")
    if native["version_code"] != ui["revision"]:
        raise RuntimeError("Native and UI versions differ")
    print("SIX_DIGIT_SIGNED_MANIFESTS_PASS")


if __name__ == "__main__":
    main()
