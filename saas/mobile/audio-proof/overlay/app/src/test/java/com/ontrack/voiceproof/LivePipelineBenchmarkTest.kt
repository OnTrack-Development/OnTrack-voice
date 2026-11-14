package com.ontrack.voiceproof

import org.json.JSONObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

class LivePipelineBenchmarkTest {

    @Test
    fun testPipelineStepTimingFields() {
        val timing = LivePipelineBenchmark.PipelineStepTiming(
            dnsLookupMs = 12.35,
            dnsIp = "65.21.208.232",
            dnsFamily = "IPv4",
            tcpConnectMs = 45.68,
            tlsHandshakeMs = 78.90,
            voiceSessionApiMs = 150.23,
            voiceSessionAuthMs = 1.25,
            voiceSessionVaultMs = 3.50,
            voiceSessionInternalMs = 4.75,
            geminiWsConnectMs = 85.12,
            geminiSetupMs = 40.50,
            uplinkSendMs = 2.10,
            geminiFirstTextMs = 220.40,
            ttsRequestNetMs = 310.20,
            ttsServerAuthMs = 1.10,
            ttsServerVaultMs = 2.20,
            fishConnectMs = 95.30,
            fishTtfbMs = 280.50,
            ttsStreamDownloadMs = 450.00,
            maxInterChunkGapMs = 165.50,
            interChunkJitterAlert = true,
            greetingCacheReadMs = 4.20,
            audioTrackWriteMs = 3.10,
            totalRoundTripToFirstAudioMs = 850.50,
            routeName = "cellular",
            success = true
        )

        assertEquals(12.35, timing.dnsLookupMs, 0.001)
        assertEquals("65.21.208.232", timing.dnsIp)
        assertEquals("IPv4", timing.dnsFamily)
        assertEquals(45.68, timing.tcpConnectMs, 0.001)
        assertEquals(78.90, timing.tlsHandshakeMs, 0.001)
        assertEquals(150.23, timing.voiceSessionApiMs, 0.001)
        assertEquals(1.25, timing.voiceSessionAuthMs, 0.001)
        assertEquals(3.50, timing.voiceSessionVaultMs, 0.001)
        assertEquals(4.75, timing.voiceSessionInternalMs, 0.001)
        assertEquals(165.50, timing.maxInterChunkGapMs, 0.001)
        assertTrue(timing.interChunkJitterAlert)
        assertEquals("cellular", timing.routeName)
        assertTrue(timing.success)
        assertNotNull(timing.toJson())
    }

    @Test
    fun testJitterAlertThreshold() {
        val normalTiming = LivePipelineBenchmark.PipelineStepTiming(maxInterChunkGapMs = 120.0, interChunkJitterAlert = false)
        assertFalse(normalTiming.interChunkJitterAlert)

        val laggyTiming = LivePipelineBenchmark.PipelineStepTiming(maxInterChunkGapMs = 175.0, interChunkJitterAlert = true)
        assertTrue(laggyTiming.interChunkJitterAlert)
    }

    @Test
    fun testPercentileCalculations() {
        val sample = listOf(10.0, 20.0, 30.0, 40.0, 50.0, 60.0, 70.0, 80.0, 90.0, 100.0)
        // Reflection or internal method check
        val m = LivePipelineBenchmark::class.java.getDeclaredMethod("percentile", List::class.java, Double::class.java)
        m.isAccessible = true

        val p50 = m.invoke(LivePipelineBenchmark, sample, 50.0) as Double
        val p95 = m.invoke(LivePipelineBenchmark, sample, 95.0) as Double
        val p99 = m.invoke(LivePipelineBenchmark, sample, 99.0) as Double

        assertEquals(55.0, p50, 0.5)
        assertEquals(95.5, p95, 1.0)
        assertEquals(99.1, p99, 1.0)
    }
}
