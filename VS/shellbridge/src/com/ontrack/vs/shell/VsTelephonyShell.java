package com.ontrack.vs.shell;

import android.content.Context;
import android.media.AudioAttributes;
import android.media.AudioDeviceInfo;
import android.media.AudioFormat;
import android.media.AudioManager;
import android.media.AudioTrack;
import android.media.AudioRecord;
import android.media.MediaRecorder;
import android.net.LocalServerSocket;
import android.net.LocalSocket;
import java.io.DataInputStream;
import java.io.DataOutputStream;
import java.io.IOException;
import java.net.SocketTimeoutException;
import android.os.SystemClock;
import java.lang.reflect.Method;
import java.util.Arrays;

/**
 * VS-only, no JemRec and no call recording. Runs as Android shell via app_process
 * after the owner explicitly starts it over ADB. No remote network listening:
 * only a private abstract local socket plus 256-bit per-install token.
 *
 * Protocol: writeUTF token, then commands PROBE/START/PLAY/STOP/STATE.
 * PLAY frame is signed 16-bit little endian, 48000Hz mono, in RAM only.
 */
public final class VsTelephonyShell {
    private static final String SOCKET = "ontrack_vs_telephony_1";
    private static final int RATE = 48000;
    private final AudioManager audio;
    private final String secret;
    private AudioTrack track;
    private AudioRecord capture;
    private boolean muted;
    private boolean previouslyMuted;

    private VsTelephonyShell(AudioManager audio, String secret) {
        this.audio = audio;
        this.secret = secret;
    }

    public static void main(String[] args) throws Exception {
        if (args.length != 1 || !args[0].matches("[A-Za-z0-9_-]{40,100}")) {
            throw new IllegalArgumentException("Usage: VsTelephonyShell <ephemeral-pairing-token>");
        }
        // Android shell UID, not the normal application sandbox.
        Class<?> activityThread = Class.forName("android.app.ActivityThread");
        Object thread = activityThread.getMethod("systemMain").invoke(null);
        Context ctx = (Context)activityThread.getMethod("getSystemContext").invoke(thread);
        AudioManager audio = (AudioManager)ctx.getSystemService(Context.AUDIO_SERVICE);
        if (audio == null) throw new IllegalStateException("No audio service");
        VsTelephonyShell server = new VsTelephonyShell(audio, args[0]);
        try (LocalServerSocket socket = new LocalServerSocket(SOCKET)) {
            while (true) {
                LocalSocket connection = socket.accept();
                try { server.handle(connection); }
                catch (Exception ignored) { }
                finally {
                    server.restore();
                    try { connection.close(); } catch (Exception ignored) {}
                }
            }
        } finally { server.restore(); }
    }

    private void handle(LocalSocket conn) throws IOException {
        conn.setSoTimeout(1000);
        DataInputStream in = new DataInputStream(conn.getInputStream());
        DataOutputStream out = new DataOutputStream(conn.getOutputStream());
        if (!secret.equals(in.readUTF())) {
            out.writeUTF("DENIED"); out.flush(); return;
        }
        out.writeUTF("READY"); out.flush();
        long lastRequest = SystemClock.elapsedRealtime();
        while (true) {
            String command;
            try {
                command = in.readUTF();
            } catch (SocketTimeoutException idle) {
                // Fail-safe on cellular hangup even while Fish API is busy.
                if (track != null && audio.getMode() != AudioManager.MODE_IN_CALL) {
                    restore(); return;
                }
                if (SystemClock.elapsedRealtime() - lastRequest > 120000) {
                    restore(); return;
                }
                continue;
            }
            lastRequest = SystemClock.elapsedRealtime();
            if ("STATE".equals(command)) {
                out.writeUTF("MODE " + audio.getMode() + " MUTE " + audio.isMicrophoneMute());
            } else if ("SPEAKER".equals(command)) {
                if (audio.getMode() != AudioManager.MODE_IN_CALL) {
                    out.writeUTF("NO_SIM_CALL");
                } else {
                    boolean previous = audio.isSpeakerphoneOn();
                    audio.setSpeakerphoneOn(!previous);
                    boolean current = audio.isSpeakerphoneOn();
                    out.writeUTF(current == previous ? "مخرج الصوت غير مدعوم على الجهاز"
                        : current ? "مكبر الصوت: تشغيل" : "مكبر الصوت: إيقاف");
                }
            } else if ("PROBE".equals(command)) {
                restore();
                out.writeUTF(probe(false));
            } else if ("START".equals(command)) {
                if (track != null) { out.writeUTF("ACTIVE"); }
                else { out.writeUTF(probe(true)); }
            } else if ("CAPTURE".equals(command)) {
                // Mirror OnTrack Voice's shell-side VOICE_CALL capture, without
                // recorders, files, audio uploads, or a saved call archive.
                if (capture == null || track == null || !muted) {
                    out.writeInt(-1);
                } else {
                    byte[] pcm = new byte[38400]; // 200 ms, 48 kHz stereo PCM16
                    try {
                        int n = capture.read(pcm, 0, pcm.length, AudioRecord.READ_BLOCKING);
                        if (n <= 0 || (n & 3) != 0) throw new IOException("VOICE_CALL capture stopped: " + n);
                        out.writeInt(n);
                        out.write(pcm, 0, n);
                    } finally {
                        Arrays.fill(pcm, (byte) 0);
                    }
                }
            } else if ("PLAY".equals(command)) {
                int length = in.readInt();
                if (length <= 0 || length > 384000 || (length & 1) != 0)
                    throw new IOException("Invalid volatile PCM frame");
                byte[] pcm = new byte[length];
                try {
                    in.readFully(pcm);
                    if (track == null || !muted) { out.writeUTF("NOT_ACTIVE"); }
                    else {
                        int written = 0;
                        while (written < pcm.length) {
                            int n = track.write(pcm, written, pcm.length - written,
                                AudioTrack.WRITE_BLOCKING);
                            if (n <= 0) throw new IOException("Uplink track write failed: " + n);
                            written += n;
                        }
                        out.writeUTF("WRITTEN " + written);
                    }
                } finally { Arrays.fill(pcm, (byte)0); }
            } else if ("STOP".equals(command)) {
                restore();
                out.writeUTF("STOPPED");
            } else {
                out.writeUTF("UNKNOWN_COMMAND");
            }
            out.flush();
        }
    }

