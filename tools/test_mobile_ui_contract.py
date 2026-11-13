#!/usr/bin/env python3
"""Guard the no-APK visual update contract for the currently installed Android v20."""
import json
import subprocess
from pathlib import Path

ui=Path('saas/mobile/ota/ui.html').read_text()
embedded=Path('saas/mobile/audio-proof/overlay/app/src/main/assets/ontrack-ui.html').read_text()
assert embedded == ui, 'APK embedded HTML differs from signed OTA UI; emulator will run old Pilot UI'
assert '#otSplash{display:none!important}' in ui, 'Native splash must not be followed by a second WebView spinner'
native=Path('saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLinkActivity.kt').read_text()
assert 'id="homeAgentCards"' in ui and 'id="agentCards"' in ui and 'id="openFlowStudio"' in ui, 'Native agent workspace not visible'
assert 'id="serverHealth"' in ui and 'tenantServerState' in ui and 'updateConnectionHealth(s)' in ui, 'SaaS connection must not reuse voice live status'
assert 'id="v1Minimal"' in ui and 'body>.otTabs' in ui, 'V1 must hide advanced bottom navigation'
assert 'window.onTrackOpenUpdates=openUpdateCenter' in ui and 'enableUpdateNotice' in ui, 'Update notification tap not wired'
assert 'id="updateNotice"' in ui and 'id="updateBell"' in ui and 'id="updateTabDot"' in ui, 'Native update notification controls missing'
assert "el('updateNotice').onclick=openUpdateCenter" in ui and "el('v1UpdateBox').hidden=false" in ui, 'Update prompt must open the compact install panel'
assert 'data-view="agents"' in ui and 'data-panel="agents"' in ui, 'SIM device navigation missing'
assert 'data-view="voices"' not in ui and 'id="voiceLibrary"' in ui, 'Voices must live under settings'
assert '"start","enable"-> requestStartTenantMonitor()' in native, 'Start must run tenant-only action without Pilot key'
assert 'startRequestedAfterPhonePermission=true' in native, 'A missing READ_PHONE_STATE must open the Android permission prompt'
assert 'tenantSimPermission.launch(Manifest.permission.READ_PHONE_STATE)' in native, 'Native permission launcher missing'
assert 'if(granted && continueStart)' in native and 'startTenantMonitor()' in native, 'Monitoring must resume after a direct Start permission grant'
assert 'check(!MobileLinkService.tenantPairingInProgress)' in native, 'Starting SIM monitor must refuse a concurrent pairing operation'
assert 'واجهات هاتف التجربة غير متاحة لحماية حسابات العملاء' not in native, 'Do not mislabel demo SIM as a customer block'
assert 'tenantDesiredEnabled' in native and 'TenantEngineRestorePolicy.shouldRestore' in native, 'Native startup status/restore missing'
assert 'tenantServiceRunning' in ui, 'UI must distinguish running SIM monitor from call-only Google connection'
assert "typeof info.auto_answer_enabled==='boolean'" in ui and 'الرد التلقائي غير مفعّل' in ui, 'Per-agent published auto-answer switch must be visible'
assert Path('saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/TenantCallRouting.kt').is_file(), 'Call-routing parity unit missing'
assert "classList.toggle('agent-on',confirmedActive" in ui and 's.tenantServiceRunning===true' in ui, 'Green switch cannot be based on requested state alone'
assert '#enable.agent-on' in ui and '#disable.agent-off' in ui and 'id="version"' in ui, 'Agent colors and visible version missing'
assert 'id="version" hidden' not in ui and 'aria-label="إصدار التطبيق"' in ui, 'Version number must not be permanently hidden'
assert 's.tenantServiceRunning===true && !s.tenantPairingInProgress' in ui, 'UI may not claim running during pairing/unconfirmed monitor'
assert "el('message').textContent=''" in ui, 'Clear pending start label when Android confirms active'
# Transport parity with the previously working Pilot must never regress.
import hashlib
service=Path('saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLinkService.kt').read_text()
assert "TenantCallRouting.attachAfterOffhook(" in service and "TenantCallRouting.autoAnswerDue(" in service, 'Native live call routing no longer matches tested policy'
assert 'overlay.show(running,agentEnabled' in service and 'showOverlay()\n            return START_STICKY' in service, 'Floating control must reflect native monitor, not idle Live connection'
session=Path('saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLiveCall.kt').read_text()
bridge=Path('saas/mobile/audio-proof/overlay/shellserver/src/com/jemcik/jemrec/shell/LivePcm.java').read_text()
pcm=Path('saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/LinkPcm.kt').read_text()
pilot=Path('saas/mobile/server/common.php').read_text()
assert "'input_channel'=>'mix'" in pilot, 'Unexpected changed old Pilot input source'
assert '.put("input_channel","mix")' in service, 'Tenant must use the same MIX voice input as proven Pilot'
assert 'LinkPcm(settings.optString("input_channel","mix"))' in session, 'Do not replace established PCM engine'
assert 'audio.setMicrophoneMute(true)' in bridge and 'audio.setMicrophoneMute(wasMuted)' in bridge, 'Must preserve mute/takeover'
assert 'session.optString("agent_name"' in session, 'Greeting must use agent display name'


