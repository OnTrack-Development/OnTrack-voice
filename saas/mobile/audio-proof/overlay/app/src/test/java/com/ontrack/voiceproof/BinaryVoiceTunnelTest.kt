package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class BinaryVoiceTunnelTest {

    @Test
    fun testEmotionalMicroCacheStorageAndRetrieval() {
        EmotionalMicroCache.clear()
        val dummyPcm = ByteArray(640) { (it % 128).toByte() }
        EmotionalMicroCache.put("Aoede", "اممم..", dummyPcm)

        val retrieved = EmotionalMicroCache.get("Aoede", "اممم..")
        assertNotNull(retrieved)
        assertEquals(640, retrieved!!.size)
        assertArrayEquals(dummyPcm, retrieved)

        val randomFiller = EmotionalMicroCache.getRandomFiller("Aoede")
        assertNotNull(randomFiller)
        assertEquals(640, randomFiller!!.size)

        EmotionalMicroCache.clear()
        assertNull(EmotionalMicroCache.get("Aoede", "اممم.."))
    }

    @Test
    fun testDownsample16kTo8kLogic() {
        // 16kHz 16-bit mono = 320 samples = 640 bytes
        // 8kHz 16-bit mono = 160 samples = 320 bytes
        val pcm16k = ByteArray(640) { it.toByte() }
        val out8k = ByteArray(pcm16k.size / 2)
        var j = 0
        for (i in 0 until pcm16k.size - 3 step 4) {
            out8k[j] = pcm16k[i]
            out8k[j + 1] = pcm16k[i + 1]
            j += 2
        }

        assertEquals(320, out8k.size)
        assertEquals(pcm16k[0], out8k[0])
        assertEquals(pcm16k[1], out8k[1])
        assertEquals(pcm16k[4], out8k[2])
        assertEquals(pcm16k[5], out8k[3])
    }

    @Test
    fun testStandardFillerListCompleteness() {
        val fillers = EmotionalMicroCache.STANDARD_FILLERS
        assertTrue(fillers.size >= 5)
        assertTrue(fillers.contains("اممم.."))
        assertTrue(fillers.contains("تمام.."))
    }
}
