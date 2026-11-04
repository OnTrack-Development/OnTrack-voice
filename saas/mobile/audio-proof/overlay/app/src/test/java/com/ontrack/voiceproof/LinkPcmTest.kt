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
    @Test fun outputPreservesSignedSamplesAndDuration() {
        assertArrayEquals(byteArrayOf(-1,-1,-1,-1,2,0,2,0),LinkPcm.output(byteArrayOf(-1,-1,2,0),24000))
    }
}
