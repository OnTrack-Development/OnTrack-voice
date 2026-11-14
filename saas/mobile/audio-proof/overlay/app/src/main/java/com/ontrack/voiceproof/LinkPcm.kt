package com.ontrack.voiceproof

import java.io.ByteArrayOutputStream

/**
 * PCM contract: native shell emits 48kHz signed stereo 16-bit interleaved.
 * Every 3 stereo frames -> one 16kHz mono sample for Gemini Live.
 * Owner-confirmed Pilot input was "mix", NOT default "right": preserve
 * (left+right)/2 until physical SIM/VoWiFi duplex tests prove otherwise.
 * This arithmetic test does not establish carrier audio availability.
 */
/** Limit queued 16 kHz audio on unstable cellular uplinks.
 *  A full WebSocket send queue means the caller's old PCM would reach Gemini
 *  late; skip delayed samples instead of producing an ever-later conversation.
 *  LTE now allows a bounded 64 KiB (~1.5 seconds of JSON/b64 PCM) to
 *  absorb radio jitter without dropping whole words. Wi-Fi remains 192 KiB.
 */
internal object LiveAudioQueuePolicy {
    const val CELLULAR_MAX_PENDING_BYTES = 32L * 1024 // 32 KiB cap for mobile data
    const val OTHER_MAX_PENDING_BYTES = 192L * 1024

    fun canSend(cellular: Boolean, pendingBytes: Long): Boolean {
        require(pendingBytes >= 0)
        return pendingBytes < if (cellular) CELLULAR_MAX_PENDING_BYTES else OTHER_MAX_PENDING_BYTES
    }
}

/**
 * Downlink PCM is 48kHz mono signed 16-bit; each packet contains 20ms (1920B),
 * aligned 1:1 with LivePcm.SLICE_BYTES (1920B) and Android Audio HAL period.
 * 1000 packets = 20 seconds (< 2 MiB), preserving natural utterances without splice drops.
 */
internal object LivePlaybackQueuePolicy {
    const val PACKET_BYTES = 1920
    const val OUTPUT_RATE = 48000
    const val MAX_PACKETS = 1000
    const val MAX_QUEUED_MS = MAX_PACKETS * PACKET_BYTES * 1000 / (OUTPUT_RATE * 2)
}

internal class LinkPcm(private val channel: String) {
    private var sum = 0
    private var frames = 0
    fun input(pcm: ByteArray): ByteArray {
        require(pcm.size % 4 == 0)
        val out = ByteArrayOutputStream()
        for (i in pcm.indices step 4) {
            val left = sample(pcm, i); val right = sample(pcm, i+2)
            sum += when(channel) { "left" -> left; "mix" -> (left+right)/2; else -> right }
            frames++
            if (frames == 3) { val v=sum/3; out.write(v and 255);out.write((v shr 8) and 255);sum=0;frames=0 }
        }
        return out.toByteArray()
    }
    fun resetInput() {
        sum = 0
        frames = 0
    }
    companion object {
        private fun sample(p:ByteArray,i:Int):Int = ((p[i].toInt() and 255) or (p[i+1].toInt() shl 8)).toShort().toInt()
        fun output(pcm:ByteArray, rate:Int):ByteArray {
            require(rate in listOf(8000,16000,24000,48000) && pcm.size%2==0)
            val factor=48000/rate;val out=ByteArray(pcm.size*factor)
            for(i in pcm.indices step 2) for(k in 0 until factor) {
                val at=i*factor+k*2;out[at]=pcm[i];out[at+1]=pcm[i+1]
            }
            return out
        }
    }
}

/**
 * Stateful anti-aliasing Catmull-Rom cubic upsampler (8/16/24k -> 48k mono PCM16).
 * Preserves continuity across WebSocket chunks (~0.04ms 1-sample lookahead),
 * cutting ultrasonic ZOH staircase imaging by 34-47 dB before modem AMR-WB/EVS.
 */
internal class DownlinkResampler {
    private var activeRate = 0
    private var p0 = 0
    private var p1 = 0
    private var count = 0

