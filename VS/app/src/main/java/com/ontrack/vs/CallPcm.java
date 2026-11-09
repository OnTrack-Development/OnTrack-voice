package com.ontrack.vs;

import java.io.ByteArrayOutputStream;

/**
 * Same persistent stereo-48k to mono-16k channel selection and frame averaging
 * as OnTrack Voice LinkPcm. A short shell packet must not reset the 3-frame
 * resampler state. This class holds transient PCM only, never records calls.
 */
public final class CallPcm {
    private final String channel;
    private int sum;
    private int frames;

    public CallPcm(String channel) {
        if (!"left".equals(channel) && !"right".equals(channel) && !"mix".equals(channel))
            throw new IllegalArgumentException("PCM input channel must be left, right or mix");
        this.channel = channel;
    }

    public synchronized byte[] input(byte[] pcm) {
        if (pcm == null || pcm.length % 4 != 0)
            throw new IllegalArgumentException("Expected stereo PCM16 frames");
        ByteArrayOutputStream out = new ByteArrayOutputStream(pcm.length / 6 + 4);
        for (int i = 0; i < pcm.length; i += 4) {
            int left = sample(pcm, i);
            int right = sample(pcm, i + 2);
            sum += "left".equals(channel) ? left
                : "mix".equals(channel) ? (left + right) / 2 : right;
            frames++;
            if (frames == 3) {
                int value = sum / 3;
                out.write(value & 255);
                out.write((value >> 8) & 255);
                sum = 0;
                frames = 0;
            }
        }
        return out.toByteArray();
    }

    private static int sample(byte[] p, int i) {
        return (short)((p[i] & 255) | (p[i + 1] << 8));
    }

    /** OnTrack LinkPcm's 8/16/24/48k to 48k sample duplication, if needed. */
    public static byte[] output(byte[] pcm, int rate) {
        if (pcm == null || pcm.length % 2 != 0
                || (rate != 8000 && rate != 16000 && rate != 24000 && rate != 48000))
            throw new IllegalArgumentException("Invalid 16-bit mono PCM output");
        int factor = 48000 / rate;
        byte[] out = new byte[pcm.length * factor];
        for (int i = 0; i < pcm.length; i += 2) {
            for (int k = 0; k < factor; k++) {
                int at = i * factor + k * 2;
                out[at] = pcm[i];
                out[at + 1] = pcm[i + 1];
            }
        }
        return out;
    }
}