assert 'id="key"' not in embedded and 'id="tenantWorkspaceSlot"' in embedded, 'Old Pilot UI bundled into Android APK'
meta=json.loads(Path('saas/mobile/ota/release.json').read_text())
assert meta['revision']>=40, 'A signed OTA revision must advance'
assert meta['min_native']==34 and meta['max_native']>=50, 'Signed UI must support new native v50 while preserving v34-v49 compatibility'
assert meta['runtime']==1
for part in ('requestUiRefresh()', "send('checkUpdates')", "data-view=\"dashboard\"", 'body.light', "readUiTheme()"):
    assert part in ui, 'Missing UI-only capability: '+part
assert 'id="rollback"' not in ui, 'Legacy UI rollback must stay hidden'
assert 'id="uiPullStatus"' in ui
assert 'callQuickActions' in ui and 'id="callControlHint"' in ui
assert 'استلام المكالمة' in ui, 'Handoff must explicitly say the human takes the current call from the AI'
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
for command in ("send('tenantMenu')", "send('tenantQuickPair')", "send('tenantRefresh')"):
    assert command in ui, 'Native Android SIM bridge not wired: '+command
inline=ui.split('<script>',1)[1].split('</script>',1)[0]
subprocess.run(['node','--check'],input=inline,text=True,check=True)
assert "Number(s.nativeCode)>=25" in ui, 'Tenant runtime must remain gated to new native version'
assert "id=\"openAnswerSettings\"" in ui and "send('openAnswerSettings')" in ui, 'Answer settings bridge missing'

assert 'id="tenantTest"' in ui and "send('tenantTest')" in ui, 'Missing actionable SIM+Live diagnostic'
assert 'Number(s.nativeCode)<26' in ui, 'Native diagnostic must be unavailable in older Android releases'

assert 'humanControl=true\n' in service and 'tenantSession=null;humanControl=true' not in service, 'Transport failure must not fake human takeover'
assert 'audioStart=scope.async(Dispatchers.IO)' in service and 'if(audioStart?.isCompleted==true)' in service and 'audioReady=true;connected=true' in service and 'sessionLoad=scope.async' in service, 'PCM and SaaS readiness must only be true after async shell+Google setup'
assert 'callProblem' in native, 'Native UI must show call/session faults'

