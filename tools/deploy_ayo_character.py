"""Safe, source-verified deployment of AYO's official animated character into /ayo only."""
import base64
import hashlib
import json
import os
import pathlib
import shutil
import subprocess
import tempfile

DOCROOT = pathlib.Path("/home/appontrack/public_html/ayo")
STAGE = pathlib.Path("/home/appontrack/.ayo-avatar-rollout-20261105")
BACKUP = pathlib.Path("/home/appontrack/.ayo-avatar-backup-20261105")
EXPECTED_MANIFEST = "aa60d66d167b310b2122938099e8b53b1cacf31c"
EXPECTED_IMAGE_SHA256 = "0e3500349d51b91c474b124eed9556e3052c469a1470c86eb1d815441f417068"
EXPECTED_CHUNKS = 5
EDITABLE = {"index.php", "app.js", "style.css", "api.php"}
NEW = {"voice-expression.php", "ayo-avatar.js", "assets/ayo-character.webp"}

def gitblob(raw):
    return hashlib.sha1(b"blob " + str(len(raw)).encode() + b"\0" + raw).hexdigest()

def deploy():
    if not DOCROOT.is_dir() or DOCROOT.is_symlink():
        raise RuntimeError("AYO target missing or invalid")
    if not STAGE.is_dir() or (STAGE / ".next").read_text().strip() != str(EXPECTED_CHUNKS):
        raise RuntimeError("Missing verified AYO release chunks")
    if BACKUP.exists():
        raise RuntimeError("Backup collision; refusing overwrite")
    manifest_raw = (STAGE / "release.json").read_bytes()
    if gitblob(manifest_raw) != EXPECTED_MANIFEST:
        raise RuntimeError("Release manifest SHA mismatch")
    manifest = json.loads(manifest_raw)
    if manifest["version"] != 1 or manifest["path"] != str(DOCROOT):
        raise RuntimeError("Unexpected release manifest")
    built = {}
    for item in manifest["patches"]:
        name = item["name"]
        if name not in EDITABLE or name in built:
            raise RuntimeError("Unexpected modified file")
        target = DOCROOT / name
        if not target.is_file() or target.is_symlink():
            raise RuntimeError("Unexpected existing AYO source")
        raw = target.read_bytes()
        if gitblob(raw) != item["oldSha"]:
            raise RuntimeError("Live source differs: " + name)
        chars = list(raw.decode("utf-8"))
        for start, end, insertion in reversed(item["edits"]):
            if not 0 <= start <= end <= len(chars):
                raise RuntimeError("Invalid patch span")
            chars[start:end] = list(insertion)
        changed = "".join(chars).encode("utf-8")
        if gitblob(changed) != item["newSha"]:
            raise RuntimeError("Patched source integrity failure: " + name)
        built[name] = changed
    for item in manifest["added"]:
        name = item["name"]
        if name not in NEW or name in built or (DOCROOT / name).exists():
            raise RuntimeError("Unexpected added AYO file: " + name)
        raw = item["content"].encode("utf-8")
        if gitblob(raw) != item["sha"]:
            raise RuntimeError("New source integrity failure: " + name)
        built[name] = raw
    image = base64.b64decode(manifest["image"]["encoded"], validate=True)
    if manifest["image"]["path"] != "assets/ayo-character.webp":
        raise RuntimeError("Unexpected portrait path")
    if hashlib.sha256(image).hexdigest() != EXPECTED_IMAGE_SHA256:
        raise RuntimeError("User portrait checksum mismatch")
    if not (image.startswith(b"RIFF") and image[8:12] == b"WEBP"):
        raise RuntimeError("Invalid avatar WebP data")
    built["assets/ayo-character.webp"] = image
    if set(built) != EDITABLE | NEW:
        raise RuntimeError("Missing required AYO release files")
    php = shutil.which("php") or "/usr/local/bin/php"
    with tempfile.TemporaryDirectory(prefix="ayo-verify-", dir=str(STAGE)) as temp:
        tempdir = pathlib.Path(temp)
        for name, raw in built.items():
            out = tempdir / name
            out.parent.mkdir(exist_ok=True, parents=True)
            out.write_bytes(raw)
        for name in ("index.php", "api.php", "voice-expression.php"):
            check = subprocess.run([php, "-l", str(tempdir / name)], capture_output=True, timeout=8)
            if check.returncode:
                raise RuntimeError("PHP lint failed: " + name)
        # Shared-host PHP may resolve to php-cgi, which does not support php -r.
        # Run a temporary PHP file with -f, supported by both php-cgi and CLI.
        expression_test = tempdir / "test_expression.php"
        expression_test.write_text(
            '<?php require __DIR__."/voice-expression.php"; '
            '$v=ayo_expression_prepare("بنعتذر عن التأخير","expressive","inworld-tts-2"); '
            'if ($v["nonverbalCue"]!=="sigh") exit(3); echo "AYO_EXPRESSION_OK";',
            encoding="utf-8"
        )
        expression_check = subprocess.run([
            php, "-f", str(expression_test)
        ], capture_output=True, timeout=8)
        if expression_check.returncode:
            raise RuntimeError("AYO expression regression test failed")
        node = shutil.which("node")
        if node:
            # Node parses ES modules when source is piped as module input.
            for name in ("app.js", "ayo-avatar.js"):
                result = subprocess.run([node, "--input-type=module", "--check"], input=built[name], capture_output=True, timeout=8)
                if result.returncode:
                    raise RuntimeError("JavaScript parse failure: " + name)
    assets = DOCROOT / "assets"
    if assets.exists() and (not assets.is_dir() or assets.is_symlink()):
        raise RuntimeError("Unexpected AYO assets directory")
    for name in NEW:
        if (DOCROOT / name).exists():
            raise RuntimeError("Refusing overwrite: " + name)
    BACKUP.mkdir(mode=0o700)
    for name in EDITABLE:
        (BACKUP / name).write_bytes((DOCROOT / name).read_bytes())
    assets.mkdir(mode=0o755, exist_ok=True)
    written = []
    try:
        for name, raw in built.items():
            target = DOCROOT / name
            partial = target.parent / (target.name + ".ayo-new")
            if partial.exists():
                raise RuntimeError("AYO staging-file collision")
            fd = os.open(partial, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o644)
            with os.fdopen(fd, "wb") as f:
                f.write(raw)
            partial.chmod(0o644)
            os.replace(partial, target)
            written.append(name)
    except Exception:
        for name in reversed(written):
            target = DOCROOT / name
            if name in EDITABLE:
                os.replace(BACKUP / name, target)
            else:
                target.unlink(missing_ok=True)
        raise
    for name, raw in built.items():
        if (DOCROOT / name).read_bytes() != raw:
            raise RuntimeError("Post-deploy verification failed: " + name)
    print(json.dumps({
        "deployed": True,
        "target": str(DOCROOT),
        "updated": sorted(EDITABLE),
        "added": sorted(NEW),
        "portrait_bytes": len(image),
        "source_sha_verified": True,
        "php_lint": "pass",
        "emotion_check": "pass",
        "js_checked_on_host": bool(node),
        "backup_outside_docroot": str(BACKUP)
    }))
