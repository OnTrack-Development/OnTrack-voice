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
        assertTrue(LiveAudioQueuePolicy.canSend(true, 24L * 1024 - 1))
        assertFalse(LiveAudioQueuePolicy.canSend(true, 24L * 1024))
        assertFalse(LiveAudioQueuePolicy.canSend(true, 48L * 1024))
        assertFalse(LiveAudioQueuePolicy.canSend(true, 192L * 1024))
        assertTrue(LiveAudioQueuePolicy.canSend(false, 48L * 1024))
        assertTrue(LiveAudioQueuePolicy.canSend(false, 192L * 1024 - 1))
        assertFalse(LiveAudioQueuePolicy.canSend(false, 192L * 1024))
    }
    @Test fun playbackBufferPreservesFullBurstInOrderWithoutEvictingEarlySpeech() {
        assertEquals(20000, LivePlaybackQueuePolicy.MAX_QUEUED_MS)
        assertEquals(25, LivePlaybackQueuePolicy.PACKET_BYTES * 1000 /
            (LivePlaybackQueuePolicy.OUTPUT_RATE * 2))
        val queue=Channel<Int>(LivePlaybackQueuePolicy.MAX_PACKETS)
        repeat(480) { assertTrue("packet $it dropped during 12s PCM burst",queue.trySend(it).isSuccess) }
        repeat(480) { assertEquals("bursty PCM must remain in order",it,queue.tryReceive().getOrNull()) }
        assertTrue(queue.tryReceive().isFailure)
        queue.close()
    }
    @Test fun outputPreservesSignedSamplesAndDuration() {
        assertArrayEquals(byteArrayOf(-1,-1,-1,-1,2,0,2,0),LinkPcm.output(byteArrayOf(-1,-1,2,0),24000))
    }
}
