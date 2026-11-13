package com.ontrack.voiceproof
import org.junit.Assert.*
import org.junit.Test
import kotlinx.coroutines.channels.Channel
class LinkPcmTest {
    @Test fun mixReadsBothSidesInsteadOfRightOnly(){
        val stereoFrame=byteArrayOf(10,0,30,0)
        assertArrayEquals(byteArrayOf(20,0),LinkPcm("mix").input(stereoFrame+stereoFrame+stereoFrame))
    }

    @Test fun captureUsesRemoteChannelAndCarriesPartialGroups() {
        val p=LinkPcm("right")
        val frame=byteArrayOf(9,0,30,0)
        assertEquals(0,p.input(frame+frame).size)
        assertArrayEquals(byteArrayOf(30,0),p.input(frame))
    }
    @Test fun cellularBacklogDropsStaleFramesWhileWifiKeepsExistingLimit() {
        assertTrue(LiveAudioQueuePolicy.canSend(true, 0))
        assertTrue(LiveAudioQueuePolicy.canSend(true, 24L * 1024))
        assertTrue(LiveAudioQueuePolicy.canSend(true, 64L * 1024 - 1))
        assertFalse(LiveAudioQueuePolicy.canSend(true, 64L * 1024))
        assertFalse(LiveAudioQueuePolicy.canSend(true, 96L * 1024))
        assertFalse(LiveAudioQueuePolicy.canSend(true, 192L * 1024))
        assertTrue(LiveAudioQueuePolicy.canSend(false, 48L * 1024))
        assertTrue(LiveAudioQueuePolicy.canSend(false, 192L * 1024 - 1))
        assertFalse(LiveAudioQueuePolicy.canSend(false, 192L * 1024))
    }
    @Test fun playbackBufferPreservesFullBurstInOrderWithoutEvictingEarlySpeech() {
        assertEquals(20000, LivePlaybackQueuePolicy.MAX_QUEUED_MS)
        assertEquals(20, LivePlaybackQueuePolicy.PACKET_BYTES * 1000 /
            (LivePlaybackQueuePolicy.OUTPUT_RATE * 2))
        val queue=Channel<Int>(LivePlaybackQueuePolicy.MAX_PACKETS)
        repeat(600) { assertTrue("packet $it dropped during 12s PCM burst",queue.trySend(it).isSuccess) }
        repeat(600) { assertEquals("bursty PCM must remain in order",it,queue.tryReceive().getOrNull()) }
        assertTrue(queue.tryReceive().isFailure)
        queue.close()
    }
    @Test fun outputPreservesSignedSamplesAndDuration() {
        assertArrayEquals(byteArrayOf(-1,-1,-1,-1,2,0,2,0),LinkPcm.output(byteArrayOf(-1,-1,2,0),24000))
    }

    @Test fun downlinkResamplerInterpolatesSmoothlyAcrossChunksAndFlushesTail() {
        val resampler = DownlinkResampler()
        // Feed a rising 24kHz ramp split across two separate packets: 0, 1000, 2000 then 3000, 4000
        val chunk1 = byteArrayOf(
            0, 0,
            (-24).toByte(), 3, // 1000
            (-48).toByte(), 7  // 2000
        )
        val chunk2 = byteArrayOf(
            (-72).toByte(), 11, // 3000
            (-96).toByte(), 15  // 4000
        )
        val out1 = resampler.upsample(chunk1, 24000)
        val out2 = resampler.upsample(chunk2, 24000)
        val tail = resampler.flushTail()
        // 5 mono 24kHz samples upsampled 2x -> 10 mono 48kHz samples -> 20 bytes total
        val all = out1 + out2 + tail
        assertEquals(20, all.size)
        val samples = IntArray(all.size / 2) { i ->
            ((all[i * 2].toInt() and 255) or (all[i * 2 + 1].toInt() shl 8)).toShort().toInt()
        }
        assertEquals(0, samples[0])
        assertTrue("interpolated midpoint should sit cleanly between 0 and 1000", samples[1] in 350..650)
        assertEquals(1000, samples[2])
        assertTrue("interpolated midpoint across chunk boundary should sit between 2000 and 3000", samples[5] in 2400..2600)
        assertEquals(4000, samples[8])
    }

