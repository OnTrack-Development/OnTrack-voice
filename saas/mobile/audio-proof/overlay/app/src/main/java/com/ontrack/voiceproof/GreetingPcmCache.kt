package com.ontrack.voiceproof

import android.content.Context
import java.io.File
import java.security.MessageDigest
import java.util.concurrent.ConcurrentHashMap

/**
 * Pre-caches pre-rendered static greeting PCM (48kHz mono 16-bit) locally
 * on disk and in memory so incoming SIM calls speak immediately upon answer (< 50ms)
 * without waiting for WebSocket handshake or TTS network round-trip.
 */
internal object GreetingPcmCache {
    private val memoryCache = ConcurrentHashMap<String, ByteArray>()
    private const val MAX_CACHE_BYTES = 5 * 1024 * 1024 // 5 MB RAM budget

    fun key(agentId: String, voice: String, greetingText: String): String {
        val raw = "${agentId.trim()}:${voice.trim()}:${greetingText.trim()}"
        val digest = MessageDigest.getInstance("SHA-256").digest(raw.toByteArray(Charsets.UTF_8))
        return digest.joinToString("") { "%02x".format(it) }
    }

    fun get(context: Context?, agentId: String, voice: String, greetingText: String): ByteArray? {
        if (greetingText.isBlank()) return null
        val cacheKey = key(agentId, voice, greetingText)
        memoryCache[cacheKey]?.let { return it }

        if (context != null) {
            val file = cacheFile(context, cacheKey)
            if (file.isFile && file.length() > 0 && file.length() <= 48000L * 2 * 30) {
                val bytes = runCatching { file.readBytes() }.getOrNull()
                if (bytes != null && bytes.size % 2 == 0) {
                    memoryCache[cacheKey] = bytes
                    return bytes
                }
            }
        }
        return null
    }

    fun put(context: Context?, agentId: String, voice: String, greetingText: String, pcm: ByteArray) {
        if (greetingText.isBlank() || pcm.isEmpty() || pcm.size % 2 != 0) return
        val cacheKey = key(agentId, voice, greetingText)
        if (pcm.size <= MAX_CACHE_BYTES) {
            memoryCache[cacheKey] = pcm
        }
        if (context != null) {
            runCatching {
                val file = cacheFile(context, cacheKey)
                file.parentFile?.mkdirs()
                file.writeBytes(pcm)
            }
        }
    }

    fun has(context: Context?, agentId: String, voice: String, greetingText: String): Boolean =
        get(context, agentId, voice, greetingText) != null

    fun prewarm(
        context: Context?,
        slot: Int,
        agentId: String,
        voice: String,
        greetingText: String,
        fetcher: (text: String, keepRunning: () -> Boolean, onChunk: (ByteArray) -> Unit) -> Unit
    ) {
        if (greetingText.isBlank()) return
        if (has(context, agentId, voice, greetingText)) return
        val resampler = PremiumPcm(24000)
        val streamBuffer = java.io.ByteArrayOutputStream()
        runCatching {
            fetcher(greetingText, { true }) { chunk ->
                val pcm = resampler.convert(chunk)
                if (pcm.isNotEmpty()) {
                    streamBuffer.write(pcm)
                }
            }
            val pcmBytes = streamBuffer.toByteArray()
            if (pcmBytes.isNotEmpty() && pcmBytes.size % 2 == 0) {
                put(context, agentId, voice, greetingText, pcmBytes)
            }
        }
    }

    private fun cacheFile(context: Context, cacheKey: String): File {
        // Persistent internal storage so Android OS never clears it on low disk cache
        val dir = File(context.filesDir, "greeting_pcm")
        return File(dir, "$cacheKey.pcm")
    }

    fun clear() {
        memoryCache.clear()
    }
}
