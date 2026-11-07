#!/usr/bin/env python3
"""Read-only code fingerprint and duplicate-control audit on canonical host."""
from pathlib import Path
import hashlib,json,os
root=Path('/home/appontrack/public_html')
if os.getuid()!=2379 or root.is_symlink():raise SystemExit('unauthorized_probe')
names=['_core/Views.php','_core/Actions.php','_core/IoPremiumProvider.php',
       '_core/GoogleTenantKeys.php','_core/TenantVoiceSession.php','device/v1/tts.php']
out={}
for name in names:
    path=root/name
    if path.is_symlink() or not path.is_file():
        out[name]={'exists':False};continue
    src=path.read_bytes()
    markers={
       'premium_admin_card_calls':b'render_io_premium_admin_provider();',
       'premium_admin_form_definitions':b'function render_io_premium_admin_provider():string',
       'premium_access_forms':b'name="io_access_mode"',
       'premium_access_handler':b"case 'save_io_premium_access':",
       'premium_access_check':b'io_premium_access_allowed(',
       'premium_engine':b'official_google_live_fish_pcm',
       'old_tts_grant':b"premium_voice_not_granted",
       'old_individual_voice_grant':b'voice_grants',
       'duplicate_credential_input':b'name="io_fish_api_key"',
    }
    out[name]={'exists':True,'bytes':len(src),'sha256':hashlib.sha256(src).hexdigest(),
               'markers':{k:src.count(v) for k,v in markers.items()}}
print(json.dumps({'uid':os.getuid(),'host_probe':out,'credentials_read':False},
                 sort_keys=True,separators=(',',':')))
