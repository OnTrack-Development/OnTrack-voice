package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Before
import org.junit.Test

class GreetingPcmCacheTest {
    @Before
    fun setUp() {
        GreetingPcmCache.clear()
    }

    @Test
    fun testKeyDeterministicAndDistinct() {
        val k1 = GreetingPcmCache.key("agent_1", "io.premium", "أهلاً بك في الشركة")
        val k2 = GreetingPcmCache.key("agent_1", "io.premium", "أهلاً بك في الشركة")
        val k3 = GreetingPcmCache.key("agent_2", "io.premium", "أهلاً بك في الشركة")
        val k4 = GreetingPcmCache.key("agent_1", "fish_s2", "أهلاً بك في الشركة")

        assertEquals(k1, k2)
        assertNotEquals(k1, k3)
        assertNotEquals(k1, k4)
        assertEquals(64, k1.length) // SHA-256 hex
    }

    @Test
    fun testMemoryPutAndGet() {
        val dummyPcm = ByteArray(960) { (it % 120).toByte() }
        assertNull(GreetingPcmCache.get(null, "ag1", "v1", "مرحبا"))
        assertFalse(GreetingPcmCache.has(null, "ag1", "v1", "مرحبا"))

        GreetingPcmCache.put(null, "ag1", "v1", "مرحبا", dummyPcm)
        assertTrue(GreetingPcmCache.has(null, "ag1", "v1", "مرحبا"))

        val cached = GreetingPcmCache.get(null, "ag1", "v1", "مرحبا")
        assertNotNull(cached)
        assertArrayEquals(dummyPcm, cached)
    }

    @Test
    fun testEmptyOrInvalidInputsRejected() {
        val oddBytes = ByteArray(3) { 1 }
        GreetingPcmCache.put(null, "ag1", "v1", "مرحبا", oddBytes)
        assertNull(GreetingPcmCache.get(null, "ag1", "v1", "مرحبا"))

        GreetingPcmCache.put(null, "ag1", "v1", "", ByteArray(100))
        assertNull(GreetingPcmCache.get(null, "ag1", "v1", ""))
    }
}
