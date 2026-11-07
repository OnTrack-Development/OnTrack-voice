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

# Source-only safe contexts to identify the duplicate renderer and exact patch anchors.
contexts={}
for name,keywords in {
    '_core/Views.php':[
        'render_io_premium_admin_provider();',
        'SELECT code,name FROM voices WHERE ready=1',
        'if(!$showProvider && (',
        '$active=[];foreach($list as $v)',
        'function render_voices(array $a):void{'
    ],
    '_core/Actions.php':[
        "case 'add_agent':","case 'update_agent':","case 'add_voice_grant':",
        "case 'save_io_premium_provider':","case 'set_voice_ready':"
    ],
    '_core/IoPremiumProvider.php':[
        'function io_premium_admin_save(',
        'function render_io_premium_admin_provider():string',
        "return card('IO Premium"
    ],
    '_core/TenantVoiceSession.php':[
        '$definition=tenant_voice_definition($binding);',
        "require_once __DIR__.'/GoogleTenantKeys.php';"
    ]
}.items():
    path=root/name
    if not path.is_file():continue
    lines=path.read_text(encoding='utf-8').splitlines()
    block=[]
    for key in keywords:
        found=[i for i,line in enumerate(lines) if key in line]
        for i in found[:4]:
            # Static PHP source only. Never include config values or runtime secrets.
            snippet='\\n'.join(lines[max(0,i-3):min(len(lines),i+5)])
            block.append({'marker':key,'line':i+1,'snippet':snippet[:1400]})
    contexts[name]=block

print(json.dumps({'uid':os.getuid(),'host_probe':out,'contexts':contexts,'credentials_read':False},
                 sort_keys=True,separators=(',',':')))
