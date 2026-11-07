#!/usr/bin/env python3
"""Offline exact brand and update CTA acceptance for the reviewed Stitch UI."""
from pathlib import Path
import json, re

ui=Path('saas/mobile/ota/ui.html').read_text(encoding='utf-8')
embedded=Path('saas/mobile/audio-proof/overlay/app/src/main/assets/ontrack-ui.html').read_text(encoding='utf-8')
css=Path('saas/console/assets/css/app.css').read_text(encoding='utf-8')
views=Path('saas/console/_core/Views.php').read_text(encoding='utf-8')
metadata=json.loads(Path('saas/mobile/ota/release.json').read_text())
assert ui==embedded, 'Embedded app HTML must match the signed OTA reference'
for color in ('#280B08','#F9E7C9','#C92925','#FBF7F0'):
    assert color in ui and color in css, 'Owner brand palette not applied consistently: '+color
assert 'id="v1UpdateBox" aria-labelledby="v1UpdateHeading"' in ui
assert 'id="v1UpdateBanner"' in ui
assert 'id="v1InstallUpdates" class="primary" disabled' in ui
assert 'id="v1UpdateBox" hidden' not in ui
assert "el('v1UpdateBanner').classList.toggle('has-update',updateReady)" in ui
assert "el('v1UpdateTitle').textContent=updateReady" in ui
assert "el('v1InstallUpdates').disabled=el('upgradeAndroid').disabled" in ui
assert "el('v1InstallUpdates').onclick=()=>el('upgradeAndroid').click()" in ui
assert "el('v1CheckUpdates').onclick=()=>el('scanAndroid').click()" in ui
assert 'window.onTrackOpenUpdates=openUpdateCenter' in ui
assert 'function ontrack_native_public_release()' in views
assert "ontrack_update_banner()" in views
assert "function render_releases(array $a):void" in views
assert '/mobile/updates/native/' in views
assert "download>↓ تنزيل APK" in views
assert metadata['revision']==50 and metadata['max_native']==50 and metadata['min_native']==34
js=re.findall(r'<script(?:\\s[^>]*)?>(.*?)</script>',ui,re.S)
assert js,'OTA JS missing'
import subprocess,tempfile
with tempfile.TemporaryDirectory() as tmp:
    temp=Path(tmp)/'stitch-ui.js'
    temp.write_text('\n'.join(js),encoding='utf-8')
    result=subprocess.run(['node','--check',str(temp)],capture_output=True,text=True)
    assert result.returncode==0, result.stderr
print('STITCH_COLORS_WEB_NATIVE_UPDATE_BANNER_AND_JS_PASS')
