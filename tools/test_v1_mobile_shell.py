#!/usr/bin/env python3
from pathlib import Path
import re,subprocess
a=Path('saas/mobile/ota/ui.html').read_text()
b=Path('saas/mobile/audio-proof/overlay/app/src/main/assets/ontrack-ui.html').read_text()
native=Path('saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLinkActivity.kt').read_text()
assert a==b, 'OTA and APK bundled UI must match'
for id in ['v1Minimal','v1Wizard','v1Enable','v1Disable','v1Sim1','v1Sim2','v1Permissions','v1OpenSite','v1Flow']:
    assert f'id="{id}"' in a, id
assert 'v1Render(s);' in a
assert 'body>.otTabs' in a and 'display:none!important' in a
assert '<!--' not in a and '/*' not in a, 'Do not ship developer comments in UI HTML'
assert 'Intent(Intent.ACTION_VIEW,builder.build())' in native, 'Website must open in external browser'
assert 'ConsoleWebActivity::class.java' not in native[native.index('private fun openConsole('):native.index('private fun showScenarioMenu()')], 'Nested website UI not permitted'
scripts=re.findall(r'<script(?:\\s[^>]*)?>([\\s\\S]*?)</script>',a)
assert scripts,'embedded JavaScript not found'
for js in scripts:
    p=subprocess.run(['node','--check'],input=js,text=True,capture_output=True)
    assert p.returncode==0,p.stderr
print('V1 mobile minimal UI syntax PASS')
