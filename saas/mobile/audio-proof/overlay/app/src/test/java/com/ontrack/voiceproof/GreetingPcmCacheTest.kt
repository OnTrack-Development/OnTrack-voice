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

    @Test
    fun testPrewarmPopulatesCache() {
        assertFalse(GreetingPcmCache.has(null, "ag1", "v1", "صباح الخير"))
        var fetched = false
        GreetingPcmCache.prewarm(null, 0, "ag1", "v1", "صباح الخير") { text, _, onChunk ->
            fetched = true
            onChunk(ByteArray(480) { 10 })
        }
        assertTrue(fetched)
        assertTrue(GreetingPcmCache.has(null, "ag1", "v1", "صباح الخير"))
        val pcm = GreetingPcmCache.get(null, "ag1", "v1", "صباح الخير")
        assertNotNull(pcm)
        assertTrue(pcm!!.isNotEmpty())
    }

    @Test
    fun testSlotAndAgentFallback() {
        val dummy = ByteArray(640) { 5 }
        GreetingPcmCache.put(null, "agent_x", "v_y", "مرحبا بكم", dummy, slot = 0)
        // Retrieve with exact match
        assertNotNull(GreetingPcmCache.get(null, "agent_x", "v_y", "مرحبا بكم", 0))
        // Retrieve with changed greeting text but same slot
        val bySlot = GreetingPcmCache.get(null, "agent_diff", "v_diff", "", 0)
        assertNotNull(bySlot)
        assertArrayEquals(dummy, bySlot)
        // Retrieve by agent alone
        val byAgent = GreetingPcmCache.getBySlotOrAgent(null, -1, "agent_x")
        assertNotNull(byAgent)
        assertArrayEquals(dummy, byAgent)
    }
}
