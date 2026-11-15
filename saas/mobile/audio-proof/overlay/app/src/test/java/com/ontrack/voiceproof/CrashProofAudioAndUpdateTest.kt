package com.ontrack.voiceproof

import android.content.Context
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.runBlocking
import okhttp3.OkHttpClient
import org.json.JSONException
import org.json.JSONObject
import org.junit.Assert.*
import org.junit.Test
import org.mockito.Mockito.`when`
import org.mockito.Mockito.mock
import java.io.File
import java.util.concurrent.atomic.AtomicReference

class CrashProofAudioAndUpdateTest {

    @Test
    fun premiumOfficialLiveHandlesInvalidTokenAndExceptionsWithoutCrashingThread() = runBlocking {
        val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
        val client = OkHttpClient()
        val capturedDisconnect = AtomicReference("")
        try {
            val sdk = PremiumOfficialLive(
                scope = scope,
                client = client,
                token = "invalid_token_without_prefix",
                model = "models/gemini-2.5-flash-native-audio-preview-12-2025",
                instruction = "تحية قصيرة",
                caller = { throw RuntimeException("caller callback failure") },
                assistant = { throw RuntimeException("assistant callback failure") },
                phrase = { throw RuntimeException("phrase callback failure") },
                googleAudio = { _, _ -> throw RuntimeException("audio callback failure") },
                interrupted = { throw RuntimeException("interrupted callback failure") },
                disconnected = { reason -> capturedDisconnect.set(reason) }
            )

            // Must NOT throw uncaught exception out of start()
            sdk.start()
            assertTrue(sdk.lastError.isNotBlank())
            assertTrue(capturedDisconnect.get().isNotBlank())

            // Calling methods after failure must remain safe
            sdk.pushPcm(ByteArray(320))
            sdk.sendClientContent("اختبار")
            sdk.prompt("تحديث")
            sdk.stop()
            assertFalse(sdk.enableGoogleAudioFallback())
        } finally {
            scope.cancel()
        }
    }

    @Test
    fun mobileLiveCallFailsGracefullyWithoutCrashingProcessOnInvalidSessionOrThrowingCallbacks() = runBlocking {
        val context = mock(Context::class.java)
        val client = OkHttpClient()
        val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
        val session = mock(JSONObject::class.java)
        `when`(session.optString("voice_engine")).thenReturn("gemini_live_native")
        `when`(session.getString("token")).thenThrow(JSONException("No value for token"))
        val settings = mock(JSONObject::class.java)
        try {
            val call = MobileLiveCall(
                context = context,
                client = client,
                scope = scope,
                session = session,
                settings = settings,
                notice = { throw RuntimeException("notice callback crash guard test") },
                onAudioDiagnostics = { throw RuntimeException("diagnostics crash guard test") },
                onFinished = { throw RuntimeException("onFinished crash guard test") }
            )

            val outcome = runCatching { call.start() }
            assertTrue(outcome.isFailure)
            assertTrue(call.lastError.isNotBlank())
            assertFalse(call.active)

            // Double stop & awaiting reports with throwing callbacks must not throw
            call.stop()
            call.awaitReports()
        } finally {
            scope.cancel()
        }
    }

    @Test
    fun mobileLiveCallSurvivesMissingPremiumModuleDuringStart() = runBlocking {
        val context = mock(Context::class.java)
        val client = OkHttpClient()
        val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
        val session = mock(JSONObject::class.java)
        `when`(session.optString("voice_engine")).thenReturn("official_google_live_fish_pcm")
        `when`(session.getString("token")).thenReturn("auth_tokens/fake-session-token-1234567890")
        val settings = mock(JSONObject::class.java)
        try {
            val call = MobileLiveCall(
                context = context,
                client = client,
                scope = scope,
                session = session,
                settings = settings,
                notice = {},
                premiumPcm = null
            )

            val outcome = runCatching { call.start() }
            assertTrue(outcome.isFailure)
            assertTrue(call.lastError.isNotBlank())
            assertFalse(call.active)
            call.stop()
        } finally {
            scope.cancel()
        }
    }

    @Test
    fun nativeUpdateSessionStalenessAndDirectApkDownloadUrlAreSafe() {
        val now = 1_700_000_000_000L
        assertFalse(NativeInstallSessionPolicy.isStale(0L, now))
        assertFalse(NativeInstallSessionPolicy.isStale(now - 30_000L, now))
        assertTrue(NativeInstallSessionPolicy.isStale(now - 95_000L, now))

        val root = "https://app.ontrackegy.com/mobile/updates/native/"
        assertEquals(
            "${root}latest.apk",
            NativeInstallSessionPolicy.resolveApkDownloadUrl(root, null)
        )
        assertEquals(
            "${root}ontrack-voice-v67-abc.apk",
            NativeInstallSessionPolicy.resolveApkDownloadUrl(root, "ontrack-voice-v67-abc.apk")
        )
        assertEquals(
            "${root}latest.apk",
            NativeInstallSessionPolicy.resolveApkDownloadUrl(root, "../../evil.apk")
        )
    }

