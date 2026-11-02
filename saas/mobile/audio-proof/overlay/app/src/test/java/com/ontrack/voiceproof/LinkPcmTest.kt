package com.ontrack.voiceproof
import org.junit.Assert.*
import org.junit.Test
class LinkPcmTest {
    @Test fun captureUsesRemoteChannelAndCarriesPartialGroups() {
        val p=LinkPcm("right")
        val frame=byteArrayOf(9,0,30,0)
        assertEquals(0,p.input(frame+frame).size)
        assertArrayEquals(byteArrayOf(30,0),p.input(frame))
    }
    @Test fun autoChannelUsesLeftWhenRightIsSilent() {
        val p=LinkPcm("auto")
        val frame=byteArrayOf(45,0,0,0)
        assertArrayEquals(byteArrayOf(45,0),p.input(frame+frame+frame))
    }
    @Test fun autoChannelUsesRightWhenLeftIsSilent() {
        val p=LinkPcm("auto")
        val frame=byteArrayOf(0,0,77,0)
        assertArrayEquals(byteArrayOf(77,0),p.input(frame+frame+frame))
    }
    @Test fun outputPreservesSignedSamplesAndDuration() {
        assertArrayEquals(byteArrayOf(-1,-1,-1,-1,2,0,2,0),LinkPcm.output(byteArrayOf(-1,-1,2,0),24000))
    }
}
