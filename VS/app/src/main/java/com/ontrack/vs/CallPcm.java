package com.ontrack.vs;

/**
 * Port of OnTrack Voice LinkPcm channel selection and 48k-to-16k transform.
 * No network, storage, or Android dependencies; in-memory transient audio only.
 */
public final class CallPcm {
    private CallPcm() {}
    public static byte[] fromStereo48k(byte[] stereo, boolean right) {
        if (stereo == null || stereo.length < 12 || stereo.length % 4 != 0)
            throw new IllegalArgumentException("Expected PCM16 48k stereo frames");
        int frames = stereo.length / 4;
        int count = frames / 3;
        byte[] out = new byte[count * 2];
        int channel = right ? 2 : 0;
        for (int i = 0; i < count; i++) {
            int sum = 0;
            for (int frame = 0; frame < 3; frame++) {
                int at = (i * 3 + frame) * 4 + channel;
                int sample = (short) ((stereo[at] & 255) | (stereo[at + 1] << 8));
                sum += sample;
            }
            int v = sum / 3;
            out[i * 2] = (byte) v;
            out[i * 2 + 1] = (byte) (v >>> 8);
        }
        return out;
    }
}
