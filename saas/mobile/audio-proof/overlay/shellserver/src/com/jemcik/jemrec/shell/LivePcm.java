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
import java.util.concurrent.atomic.AtomicBoolean;

/** Authenticated loopback only: capture 48k stereo, inject 48k mono. */
final class LivePcm {
    private static final AtomicBoolean owned = new AtomicBoolean();

    static void run(Socket socket, AudioCapture capture, AudioManager audio) throws Exception {
        if (!owned.compareAndSet(false, true)) throw new IOException("live bridge already active");
        AtomicBoolean active = new AtomicBoolean(true);
        AudioTrack track = null;
        // Never mute the device microphone: it is also the call's GSM uplink.
        // A captured playback/submix stream does not prove remote caller audio.
        try {
            socket.setSoTimeout(30_000);
            DataInputStream in = new DataInputStream(socket.getInputStream());
            int route = in.readUnsignedByte();
            track = route == 'S' ? GreetingPlayer.screeningTrack(audio) : GreetingPlayer.musicTrack(audio);
            capture.checkCompatibility();
            capture.start();
            // Deliberately preserve the caller uplink. Muting it made the AI audible
            // but prevented the handset from transmitting caller speech.
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
            reader.setDaemon(true); reader.start();
            byte[] bytes = new byte[65536];
            while (active.get()) {
                int size = in.readInt();
                if (size == -1) { track.pause(); track.flush(); track.play(); continue; }
                if (size < 0 || size > bytes.length || size % 2 != 0) throw new IOException("invalid live playback block");
                in.readFully(bytes, 0, size);
                int pos = 0;
                while (pos < size) {
                    int n = track.write(bytes, pos, size-pos, AudioTrack.WRITE_BLOCKING);
                    if (n <= 0) throw new IOException("call playback failed: " + n);
                    pos += n;
                }
            }
        } finally {
            active.set(false);
            try { socket.close(); } catch (IOException ignored) { }
            try { capture.stop(); } catch (Throwable ignored) { }
            if (track != null) { try { track.pause(); track.flush(); track.release(); } catch (Throwable ignored) { } }
            owned.set(false);
        }
    }
}
