#!/usr/bin/env python3
"""Build pinned immutable old+new v67 patch ZIP for Windows SSH runner."""
from pathlib import Path
import os
import subprocess
import sys
import zipfile

BASE='e82a6c44dd3717d20f6021d54e2826c9605b1ae7'
FILES=[
    '_core/Checkout.php','_core/KashierWebhook.php',
    '_core/WhatsAppSupport.php','_core/WhatsAppNativeAgent.php',
    '_core/WhatsAppWhmcsBridge.php','_core/WhatsAppJobSafety.php',
    'whatsapp-worker.php','device/v1/index.php','_core/Actions.php','_core/Views.php'
]
NEW={'_core/WhatsAppJobSafety.php'}

def main():
    target=Path(sys.argv[1]).resolve()
    root=Path(__file__).resolve().parents[1]
    with zipfile.ZipFile(target,'w',compression=zipfile.ZIP_DEFLATED) as archive:
        for rel in FILES:
            source='saas/console/'+rel
            after=(root/source).read_bytes()
            if not after.startswith(b'<?php'):
                raise RuntimeError('unexpected source '+source)
            archive.writestr('new/'+rel,after)
            if rel not in NEW:
                before=subprocess.check_output(['git','show',BASE+':'+source],cwd=root)
                if not before.startswith(b'<?php'):
                    raise RuntimeError('unexpected base '+source)
                archive.writestr('old/'+rel,before)
    print('V67_PINNED_ATOMIC_DELTA_PACKAGE_PASS',target.stat().st_size,flush=True)

if __name__=='__main__':
    main()
