#!/usr/bin/env python3
"""Guard the no-APK visual update contract for the currently installed Android v20."""
import json
import subprocess
from pathlib import Path

ui=Path('saas/mobile/ota/ui.html').read_text()
meta=json.loads(Path('saas/mobile/ota/release.json').read_text())
assert meta['revision']>=17, 'A signed OTA revision must advance'
assert meta['min_native']<=20<=meta['max_native'], 'Existing Android 0.2.9 must accept this OTA without APK'
assert meta['runtime']==1
for part in ('requestUiRefresh()', "send('checkUpdates')", "data-view=\"dashboard\"", 'body.light', "readUiTheme()"):
    assert part in ui, 'Missing UI-only capability: '+part
assert 'id="rollback"' not in ui, 'Legacy UI rollback must stay hidden'
assert 'id="uiPullStatus"' in ui
assert 'id="upgradeAndroid"' in ui, 'Do not remove separate native update controls'
inline=ui.split('<script>',1)[1].split('</script>',1)[0]
subprocess.run(['node','--check'],input=inline,text=True,check=True)
print('PASS: OnTrack UI OTA compatible with installed v20, pull-to-refresh and JS syntax')
