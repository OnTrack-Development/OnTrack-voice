package com.ontrack.voiceproof

import java.util.concurrent.ConcurrentHashMap

/**
 * Micro-cache for conversational emotional fillers, sighs, and acknowledgments
 * ("اممم..", "آه..", "تمام..", "لحظة قلبي..", "يا عيوني..").
 * Stored as raw 16-bit 24kHz/16kHz PCM for instant (<10ms) playback during thinking delay.
 */
internal object EmotionalMicroCache {
    private val fillers = ConcurrentHashMap<String, ByteArray>()

    val STANDARD_FILLERS = listOf(
        "اممم..",
        "آه..",
        "تمام..",
        "أكيد..",
        "يا عيوني..",
        "لحظة.."
    )

    fun get(voice: String, phrase: String): ByteArray? {
        val key = "${voice.trim()}:${phrase.trim()}"
        return fillers[key]
    }

    fun put(voice: String, phrase: String, pcm: ByteArray) {
        if (pcm.isNotEmpty() && pcm.size % 2 == 0) {
            val key = "${voice.trim()}:${phrase.trim()}"
            fillers[key] = pcm
        }
    }

    fun getRandomFiller(voice: String): ByteArray? {
        val matching = fillers.filterKeys { it.startsWith("${voice.trim()}:") }.values
        return if (matching.isNotEmpty()) matching.random() else null
    }

    fun size(): Int = fillers.size

    fun clear() {
        fillers.clear()
    }
}
