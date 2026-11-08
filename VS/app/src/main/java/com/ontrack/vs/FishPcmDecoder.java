package com.ontrack.vs;

import android.media.MediaCodec;
import android.media.MediaExtractor;
import android.media.MediaFormat;
import android.media.MediaDataSource;
import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.nio.ByteBuffer;
import java.util.Arrays;

/**
 * Memory-only Fish MP3 -> PCM16LE, mono, 48kHz for the VS-only shell uplink.
 * No files and no recording of received call audio.
 */
public final class FishPcmDecoder {
    private FishPcmDecoder() {}

    private static final class Source extends MediaDataSource {
        private byte[] payload;
        Source(byte[] data) { payload = data; }
        @Override public synchronized int readAt(long position, byte[] buffer, int offset, int size)
                throws IOException {
            if (payload == null) return -1;
            if (position < 0 || position >= payload.length) return -1;
            int count = (int)Math.min((long)size, payload.length - position);
            System.arraycopy(payload, (int)position, buffer, offset, count);
            return count;
        }
        @Override public synchronized long getSize() { return payload == null ? 0 : payload.length; }
        @Override public synchronized void close() { payload = null; }
    }

    public static byte[] decodeTo48kMono(byte[] mp3) throws Exception {
        if (mp3 == null || mp3.length == 0) throw new IOException("Fish audio is empty");
        MediaExtractor extractor = new MediaExtractor();
        Source source = new Source(mp3);
        MediaCodec codec = null;
        ByteArrayOutputStream output = new ByteArrayOutputStream(128000);
        try {
            extractor.setDataSource(source);
            int track = -1;
            for (int i = 0; i < extractor.getTrackCount(); i++) {
                if (extractor.getTrackFormat(i).getString(MediaFormat.KEY_MIME).startsWith("audio/")) {
                    track = i; break;
                }
            }
            if (track < 0) throw new IOException("Fish response has no audio track");
            extractor.selectTrack(track);
            MediaFormat format = extractor.getTrackFormat(track);
            codec = MediaCodec.createDecoderByType(format.getString(MediaFormat.KEY_MIME));
            codec.configure(format, null, null, 0);
            codec.start();
            int sampleRate = format.containsKey(MediaFormat.KEY_SAMPLE_RATE)
                ? format.getInteger(MediaFormat.KEY_SAMPLE_RATE) : 24000;
            int channels = format.containsKey(MediaFormat.KEY_CHANNEL_COUNT)
                ? format.getInteger(MediaFormat.KEY_CHANNEL_COUNT) : 1;
            boolean inputDone = false;
            boolean outputDone = false;
            MediaCodec.BufferInfo info = new MediaCodec.BufferInfo();
            int idle = 0;
            while (!outputDone && idle++ < 1000) {
                if (!inputDone) {
                    int index = codec.dequeueInputBuffer(10000);
                    if (index >= 0) {
                        ByteBuffer buf = codec.getInputBuffer(index);
                        buf.clear();
                        int size = extractor.readSampleData(buf, 0);
                        if (size < 0) {
                            codec.queueInputBuffer(index, 0, 0, 0,
                                MediaCodec.BUFFER_FLAG_END_OF_STREAM);
                            inputDone = true;
                        } else {
                            codec.queueInputBuffer(index, 0, size,
                                extractor.getSampleTime(), 0);
                            extractor.advance();
                        }
                    }
                }
                int index = codec.dequeueOutputBuffer(info, 10000);
                if (index == MediaCodec.INFO_OUTPUT_FORMAT_CHANGED) {
                    MediaFormat actual = codec.getOutputFormat();
                    sampleRate = actual.getInteger(MediaFormat.KEY_SAMPLE_RATE);
                    channels = actual.getInteger(MediaFormat.KEY_CHANNEL_COUNT);
                    if (actual.containsKey(MediaFormat.KEY_PCM_ENCODING)
                        && actual.getInteger(MediaFormat.KEY_PCM_ENCODING) != 2)
                        throw new IOException("Fish decoder returned non-PCM16");
                } else if (index >= 0) {
                    if (info.size > 0) {
                        ByteBuffer buffer = codec.getOutputBuffer(index);
                        buffer.position(info.offset);
                        buffer.limit(info.offset + info.size);
                        resample(buffer, output, sampleRate, channels);
                        if (output.size() > 48_000 * 2 * 45)
                            throw new IOException("Fish output too long for one live segment");
                    }
                    outputDone = (info.flags & MediaCodec.BUFFER_FLAG_END_OF_STREAM) != 0;
                    codec.releaseOutputBuffer(index, false);
                }
            }
            if (!outputDone) throw new IOException("Fish PCM decoder timeout");
            return output.toByteArray();
        } finally {
            if (codec != null) {
                try { codec.stop(); } catch (Exception ignored) {}
                try { codec.release(); } catch (Exception ignored) {}
            }
            try { extractor.release(); } catch (Exception ignored) {}
            try { source.close(); } catch (Exception ignored) {}
            Arrays.fill(mp3, (byte)0);
        }
    }

    private static void resample(ByteBuffer src, ByteArrayOutputStream out, int rate, int channels)
            throws IOException {
        if (rate < 8000 || rate > 96000 || channels < 1 || channels > 2)
            throw new IOException("Unsupported Fish decoded audio format");
        int frames = src.remaining() / (2 * channels);
        if (frames < 1) return;
        short[] mono = new short[frames];
        for (int i = 0; i < frames; i++) {
            int sum = 0;
            for (int ch = 0; ch < channels; ch++) {
                int l = src.get() & 255;
                int h = src.get();
                sum += (short)((h << 8) | l);
            }
            mono[i] = (short)(sum / channels);
        }
        int framesOut = (int)((long)frames * 48000 / rate);
        for (int i = 0; i < framesOut; i++) {
            double position = i * (double)rate / 48000;
            int left = Math.min(frames - 1, (int)position);
            int right = Math.min(frames - 1, left + 1);
            double fraction = position - left;
            int sample = (int)Math.round(mono[left] * (1 - fraction) + mono[right] * fraction);
            out.write(sample & 255);
            out.write((sample >>> 8) & 255);
        }
        Arrays.fill(mono, (short)0);
    }
}