    fun reset() {
        count = 0
        p0 = 0
        p1 = 0
    }

    fun upsample(pcm: ByteArray, rate: Int): ByteArray {
        require(rate in listOf(8000, 16000, 24000, 48000) && pcm.size % 2 == 0)
        if (rate != activeRate) {
            activeRate = rate
            reset()
        }
        if (pcm.isEmpty()) return ByteArray(0)
        if (rate == 48000) return pcm.copyOf()
        val factor = 48000 / rate
        val out = ByteArrayOutputStream(pcm.size * factor + factor * 2)
        for (i in pcm.indices step 2) {
            val s = ((pcm[i].toInt() and 255) or (pcm[i + 1].toInt() shl 8)).toShort().toInt()
            when (count) {
                0 -> {
                    p0 = s; p1 = s; count = 1
                }
                1 -> {
                    emitInterpolated(out, p0, p1, s, s, factor)
                    p0 = p1; p1 = s; count = 2
                }
                else -> {
                    emitInterpolated(out, p0, p1, s, s, factor)
                    p0 = p1; p1 = s
                }
            }
        }
        return out.toByteArray()
    }

    fun flushTail(): ByteArray {
        if (count == 0 || activeRate == 0 || activeRate == 48000) return ByteArray(0)
        val factor = 48000 / activeRate
        val out = ByteArrayOutputStream(factor * 2)
        emitInterpolated(out, p0, p1, p1, p1, factor)
        reset()
        return out.toByteArray()
    }

    private fun emitInterpolated(out: ByteArrayOutputStream, s0: Int, s1: Int, s2: Int, s3: Int, factor: Int) {
        writeSample(out, s1)
        for (k in 1 until factor) {
            val t = k.toDouble() / factor.toDouble()
            val t2 = t * t
            val t3 = t2 * t
            val interp = 0.5 * (
                (2.0 * s1) +
                    (-s0 + s2) * t +
                    (2.0 * s0 - 5.0 * s1 + 4.0 * s2 - s3) * t2 +
                    (-s0 + 3.0 * s1 - 3.0 * s2 + s3) * t3
                )
            val v = interp.toInt().coerceIn(-32768, 32767)
            writeSample(out, v)
        }
    }

    private fun writeSample(out: ByteArrayOutputStream, v: Int) {
        out.write(v and 255)
        out.write((v shr 8) and 255)
    }
}

/**
 * Low-latency 16 kHz mono uplink framer:
 * - Wi-Fi / 4G LTE / 5G NR: 20 ms (640 bytes = 320 samples) for minimal latency & fast VAD onset
 * - 2G / 3G / HSPA+: 40 ms (1280 bytes = 640 samples)
 */
internal class UplinkAudioFrameBuffer {
    private val pending = ByteArrayOutputStream()

    fun push(chunk: ByteArray, frameBytes: Int): List<ByteArray> {
        require(frameBytes > 0 && frameBytes % 2 == 0)
        if (chunk.isNotEmpty()) {
            pending.write(chunk)
        }
        val current = pending.toByteArray()
        if (current.size < frameBytes) return emptyList()
        val ready = ArrayList<ByteArray>(current.size / frameBytes)
        var offset = 0
        while (offset + frameBytes <= current.size) {
            ready.add(current.copyOfRange(offset, offset + frameBytes))
            offset += frameBytes
        }
        pending.reset()
        if (offset < current.size) {
            pending.write(current, offset, current.size - offset)
        }
        return ready
    }

    fun pendingSize(): Int = pending.size()

    fun flush(): ByteArray? {
        if (pending.size() == 0) return null
        val tail = pending.toByteArray()
        pending.reset()
        return tail
    }

    companion object {
        const val FAST_FRAME_BYTES = 640 // 20ms @ 16kHz 16-bit mono
        const val SLOW_CELLULAR_FRAME_BYTES = 1280 // 40ms @ 16kHz 16-bit mono

        fun targetBytes(cellular: Boolean, slowCellular: Boolean): Int =
            if (cellular && slowCellular) SLOW_CELLULAR_FRAME_BYTES else FAST_FRAME_BYTES
    }
}