    @Test
    fun uiUpdateButtonsAndProgressContractMatchNativeBridge() {
        val rootDir = generateSequence(File(System.getProperty("user.dir") ?: ".")) { it.parentFile }
            .firstOrNull { File(it, "saas/mobile/ota/ui.html").isFile }
            ?: return
        val ui = File(rootDir, "saas/mobile/ota/ui.html").readText()
        val activity = File(
            rootDir,
            "saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLinkActivity.kt"
        ).readText()

        assertTrue(ui.contains("id=\"v1UpdateProgressWrap\""))
        assertTrue(ui.contains("id=\"v1CheckUpdates\""))
        assertTrue(ui.contains("id=\"v1InstallUpdates\""))
        assertTrue(ui.contains("id=\"v1BrowserApkBtn\""))
        assertTrue(ui.contains("id=\"browserApkBtn\""))
        assertTrue(ui.contains("send('openApkDownload')"))
        assertTrue(ui.contains("send('checkNativeUpdates')"))
        assertTrue(ui.contains("send('installNativeUpdate')"))
        assertTrue(ui.contains("send('checkUpdates')"))

        assertTrue(activity.contains("\"checkUpdates\"->checkUpdates(true)"))
        assertTrue(activity.contains("\"checkNativeUpdates\"->checkNativeUpdates(true)"))
        assertTrue(activity.contains("\"installNativeUpdate\"->installNativeUpdate()"))
        assertTrue(activity.contains("\"openApkDownload\"->startActivity"))
        assertTrue(activity.contains("Intent.FLAG_ACTIVITY_NEW_TASK"))
        assertTrue(activity.contains("nativeUpdateProgress"))
        assertTrue(activity.contains("\"reviveDaemon\"->reviveDaemonNow()"))
        assertTrue(ui.contains("id=\"v1DataModeCard\""))
        assertTrue(ui.contains("id=\"v1WifiFreeToggle\""))
        assertTrue(ui.contains("id=\"v1ReviveDaemon\""))
        assertTrue(ui.contains("send('reviveDaemon')"))
    }

    @Test
    fun cellularDaemonDiagnosticsExplainsMissing5555AndXiaomiSecurity() {
        assertEquals("", CellularDaemonDiagnostics.diagnose(true, true, false, false, false))
        assertTrue(
            CellularDaemonDiagnostics.diagnose(false, false, false, false, false).contains("ADB")
        )
        assertTrue(
            CellularDaemonDiagnostics.diagnose(false, true, false, false, false).contains("5555")
        )
        assertTrue(
            CellularDaemonDiagnostics.diagnose(false, true, false, true, false).contains("Security settings")
        )
        assertTrue(
            CellularDaemonDiagnostics.diagnose(false, true, true, false, true).contains("Wireless Debugging")
        )
    }

    @Test
    fun v69AudioQualityAndReadinessGuardsAreEnforced() {
        val rootDir = generateSequence(File(System.getProperty("user.dir") ?: ".")) { it.parentFile }
            .firstOrNull { File(it, "saas/mobile/audio-proof/prepare.py").isFile }
            ?: return
        val prepare = File(rootDir, "saas/mobile/audio-proof/prepare.py").readText()
        val livePcm = File(
            rootDir,
            "saas/mobile/audio-proof/overlay/shellserver/src/com/jemcik/jemrec/shell/LivePcm.java"
        ).readText()
        val liveCall = File(
            rootDir,
            "saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLiveCall.kt"
        ).readText()
        val service = File(
            rootDir,
            "saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLinkService.kt"
        ).readText()

        // 1. Hardware mic preprocessors must never be injected onto VOICE_CALL SIM capture
        assertFalse(prepare.contains("AcousticEchoCanceler.create"))
        assertFalse(prepare.contains("NoiseSuppressor.create"))
        // 2. LivePcm uses blocking write + 160ms pre-roll + urgent audio priority + zero-drop queue
        assertTrue(livePcm.contains("AudioTrack.WRITE_BLOCKING"))
        assertTrue(livePcm.contains("Process.THREAD_PRIORITY_URGENT_AUDIO"))
        assertTrue(livePcm.contains("SLICE_BYTES = 1920"))
        assertTrue(livePcm.contains("PLAYBACK_STALL_TIMEOUT_MS"))
        // 3. MobileLiveCall requires real firstPcmCaptured before ready & uses smooth DownlinkResampler + flushTail
        assertTrue(liveCall.contains("firstPcmCaptured.await()"))
        assertTrue(liveCall.contains("downlinkResampler.upsample("))
        assertTrue(liveCall.contains("downlinkResampler.flushTail()"))
        assertTrue(liveCall.contains("uplinkBuffer.flush()"))
        // 4. Separate audio readiness & stage telemetry exist on service + VAD timings preserve speech ends
        assertTrue(service.contains("stageCallerPcm"))
        assertTrue(service.contains("stageAiPlayed"))
        assertEquals(160, LiveVadConfig.PREFIX_PADDING_MS)
        assertEquals(320, LiveVadConfig.SILENCE_DURATION_MS)
        assertEquals(1024, LiveVadConfig.DEFAULT_MAX_OUTPUT_TOKENS)
    }
}
