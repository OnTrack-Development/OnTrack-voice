package com.ontrack.voiceproof.shell;

import android.media.AudioManager;
import android.media.AudioTrack;
import android.media.MediaCodec;
import android.os.Process;
import android.os.SystemClock;
import com.genymobile.scrcpy.audio.AudioCapture;
import java.io.DataInputStream;
import java.io.DataOutputStream;
import java.io.IOException;
import java.net.Socket;
import java.nio.ByteBuffer;
import java.util.concurrent.ArrayBlockingQueue;
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
    private static final int SLICE_BYTES = 1920; // 20 ms at 48 kHz mono 16-bit PCM (matches HAL period)
    private static final int MAX_QUEUED_FRAMES = 1000;
    private static final long PLAYBACK_STALL_TIMEOUT_MS = 2500L;
    private static final AtomicBoolean owned = new AtomicBoolean();

    private static final ArrayBlockingQueue<byte[]> bufferPool = new ArrayBlockingQueue<>(128);

    private static byte[] obtainBuffer(int minCapacity) {
        byte[] b = bufferPool.poll();
        if (b != null && b.length >= minCapacity) return b;
        return new byte[Math.max(minCapacity, 8192)];
    }

    private static void releaseBuffer(byte[] b) {
        if (b != null && b.length <= 65536) bufferPool.offer(b);
    }

    private static final class Frame {
        final int gen;
        final int length;
        final byte[] data;

        Frame(int gen, int length, byte[] data) {
            this.gen = gen;
            this.length = length;
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
            // Prime HAL uplink ring buffer with 160ms silence so first AI syllable is not clipped
            byte[] preRoll = new byte[8 * SLICE_BYTES];
            track.write(preRoll, 0, preRoll.length, AudioTrack.WRITE_BLOCKING);
            DataOutputStream out = new DataOutputStream(socket.getOutputStream());
            out.writeInt(0x4f545631); out.writeInt(48000); out.writeInt(2); out.flush();

            Thread reader = new Thread(() -> {
                try { Process.setThreadPriority(Process.THREAD_PRIORITY_URGENT_AUDIO); } catch (Throwable ignored) { }
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
                try { Process.setThreadPriority(Process.THREAD_PRIORITY_URGENT_AUDIO); } catch (Throwable ignored) { }
                try {
                    while (active.get()) {
                        Frame frame;
                        try {
                            frame = playQueue.poll(25, TimeUnit.MILLISECONDS);
                        } catch (InterruptedException ignored) {
                            if (!active.get()) break;
                            continue;
                        }
                        if (frame == null) continue;
                        if (frame.gen != playbackGen.get()) {
                            releaseBuffer(frame.data);
                            continue;
                        }
                        int offset = 0;
                        long stallStartMs = 0L;
                        while (active.get() && offset < frame.length && frame.gen == playbackGen.get()) {
                            int chunk = Math.min(SLICE_BYTES, frame.length - offset);
                            int written;
                            // Blocking write syncs cleanly with AudioTrack hardware clock;
                            // barge-in interruption is handled immediately via track.pause()/flush().
                            synchronized (trackLock) {
                                if (!active.get() || frame.gen != playbackGen.get()) break;
                                written = playbackTrack.write(frame.data, offset, chunk, AudioTrack.WRITE_BLOCKING);
                            }
                            if (written < 0) throw new IOException("call playback failed: " + written);
                            if (written == 0) {
                                long nowMs = SystemClock.elapsedRealtime();
                                if (stallStartMs == 0L) {
                                    stallStartMs = nowMs;
                                } else if (nowMs - stallStartMs > PLAYBACK_STALL_TIMEOUT_MS) {
                                    throw new IOException("call playback stalled");
                                }
                                Thread.sleep(2);
                                continue;
                            }
                            stallStartMs = 0L;
                            offset += written;
                        }
                        releaseBuffer(frame.data);
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
                    Frame dropped;
                    while ((dropped = playQueue.poll()) != null) {
                        releaseBuffer(dropped.data);
                    }
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
                byte[] frameBuf = obtainBuffer(size);
                System.arraycopy(bytes, 0, frameBuf, 0, size);
                Frame frame = new Frame(currentGen, size, frameBuf);
                // Zero-loss audio queue: never drop oldest frame during active speech turn.
                while (active.get() && currentGen == playbackGen.get()) {
                    if (playQueue.offer(frame, 100, TimeUnit.MILLISECONDS)) break;
                }
            }
        } finally {
            active.set(false);
            playbackGen.incrementAndGet();
            Frame dropped;
            while ((dropped = playQueue.poll()) != null) {
                releaseBuffer(dropped.data);
            }
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
