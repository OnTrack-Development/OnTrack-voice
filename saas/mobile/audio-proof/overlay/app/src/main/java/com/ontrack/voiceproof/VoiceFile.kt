package com.ontrack.voiceproof

import android.content.Context
import android.media.AudioFormat
import android.media.MediaCodec
import android.media.MediaExtractor
import android.media.MediaFormat
import android.net.Uri
import android.os.SystemClock
import java.io.ByteArrayOutputStream

/** SAF import, locally decoded; no upload, provider key or microphone permission. */
object VoiceFile {
    private fun boundedRead(context: Context, uri: Uri, limit: Int): ByteArray =
        context.contentResolver.openInputStream(uri)!!.use { input ->
            val out = ByteArrayOutputStream()
            val buffer = ByteArray(minOf(8192, limit))
            while (out.size() < limit) {
                val count = input.read(buffer, 0, minOf(buffer.size, limit - out.size()))
                if (count < 0) break
                if (count > 0) out.write(buffer, 0, count)
            }
            out.toByteArray()
        }

    fun read(context: Context, uri: Uri): ByteArray {
        val prefix = boundedRead(context, uri, 12)
        if (prefix.size == 12 && String(prefix, 0, 4, Charsets.US_ASCII) == "RIFF") {
            val wav = boundedRead(context, uri, PcmWav.MAX_INPUT + 1)
            return PcmWav.read(wav)
        }
        val extractor = MediaExtractor()
        var codec: MediaCodec? = null
        try {
            extractor.setDataSource(context, uri, null)
            val track = (0 until extractor.trackCount).firstOrNull {
                extractor.getTrackFormat(it).getString(MediaFormat.KEY_MIME)?.startsWith("audio/") == true
            } ?: error("No supported audio track")
            extractor.selectTrack(track)
            val format = extractor.getTrackFormat(track)
            var rate = format.getInteger(MediaFormat.KEY_SAMPLE_RATE)
            var channels = format.getInteger(MediaFormat.KEY_CHANNEL_COUNT)
            require(rate in 8000..192000 && channels in 1..2) { "Unsupported audio sample format" }
            val decoder = MediaCodec.createDecoderByType(format.getString(MediaFormat.KEY_MIME)!!)
            codec = decoder
            decoder.configure(format, null, null, 0)
            decoder.start()
            val info = MediaCodec.BufferInfo()
            val out = ByteArrayOutputStream()
            var eos = false
            val deadline = SystemClock.elapsedRealtime() + 30000
            while (out.size() < rate * channels * 2 * PcmWav.MAX_SECONDS) {
                check(SystemClock.elapsedRealtime() < deadline) { "Audio decode timed out; use PCM16 WAV" }
                if (!eos) {
                    val input = decoder.dequeueInputBuffer(10000)
                    if (input >= 0) {
                        val size = extractor.readSampleData(decoder.getInputBuffer(input)!!, 0)
                        if (size < 0 || extractor.sampleTime > PcmWav.MAX_SECONDS * 1000000L) {
                            decoder.queueInputBuffer(input, 0, 0, 0, MediaCodec.BUFFER_FLAG_END_OF_STREAM)
                            eos = true
                        } else {
                            decoder.queueInputBuffer(input, 0, size, extractor.sampleTime, 0)
                            extractor.advance()
                        }
                    }
                }
                when (val index = decoder.dequeueOutputBuffer(info, 10000)) {
                    MediaCodec.INFO_OUTPUT_FORMAT_CHANGED -> {
                        val decoded = decoder.outputFormat
                        rate = decoded.getInteger(MediaFormat.KEY_SAMPLE_RATE)
                        channels = decoded.getInteger(MediaFormat.KEY_CHANNEL_COUNT)
                        require(rate in 8000..192000 && channels in 1..2) { "Unsupported decoded sample format" }
                        require(!decoded.containsKey(MediaFormat.KEY_PCM_ENCODING) ||
                            decoded.getInteger(MediaFormat.KEY_PCM_ENCODING) == AudioFormat.ENCODING_PCM_16BIT) {
                            "Decoder did not produce PCM16; export PCM16 WAV"
                        }
                    }
                    else -> if (index >= 0) {
                        try {
                            if (info.size > 0) {
                                val bytes = decoder.getOutputBuffer(index)!!
                                bytes.position(info.offset)
                                val remaining = minOf(info.size, PcmWav.MAX_INPUT - out.size(),
                                    rate * channels * 2 * PcmWav.MAX_SECONDS - out.size())
                                val block = ByteArray(remaining.coerceAtLeast(0))
                                bytes.get(block)
                                out.write(block)
                            }
                        } finally { decoder.releaseOutputBuffer(index, false) }
                        if (info.flags and MediaCodec.BUFFER_FLAG_END_OF_STREAM != 0) break
                    }
                }
            }
            return PcmWav.convert(out.toByteArray(), rate, channels)
        } finally {
            runCatching { codec?.stop() }
            codec?.release()
            extractor.release()
        }
    }
}
