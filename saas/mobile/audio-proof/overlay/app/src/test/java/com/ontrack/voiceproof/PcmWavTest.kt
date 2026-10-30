package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test
import java.nio.ByteBuffer
import java.nio.ByteOrder

class PcmWavTest {
    private fun wav(rate: Int, channels: Int, samples: ShortArray): ByteArray {
        val b = ByteBuffer.allocate(44 + samples.size * 2).order(ByteOrder.LITTLE_ENDIAN)
        b.put("RIFF".toByteArray()).putInt(36 + samples.size * 2).put("WAVEfmt ".toByteArray())
        b.putInt(16).putShort(1).putShort(channels.toShort()).putInt(rate)
        b.putInt(rate * channels * 2).putShort((channels * 2).toShort()).putShort(16)
        b.put("data".toByteArray()).putInt(samples.size * 2)
        samples.forEach { b.putShort(it) }
        return b.array()
    }

    @Test fun mono48kPreservesSignal() {
        val values = shortArrayOf(0, 1000, -1000, 32767, -32768)
        val out = PcmWav.read(wav(48000, 1, values))
        val decoded = ByteBuffer.wrap(out).order(ByteOrder.LITTLE_ENDIAN)
        values.forEach { assertEquals(it, decoded.short) }
    }
    @Test fun stereoIsMixedAndResampled() {
        val out = PcmWav.read(wav(8000, 2, ShortArray(16000) { if (it % 2 == 0) 1000 else -1000 }))
        assertEquals(96000, out.size)
        assertTrue(out.all { it == 0.toByte() })
    }
    @Test fun capsAtThirtySeconds() {
        assertEquals(48000 * 30 * 2, PcmWav.convert(ByteArray(48000 * 31 * 2), 48000, 1).size)
    }
    @Test fun rejectsTruncatedData() {
        val input = wav(48000, 1, shortArrayOf(1, 2))
        assertThrows(IllegalArgumentException::class.java) { PcmWav.read(input.copyOf(input.size - 1)) }
    }
    @Test fun rejectsUnsupportedOrMisalignedData() {
        assertThrows(IllegalArgumentException::class.java) { PcmWav.convert(byteArrayOf(1), 48000, 1) }
        assertThrows(IllegalArgumentException::class.java) { PcmWav.convert(byteArrayOf(1, 2), 1, 1) }
    }
}
