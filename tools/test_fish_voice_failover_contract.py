#!/usr/bin/env python3
"""Source-level safety contract for in-call Fish -> Google audio failover.
Complements PHP quota classification and Android JVM/daemon tests.
"""
from pathlib import Path
root=Path('saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof')
server=Path('saas/console/device/v1/tts.php').read_text(encoding='utf-8')
policy=Path('saas/console/device/v1/FishTtsFallbackPolicy.php').read_text(encoding='utf-8')
tnt=(root/'TenantDeviceEnrollment.kt').read_text(encoding='utf-8')
live=(root/'MobileLiveCall.kt').read_text(encoding='utf-8')
google=(root/'PremiumOfficialLive.kt').read_text(encoding='utf-8')
assert 'FishTtsFallbackPolicy::classify($status,$errorBody)' in server
assert 'FishTtsFallbackPolicy::mark($fingerprint,$code)' in server
assert 'if(($circuit=FishTtsFallbackPolicy::active($fingerprint))!==null)' in server
assert "header('Content-Type: application/octet-stream')" in server
assert 'if($upstreamStatus<200||$upstreamStatus>=300)' in server
assert "premium_tts_credit_exhausted" in policy and "premium_tts_rate_limited" in policy
assert 'if(response.code==401)throw SecurityException' in tnt
assert 'FishTtsFailover.reason(response.code,code)' in tnt
assert 'if(reason!=null)throw FishTtsFailoverException(reason)' in tnt
assert 'private val googleVoiceFallback=AtomicBoolean(false)' in live
assert 'FishTtsFailoverException' in live
assert 'googleVoiceFallback.compareAndSet(false,true)' in live
assert 'sdk.enableGoogleAudioFallback()' in live
assert 'private val audioGate=Any()' in google
assert 'maxBufferedBytes=24_000*2*16' in google
assert 'googleAudio(audio,rate)' in google
assert "socket?.close(1000,\"call ended\")" in google
assert 'premiumInput.close()' in live and 'worker?.cancel()' in live
assert 'private val premium=session.optString("voice_engine")=="official_google_live_fish_pcm"' in live
assert 'CaptureDaemon.livePcm' in live and 'Google Live' in live
print('FISH_CREDIT_FALLBACK_AUTH_AND_IN_CALL_SAFETY_CONTRACT_PASS')