    /**
     * Use a zero-volume preflight. Only mute after the OS reports a telephony
     * route. A successful route readback is NOT proof of far-end reception.
     */
    private String probe(boolean activate) {
        if (audio.getMode() != AudioManager.MODE_IN_CALL) return "NO_SIM_CALL";
        AudioDeviceInfo uplink = null;
        for (AudioDeviceInfo dev : audio.getDevices(AudioManager.GET_DEVICES_OUTPUTS)) {
            if (dev.getType() == AudioDeviceInfo.TYPE_TELEPHONY) { uplink = dev; break; }
        }
        if (uplink == null) return "NO_UPLINK_DEVICE";
        AudioTrack trial = null;
        try {
            AudioFormat format = new AudioFormat.Builder()
                .setSampleRate(RATE).setEncoding(AudioFormat.ENCODING_PCM_16BIT)
                .setChannelMask(AudioFormat.CHANNEL_OUT_MONO).build();
            trial = new AudioTrack.Builder()
                .setAudioAttributes(new AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_MEDIA)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH).build())
                .setAudioFormat(format)
                .setBufferSizeInBytes(19200)
                .setTransferMode(AudioTrack.MODE_STREAM).build();
            if (trial.getState() != AudioTrack.STATE_INITIALIZED
                || !trial.setPreferredDevice(uplink)) return "UPLINK_ROUTE_DENIED";
            trial.play();
            byte[] silence = new byte[9600];
            int n = trial.write(silence, 0, silence.length, AudioTrack.WRITE_BLOCKING);
            if (n <= 0) return "UPLINK_WRITE_DENIED";
            AudioDeviceInfo actual = trial.getRoutedDevice();
            if (actual == null || actual.getId() != uplink.getId()) return "UPLINK_NOT_ROUTED";
            if (!activate) return "ROUTE_CANDIDATE_ONLY";
            // OnTrack Voice starts shell VOICE_CALL capture before muting
            // the handset microphone. An ordinary app microphone is not
            // equivalent and often receives silence during a SIM call.
            startCapture();
            track = trial;
            trial = null;
            previouslyMuted = audio.isMicrophoneMute();
            audio.setMicrophoneMute(true);
            muted = true;
            if (!audio.isMicrophoneMute()) {
                restore(); return "MUTE_NOT_CONFIRMED";
            }
            return "ACTIVE_MIC_MUTED_ROUTE_CANDIDATE";
        } catch (Throwable error) {
            restore();
            return "UNSUPPORTED_" + error.getClass().getSimpleName();
        } finally {
            if (trial != null) {
                try { trial.pause(); trial.flush(); trial.release(); }
                catch (Exception ignored) {}
            }
        }
    }

    private void startCapture() throws IOException {
        int min = AudioRecord.getMinBufferSize(RATE,
            AudioFormat.CHANNEL_IN_STEREO, AudioFormat.ENCODING_PCM_16BIT);
        if (min <= 0) throw new IOException("VOICE_CALL PCM format is unsupported");
        AudioRecord record = new AudioRecord(
            MediaRecorder.AudioSource.VOICE_CALL, RATE,
            AudioFormat.CHANNEL_IN_STEREO, AudioFormat.ENCODING_PCM_16BIT,
            Math.max(min * 4, 38400));
        if (record.getState() != AudioRecord.STATE_INITIALIZED) {
            record.release();
            throw new IOException("shell VOICE_CALL did not initialize");
        }
        try {
            record.startRecording();
            if (record.getRecordingState() != AudioRecord.RECORDSTATE_RECORDING)
                throw new IOException("shell VOICE_CALL was denied");
            capture = record;
        } catch (Exception ex) {
            record.release();
            throw new IOException("shell VOICE_CALL capture refused", ex);
        }
    }

    private void restore() {
        AudioRecord record = capture;
        capture = null;
        if (record != null) {
            try { record.stop(); } catch (Exception ignored) { }
            try { record.release(); } catch (Exception ignored) { }
        }
        if (track != null) {
            try { track.pause(); track.flush(); track.release(); }
            catch (Exception ignored) {}
            track = null;
        }
        if (muted) {
            try { audio.setMicrophoneMute(previouslyMuted); } catch (Exception ignored) {}
        }
        muted = false;
    }
}
