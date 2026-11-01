#!/usr/bin/env python3
"""Build a PHP-only pilot server artifact; never package tracked auth.sha256."""
import io
from pathlib import Path
import sys
import tarfile


def package(source, output):
    source, output = Path(source), Path(output)
    if not source.is_dir() or source.is_symlink():
        raise ValueError("unsafe_pilot_source")
    files = sorted(source.glob("*.php")) + [source / ".htaccess"]
    if len(files) < 19:
        raise ValueError("incomplete_pilot_source")
    for file in files:
        if not file.is_file() or file.is_symlink() or file.parent != source:
            raise ValueError("unsafe_pilot_member")
    output.parent.mkdir(parents=True, exist_ok=True)
    with tarfile.open(output, "w:gz", format=tarfile.USTAR_FORMAT) as archive:
        for file in files:
            archive.add(file, arcname="server/" + file.name, recursive=False)
    return len(files)


if __name__ == "__main__":
    if len(sys.argv) != 3:
        raise SystemExit("usage: pack_mobile_server.py source output")
    print("PILOT_PHP_PACKED", package(sys.argv[1], sys.argv[2]))
