package com.ontrack.voiceproof

import java.nio.ByteBuffer
import java.nio.ByteOrder
import kotlin.math.roundToInt

/** A bounded PCM16 WAV reader. No Android dependency: exercised on the JVM. */
object PcmWav {
    const val RATE = 48000
    const val MAX_SECONDS = 30
    const val MAX_INPUT = 16 * 1024 * 1024

    fun read(wav: ByteArray): ByteArray {
        require(wav.size in 44..MAX_INPUT) { "WAV file must be between 44 bytes and 16 MB" }
        val b = ByteBuffer.wrap(wav).order(ByteOrder.LITTLE_ENDIAN)
        fun tag(offset: Int) = String(wav, offset, 4, Charsets.US_ASCII)
        require(tag(0) == "RIFF" && tag(8) == "WAVE") { "Not a RIFF/WAVE file" }
        var offset = 12
        var channels = 0
        var rate = 0
        var bits = 0
        var data: ByteArray? = null
        while (offset <= wav.size - 8) {
            val length = b.getInt(offset + 4).toLong() and 0xffffffffL
            val begin = offset + 8
            require(length <= wav.size.toLong() - begin) { "Truncated WAV chunk" }
            when (tag(offset)) {
                "fmt " -> {
                    require(length >= 16 && b.getShort(begin).toInt() == 1) { "WAV must be uncompressed PCM16" }
                    channels = b.getShort(begin + 2).toInt()
                    rate = b.getInt(begin + 4)
                    bits = b.getShort(begin + 14).toInt()
                }
                "data" -> data = wav.copyOfRange(begin, begin + length.toInt())
            }
            val next = begin.toLong() + length + (length and 1)
            if (next > wav.size || next > Int.MAX_VALUE) break
            offset = next.toInt()
        }
        require(bits == 16) { "Use 16-bit PCM WAV" }
        return convert(requireNotNull(data) { "WAV has no audio data" }, rate, channels)
    }

    fun convert(pcm: ByteArray, rate: Int, channels: Int): ByteArray {
        require(rate in 8000..192000 && channels in 1..2) { "Only mono/stereo audio at 8–192 kHz is supported" }
        require(pcm.size <= MAX_INPUT && pcm.size % (2 * channels) == 0) { "Invalid PCM frame data" }
        val frames = minOf(pcm.size / (2 * channels), rate * MAX_SECONDS)
        require(frames > 0) { "Audio is empty" }
        val source = ByteBuffer.wrap(pcm).order(ByteOrder.LITTLE_ENDIAN)
        fun sample(frame: Int): Double {
            var value = 0.0
            for (channel in 0 until channels) value += source.getShort((frame * channels + channel) * 2)
            return value / channels
        }
        val count = (frames.toLong() * RATE / rate).toInt()
        val output = ByteBuffer.allocate(count * 2).order(ByteOrder.LITTLE_ENDIAN)
        for (index in 0 until count) {
            val position = index.toDouble() * rate / RATE
            val left = position.toInt().coerceAtMost(frames - 1)
            val right = minOf(left + 1, frames - 1)
            val fraction = position - left
            val value = (sample(left) * (1 - fraction) + sample(right) * fraction).roundToInt()
            output.putShort(value.coerceIn(-32768, 32767).toShort())
        }
        return output.array()
    }
}