# Regression Evals are enforced at source/contract level alongside JVM tests.
# A green build is still NOT proof of mute or duplex on Xiaomi/VoWiFi.
assert service.count('humanControl=true')==1, 'Only ACTION_TAKEOVER may record explicit human control'
assert 'if(slot in 0..1 && inCall && callAnswered && audioReady && call?.active==true)' in service, 'Takeover button must require a live PCM session'
assert 'sessionLoad=scope.async(Dispatchers.IO)' in service, 'Signed Google session must not pause the telephony polling loop'
assert 'audioStart=scope.async(Dispatchers.IO)' in service, 'PCM/Google setup must not freeze call-state monitoring'
assert 'if(audioStart?.isCompleted==true)' in service and 'audioReady=true;connected=true' in service, 'UI readiness requires actual PCM startup completion'
assert 'audioStart?.cancel();audioStart=null' in service, 'Call end/disable must cancel pending PCM setup'
assert 'try{' in service and 'تعذر اتصال صوت المكالمة' in service and 'callProblem' in service, 'Audio exceptions must be displayed, not disguised as human takeover'
assert session.index('pipe=CaptureDaemon.livePcm') < session.index('worker=scope.launch') < session.index('openSocket()',session.index('worker=scope.launch')) < session.index('withTimeout(if(isCellularLink())35000L else 25000L){ready.await()}'), 'Open, mute and drain PCM before waiting for Gemini Live'
assert 'else if(socketReady)' in session, 'Do not describe expected Google setup delay as network failure'
assert 'catch(error:Throwable)' in session and 'stop()' in session.split('catch(error:Throwable)',1)[1].split('private fun openSocket',1)[0], 'Audio setup failures must close pipe and restore microphone'
assert 'audio.setMicrophoneMute(true)' in bridge and 'if (!audio.isMicrophoneMute())' in bridge and 'audio.setMicrophoneMute(wasMuted)' in bridge, 'Shell must confirm mute before PCM header and restore prior state'
assert 'voiceproof/LinkPcm.kt' not in bridge, 'No cross-runtime PCM dependency'
# Regression 2026-11-04: Windows built jemrec-capture.jar but Kotlin and
# Gradle expected ontrack-voiceproof-capture.jar, yielding a signed APK
# without the shell recorder. This static guard complements APK ZIP checking.
windows_builder=Path('tools/build_shellserver_windows.ps1').read_text()
android_overlay=Path('saas/mobile/audio-proof/prepare.py').read_text()
release_workflow=Path('.github/workflows/ontrack-voice-android-release.yml').read_text()
assert "$jar = Join-Path $dir 'ontrack-voiceproof-capture.jar'" in windows_builder, 'Windows D8 output MUST match renamed asset'
assert "text.replace(OLD, NEW)" in android_overlay and ".replace('jemrec-', 'ontrack-voiceproof-')" in android_overlay, 'Upstream overlay no longer renames shell daemon asset'
assert "verify_shell_jar_packaging.py .build/jemrec/app/build/outputs/apk/release/app-release-unsigned.apk" in release_workflow, 'Release APK must be inspected for actual shell daemon prior to signing'
assert "verify_shell_jar_packaging.py $signed" in release_workflow, 'Signed APK must preserve exact D8 shell asset bytes'
# Wi-Fi voice worked, LTE call answered but its sound did not.
# A failed 4G/5G transport MUST allow bounded retry during the SAME
# OFFHOOK call, and the UI must distinguish Google/PCM/shell stages.
routing=Path('saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/TenantCallRouting.kt').read_text()
call_overlay=Path('saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/CallOverlay.kt').read_text()
overlay_model=Path('saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/OverlayUiModel.kt').read_text()
assert 'fun voiceRetryDue(' in routing and 'attempts in 1 until maxAttempts' in routing, 'LTE reconnect must have a hard per-call bound'
assert service.count('catch(timeout:TimeoutCancellationException)')==2, 'Google setup and SIM observer must treat cellular timeouts as recoverable errors, not global cancellation'
assert 'TenantCallRouting.voiceRetryDue(' in service and 'internetAvailableForRetry()' in service and 'CellularCallRecoveryPolicy.MAX_VOICE_START_ATTEMPTS' in service, 'Allow bounded LTE reconnection before VALIDATED while preserving no-network safety'
assert 'audioAttemptCount=0;nextAudioRetryAt=0L' in service, 'Reset retries per call or SIM identity'
assert 'onAudioDiagnostics=' in service and 'audioDiagnostic=diagnostic' in service, 'Show audio stage without exposing PCM'
assert 'capturedPackets.incrementAndGet()' in session and 'modelAudioBytes.addAndGet' in session and 'submittedToShell.addAndGet' in session, 'Capture/AI/shell stages must be separately observable'
assert 'audioDiagnostic:String' in call_overlay and 'audioDiagnostic: String = ""' in overlay_model, 'Floating overlay must show which LTE audio leg failed'
# Both transport layouts are valid: the original v65 SIM bridge, or the newer
# bounded-queue bridge. v72 intentionally restores the original queue/player to
# avoid terminating a live call after a short 2.5s AudioTrack stall.
# Experimental v45 PCM bridge acceptance: original AudioTrack blocking
# injection with no asynchronous queue, preserving verified mute-before-capture.
assert 'AudioTrack.WRITE_BLOCKING' in bridge, 'v45 audio injection path required'
assert 'LinkedBlockingQueue' not in bridge and 'ConcurrentLinkedQueue<Frame>' not in bridge, 'v45 bridge must not queue playback'
assert bridge.index('audio.setMicrophoneMute(true)') < bridge.index('capture.start()'), 'capture must not start before owner mic mute'
assert 'audio.setMicrophoneMute(wasMuted)' in bridge and 'capture.stop()' in bridge, 'mic restore and stream cleanup required'
assert 'PLAYBACK_STALL_TIMEOUT_MS' not in bridge, 'audio stall must not force-close SIM'
assert 'LiveInterruptionPolicy.shouldCutPreviousSpeech' not in session, 'Do not locally cut AI audio on echo inputTranscription'
assert 'v1BrowserApkBtn' in ui and 'browserApkBtn' in ui and "send('openApkDownload')" in ui, 'Direct APK browser button missing from UI'
assert '"openApkDownload"->startActivity' in native, 'Native bridge openApkDownload missing'
print('PASS: Android UI/telephony/PCM source contracts validated; physical phone E2E remains UNVERIFIED')
