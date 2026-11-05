package com.ontrack.voiceproof
import org.junit.Assert.*
import org.junit.Test
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
    @Test fun outputPreservesSignedSamplesAndDuration() {
        assertArrayEquals(byteArrayOf(-1,-1,-1,-1,2,0,2,0),LinkPcm.output(byteArrayOf(-1,-1,2,0),24000))
    }
}
