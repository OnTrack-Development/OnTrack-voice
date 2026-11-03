#!/usr/bin/env python3
"""Guard the no-APK visual update contract for the currently installed Android v20."""
import json
import subprocess
from pathlib import Path

ui=Path('saas/mobile/ota/ui.html').read_text()
embedded=Path('saas/mobile/audio-proof/overlay/app/src/main/assets/ontrack-ui.html').read_text()
assert embedded == ui, 'APK embedded HTML differs from signed OTA UI; emulator will run old Pilot UI'
native=Path('saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLinkActivity.kt').read_text()
assert '"start","enable"->{' in native, 'Cached UI start must enable bound SaaS SIM without Pilot key'
assert 'واجهات هاتف التجربة غير متاحة لحماية حسابات العملاء' not in native, 'Do not mislabel demo SIM as a customer block'
assert 'tenantDesiredEnabled' in native and 'TenantEngineRestorePolicy.shouldRestore' in native, 'Native startup status/restore missing'
assert 'tenantServiceRunning' in ui, 'UI must distinguish running SIM monitor from call-only Google connection'
# Transport parity with the previously working Pilot must never regress.
import hashlib
service=Path('saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLinkService.kt').read_text()
session=Path('saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLiveCall.kt').read_text()
bridge=Path('saas/mobile/audio-proof/overlay/shellserver/src/com/jemcik/jemrec/shell/LivePcm.java').read_text()
pcm=Path('saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/LinkPcm.kt').read_text()
pilot=Path('saas/mobile/server/common.php').read_text()
assert "'input_channel'=>'mix'" in pilot, 'Unexpected changed old Pilot input source'
assert '.put("input_channel","mix")' in service, 'Tenant must use the same MIX voice input as proven Pilot'
assert 'LinkPcm(settings.optString("input_channel","right"))' in session, 'Do not replace established PCM engine'
assert 'audio.setMicrophoneMute(true)' in bridge and 'audio.setMicrophoneMute(wasMuted)' in bridge, 'Must preserve mute/takeover'
assert 'session.optString("agent_name"' in session, 'Greeting must use agent display name'


assert 'id="key"' not in embedded and 'id="tenantWorkspaceSlot"' in embedded, 'Old Pilot UI bundled into Android APK'
meta=json.loads(Path('saas/mobile/ota/release.json').read_text())
assert meta['revision']>=18, 'A signed OTA revision must advance'
assert meta['min_native']==33 and meta['max_native']==33, 'Advanced UI must not auto-replace legacy 0.3.9 with unsupported commands'
assert meta['runtime']==1
for part in ('requestUiRefresh()', "send('checkUpdates')", "data-view=\"dashboard\"", 'body.light', "readUiTheme()"):
    assert part in ui, 'Missing UI-only capability: '+part
assert 'id="rollback"' not in ui, 'Legacy UI rollback must stay hidden'
assert 'id="uiPullStatus"' in ui
assert 'callQuickActions' in ui and 'id="callControlHint"' in ui
assert 'استلام من الإيجنت' in ui, 'Handoff must explicitly say the human takes the current call from the AI'
assert "el('callActions').style.display=call?'block':'none'" in ui, 'Controls must show while ringing'
assert "el('speaker').disabled=!modern||!answered" in ui, 'No speaker action before call is answered'
assert 'id="upgradeAndroid"' in ui, 'Do not remove separate native update controls'
assert meta['revision'] >= 22 and meta['max_native'] >= 26, 'Native 26 OTA contract required'
for element in ('id="openConsole"', 'id="openScenario"', 'id="tenantWorkspaceSlot"'):
    assert element in ui, 'OnTrack release shortcuts must remain available: '+element
for command in ("send('openConsole')", "send('openScenario')", "send('workspaceSlot'"):
    assert command in ui, 'Native Android bridge command not wired: '+command
assert "Number(s.nativeCode)>=23" in ui, 'Do not enable new native commands on older APKs'
assert "Number(s.nativeCode)>=24" in ui, 'SIM controls must require Android 0.3.3'
for el in ('id="tenantDeviceCenter"', 'id="tenantMenu"', 'id="tenantQr"', 'id="tenantRefresh"', 'id="tenantSim1"', 'id="tenantSim2"'):
    assert el in ui, 'Unified SIM panel missing '+el
for command in ("send('tenantMenu')", "send('tenantQrScan')", "send('tenantRefresh')"):
    assert command in ui, 'Native Android SIM bridge not wired: '+command
inline=ui.split('<script>',1)[1].split('</script>',1)[0]
subprocess.run(['node','--check'],input=inline,text=True,check=True)
print('PASS: OnTrack UI OTA v30 requires Android v33, with full UI controls and JS syntax')

assert "Number(s.nativeCode)>=25" in ui, 'Tenant runtime must remain gated to new native version'
assert "id=\"openAnswerSettings\"" in ui and "send('openAnswerSettings')" in ui, 'Answer settings bridge missing'

assert 'id="tenantTest"' in ui and "send('tenantTest')" in ui, 'Missing actionable SIM+Live diagnostic'
assert 'Number(s.nativeCode)<26' in ui, 'Native diagnostic must be unavailable in older Android releases'
