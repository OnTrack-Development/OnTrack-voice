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
    const val CELLULAR_MAX_PENDING_BYTES = 64L * 1024
    const val OTHER_MAX_PENDING_BYTES = 192L * 1024

    fun canSend(cellular: Boolean, pendingBytes: Long): Boolean {
        require(pendingBytes >= 0)
        return pendingBytes < if (cellular) CELLULAR_MAX_PENDING_BYTES else OTHER_MAX_PENDING_BYTES
    }
}

/**
 * Downlink PCM is 48kHz mono signed 16-bit; each packet contains 25ms.
 * Gemini websocket messages may batch seconds of audio. A 10-packet channel
 * (250ms) is NOT a valid playback jitter buffer and audibly drops syllables.
 * 800 packets = 20 seconds / <2 MiB, enough to preserve natural utterances.
 */
internal object LivePlaybackQueuePolicy {
    const val PACKET_BYTES = 2400
    const val OUTPUT_RATE = 48000
    const val MAX_PACKETS = 800
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
