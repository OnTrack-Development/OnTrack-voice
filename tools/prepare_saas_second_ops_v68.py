#!/usr/bin/env python3
"""Build pinned immutable old+new v68 patch ZIP for Windows SSH runner."""
from pathlib import Path
import os
import subprocess
import sys
import zipfile

BASE='edbf1e46a5fa5ac523fd8dda8af442f38bbea5b1'
FILES=[
    '_core/ExternalOrderClaim.php',
    '_core/OperationalOutbox.php',
    '_core/EmailDelivery.php',
    '_core/Signup.php',
    '_core/Actions.php',
    '_core/Views.php',
    '_core/TenantWorkspace.php',
    'hooks/index.php',
    'operational-worker.php',
    'flow-ai-worker.php',
    'whatsapp-worker.php',
    'jobs/knowledge_sync.php'
]
NEW={'_core/ExternalOrderClaim.php','_core/OperationalOutbox.php',
     '_core/EmailDelivery.php','operational-worker.php'}

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
    print('V68_PINNED_ATOMIC_DELTA_PACKAGE_PASS',target.stat().st_size,flush=True)

if __name__=='__main__':
    main()