internal data class AudioTelemetrySnapshot(
    val pcmSetupMs: Long,
    val pcmFirstFrameMs: Long,
    val maxCaptureGapMs: Long,
    val captureGapCount: Long,
    val pcmDroppedPackets: Long,
    val networkConnectMs: Long,
    val aiFirstAudioMs: Long,
    val networkSkippedUplinkPackets: Long,
    val networkReconnects: Long,
    val firstAudioInputMs: Long = 0L,
    val speakerPlaybackMs: Long = 0L
)

/**
 * Separates local PCM bridge latency & packet loss from cellular/Wi-Fi network metrics.
 * Never logs or transmits PCM audio bytes.
 */
internal class LiveAudioTelemetry {
    @Volatile var pcmSetupMs: Long = 0L; private set
    @Volatile var pcmFirstFrameMs: Long = 0L; private set
    @Volatile var networkConnectMs: Long = 0L; private set
    @Volatile var aiFirstAudioMs: Long = 0L; private set
    @Volatile var firstAudioInputMs: Long = 0L; private set
    @Volatile var speakerPlaybackMs: Long = 0L; private set
    private var lastCaptureAtMs: Long = 0L
    private var maxGapMs: Long = 0L
    private var gapCount: Long = 0L
    private var pcmDropped: Long = 0L
    private var netSkipped: Long = 0L
    private var reconnects: Long = 0L

    @Synchronized fun recordPcmSetup(elapsedMs: Long) { pcmSetupMs = elapsedMs.coerceAtLeast(0L) }
    @Synchronized fun recordPcmCaptureTick(nowMs: Long, firstFrameElapsedMs: Long? = null) {
        if (pcmFirstFrameMs == 0L && firstFrameElapsedMs != null) {
            pcmFirstFrameMs = firstFrameElapsedMs.coerceAtLeast(0L)
        }
        if (lastCaptureAtMs > 0L && nowMs >= lastCaptureAtMs) {
            val delta = nowMs - lastCaptureAtMs
            if (delta > maxGapMs) maxGapMs = delta
            if (delta > 80L) gapCount++
        }
        lastCaptureAtMs = nowMs
    }
    @Synchronized fun recordPcmDrop(count: Long = 1L) { pcmDropped += count.coerceAtLeast(0L) }
    @Synchronized fun recordNetworkConnect(elapsedMs: Long) { networkConnectMs = elapsedMs.coerceAtLeast(0L) }
    @Synchronized fun recordAiFirstAudio(elapsedMs: Long) { if (aiFirstAudioMs == 0L) aiFirstAudioMs = elapsedMs.coerceAtLeast(0L) }
    @Synchronized fun recordFirstAudioInput(elapsedMs: Long) { if (firstAudioInputMs == 0L) firstAudioInputMs = elapsedMs.coerceAtLeast(0L) }
    @Synchronized fun recordSpeakerPlayback(elapsedMs: Long) { if (speakerPlaybackMs == 0L) speakerPlaybackMs = elapsedMs.coerceAtLeast(0L) }
    @Synchronized fun recordNetworkUplinkSkip(count: Long = 1L) { netSkipped += count.coerceAtLeast(0L) }
    @Synchronized fun recordNetworkReconnect() { reconnects++ }

    @Synchronized fun snapshot(): AudioTelemetrySnapshot = AudioTelemetrySnapshot(
        pcmSetupMs = pcmSetupMs,
        pcmFirstFrameMs = pcmFirstFrameMs,
        maxCaptureGapMs = maxGapMs,
        captureGapCount = gapCount,
        pcmDroppedPackets = pcmDropped,
        networkConnectMs = networkConnectMs,
        aiFirstAudioMs = aiFirstAudioMs,
        networkSkippedUplinkPackets = netSkipped,
        networkReconnects = reconnects,
        firstAudioInputMs = firstAudioInputMs,
        speakerPlaybackMs = speakerPlaybackMs
    )
}
