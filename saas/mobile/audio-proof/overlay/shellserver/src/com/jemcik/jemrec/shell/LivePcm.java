package com.ontrack.voiceproof.shell;

import android.media.AudioManager;
import android.media.AudioTrack;
import android.media.MediaCodec;
import com.genymobile.scrcpy.audio.AudioCapture;
import java.io.DataInputStream;
import java.io.DataOutputStream;
import java.io.IOException;
import java.net.Socket;
import java.nio.ByteBuffer;
import java.util.Arrays;
import java.util.concurrent.LinkedBlockingQueue;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicBoolean;
import java.util.concurrent.atomic.AtomicInteger;

/**
 * Authenticated loopback: capture 48k stereo and inject 48k mono.
 * 2026-11-04 regression: the previous caller microphone was NOT muted when
 * Gemini setup failed before opening this bridge. The Android call owner
 * must open and verify this PCM header before awaiting Gemini setup; only
 * then may the UI claim an AI-controlled call. The mute is set before
 * this header is written; finally restores its prior state on ALL exits.
 * Physical VoWiFi/RIL mute and two-way audio remain device E2E checks,
 * not properties proven by successful JVM/Gradle builds.
 */
final class LivePcm {
    private static final int SLICE_BYTES = 960; // 10 ms at 48 kHz mono 16-bit PCM
    private static final int MAX_QUEUED_FRAMES = 1200;
    private static final AtomicBoolean owned = new AtomicBoolean();

    private static final class Frame {
        final int gen;
        final byte[] data;

        Frame(int gen, byte[] data) {
            this.gen = gen;
            this.data = data;
        }
    }

    static void run(Socket socket, AudioCapture capture, AudioManager audio) throws Exception {
        if (!owned.compareAndSet(false, true)) throw new IOException("live bridge already active");
        AtomicBoolean active = new AtomicBoolean(true);
        AtomicInteger playbackGen = new AtomicInteger(0);
        LinkedBlockingQueue<Frame> playQueue = new LinkedBlockingQueue<>(MAX_QUEUED_FRAMES);
        Object trackLock = new Object();
        AudioTrack track = null;
        Thread player = null;
        boolean wasMuted = false;
        try { wasMuted = audio.isMicrophoneMute(); } catch (Throwable ignored) { }
        try {
            socket.setSoTimeout(30_000);
            socket.setTcpNoDelay(true);
            socket.setPerformancePreferences(0, 2, 1);
            DataInputStream in = new DataInputStream(socket.getInputStream());
            int route = in.readUnsignedByte();
            track = route == 'S' ? GreetingPlayer.screeningTrack(audio) : GreetingPlayer.musicTrack(audio);
            capture.checkCompatibility();
            // The modem call must not expose the owner's mic while the
            // capture engine initializes (especially over LTE/VoLTE).
            // On any failure, finally restores the exact previous mute state.
            audio.setMicrophoneMute(true);
            if (!audio.isMicrophoneMute()) throw new IOException("microphone did not mute");
            capture.start();
            track.play();
            DataOutputStream out = new DataOutputStream(socket.getOutputStream());
            out.writeInt(0x4f545631); out.writeInt(48000); out.writeInt(2); out.flush();

            Thread reader = new Thread(() -> {
                ByteBuffer pcm = ByteBuffer.allocateDirect(4096);
                MediaCodec.BufferInfo info = new MediaCodec.BufferInfo();
                byte[] bytes = new byte[4096];
                try {
                    while (active.get()) {
                        pcm.clear();
                        int n = capture.read(pcm, info);
                        if (n <= 0 || n > bytes.length || n % 4 != 0) throw new IOException("invalid live capture block");
                        pcm.position(0); pcm.get(bytes, 0, n);
                        out.writeInt(n); out.write(bytes, 0, n); out.flush();
                    }
                } catch (Throwable error) {
                    active.set(false);
                    try { socket.close(); } catch (IOException ignored) { }
                }
            }, "ontrack-live-capture");
            reader.setDaemon(true);
            reader.setUncaughtExceptionHandler((t, e) -> {
                active.set(false);
                try { socket.close(); } catch (Throwable ignored) { }
            });
            reader.start();

            final AudioTrack playbackTrack = track;
            player = new Thread(() -> {
                try {
                    while (active.get()) {
                        Frame frame;
                        try {
                            frame = playQueue.poll(100, TimeUnit.MILLISECONDS);
                        } catch (InterruptedException ignored) {
                            if (!active.get()) break;
                            continue;
                        }
                        if (frame == null) continue;
                        if (frame.gen != playbackGen.get()) continue;
                        int offset = 0;
                        while (active.get() && offset < frame.data.length && frame.gen == playbackGen.get()) {
                            int chunk = Math.min(SLICE_BYTES, frame.data.length - offset);
                            int written;
                            synchronized (trackLock) {
                                if (!active.get() || frame.gen != playbackGen.get()) break;
                                written = playbackTrack.write(frame.data, offset, chunk, AudioTrack.WRITE_BLOCKING);
                            }
                            if (written < 0) throw new IOException("call playback failed: " + written);
                            if (written == 0) continue;
                            offset += written;
                        }
                    }
                } catch (Throwable error) {
                    active.set(false);
                    try { socket.close(); } catch (Throwable ignored) { }
                }
            }, "ontrack-live-playback");
            player.setDaemon(true);
            player.setUncaughtExceptionHandler((t, e) -> {
                active.set(false);
                try { socket.close(); } catch (Throwable ignored) { }
            });
            player.start();

            byte[] bytes = new byte[65536];
            while (active.get()) {
                int size = in.readInt();
                if (size == -1) {
                    playbackGen.incrementAndGet();
                    playQueue.clear();
                    synchronized (trackLock) {
                        if (active.get()) {
                            try {
                                track.pause();
                                track.flush();
                                track.play();
                            } catch (Throwable ignored) { }
                        }
                    }
                    continue;
                }
                if (size == 0) continue;
                if (size < 0 || size > bytes.length || size % 2 != 0) throw new IOException("invalid live playback block");
                in.readFully(bytes, 0, size);
                int currentGen = playbackGen.get();
                Frame frame = new Frame(currentGen, Arrays.copyOf(bytes, size));
                if (!playQueue.offer(frame)) {
                    playQueue.poll();
                    playQueue.offer(frame);
                }
            }
        } finally {
            active.set(false);
            playbackGen.incrementAndGet();
            playQueue.clear();
            if (player != null) player.interrupt();
            try { socket.close(); } catch (IOException ignored) { }
            try { capture.stop(); } catch (Throwable ignored) { }
            if (track != null) {
                synchronized (trackLock) {
                    try { track.pause(); track.flush(); track.release(); } catch (Throwable ignored) { }
                }
            }
            try { audio.setMicrophoneMute(wasMuted); } catch (Throwable ignored) { }
            owned.set(false);
        }
    }
}