    @Test fun adaptiveUplinkFramerBatches20msFastAnd40msSlowCellularWithFlush() {
        assertEquals(640, UplinkAudioFrameBuffer.targetBytes(cellular = false, slowCellular = false))
        assertEquals(640, UplinkAudioFrameBuffer.targetBytes(cellular = true, slowCellular = false))
        assertEquals(1280, UplinkAudioFrameBuffer.targetBytes(cellular = true, slowCellular = true))

        val buffer = UplinkAudioFrameBuffer()
        val p1 = ByteArray(320) { 1 }
        val p2 = ByteArray(320) { 2 }
        assertTrue(buffer.push(p1, UplinkAudioFrameBuffer.FAST_FRAME_BYTES).isEmpty())
        assertEquals(320, buffer.pendingSize())
        val emittedFast = buffer.push(p2, UplinkAudioFrameBuffer.FAST_FRAME_BYTES)
        assertEquals(1, emittedFast.size)
        assertEquals(640, emittedFast[0].size)
        assertEquals(0, buffer.pendingSize())

        val slowBuf = UplinkAudioFrameBuffer()
        assertTrue(slowBuf.push(ByteArray(640) { 7 }, UplinkAudioFrameBuffer.SLOW_CELLULAR_FRAME_BYTES).isEmpty())
        val emittedSlow = slowBuf.push(ByteArray(640) { 8 }, UplinkAudioFrameBuffer.SLOW_CELLULAR_FRAME_BYTES)
        assertEquals(1, emittedSlow.size)
        assertEquals(1280, emittedSlow[0].size)

        // Partial tail left at end of turn must flush cleanly without getting stranded
        assertTrue(slowBuf.push(ByteArray(320) { 9 }, UplinkAudioFrameBuffer.SLOW_CELLULAR_FRAME_BYTES).isEmpty())
        val flushed = slowBuf.flush()
        assertNotNull(flushed)
        assertEquals(320, flushed!!.size)
        assertNull(slowBuf.flush())
    }

    @Test fun telemetrySeparatesPcmMetricsFromNetworkMetrics() {
        val telemetry = LiveAudioTelemetry()
        telemetry.recordPcmSetup(45L)
        telemetry.recordPcmCaptureTick(1000L, 65L)
        telemetry.recordPcmCaptureTick(1020L, null)
        telemetry.recordPcmCaptureTick(1120L, null) // 100ms gap > 80ms threshold
        telemetry.recordPcmDrop()
        telemetry.recordNetworkConnect(310L)
        telemetry.recordAiFirstAudio(480L)
        telemetry.recordFirstAudioInput(520L)
        telemetry.recordSpeakerPlayback(750L)
        telemetry.recordNetworkUplinkSkip()
        telemetry.recordNetworkReconnect()

        val snap = telemetry.snapshot()
        assertEquals(45L, snap.pcmSetupMs)
        assertEquals(65L, snap.pcmFirstFrameMs)
        assertEquals(100L, snap.maxCaptureGapMs)
        assertEquals(1L, snap.captureGapCount)
        assertEquals(1L, snap.pcmDroppedPackets)
        assertEquals(310L, snap.networkConnectMs)
        assertEquals(480L, snap.aiFirstAudioMs)
        assertEquals(520L, snap.firstAudioInputMs)
        assertEquals(750L, snap.speakerPlaybackMs)
        assertEquals(1L, snap.networkSkippedUplinkPackets)
        assertEquals(1L, snap.networkReconnects)
    }

    @Test fun slowCellularClassifierDistinguishes2g3gFrom4g5g() {
        assertTrue(CellularRadioStatus.isSlowCellularType(android.telephony.TelephonyManager.NETWORK_TYPE_EDGE))
        assertTrue(CellularRadioStatus.isSlowCellularType(android.telephony.TelephonyManager.NETWORK_TYPE_HSPAP))
        assertTrue(CellularRadioStatus.isSlowCellularType(android.telephony.TelephonyManager.NETWORK_TYPE_UMTS))
        assertFalse(CellularRadioStatus.isSlowCellularType(android.telephony.TelephonyManager.NETWORK_TYPE_LTE))
        assertFalse(CellularRadioStatus.isSlowCellularType(android.telephony.TelephonyManager.NETWORK_TYPE_NR))
    }
}
