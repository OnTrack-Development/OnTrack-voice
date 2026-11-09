package com.ontrack.vs;

import android.Manifest;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.media.AudioFormat;
import android.media.AudioManager;
import android.os.Build;
import android.os.IBinder;
import java.io.ByteArrayOutputStream;
import java.util.Arrays;

/**
 * VS-only experimental SIM live voice path. Absolutely no call recording:
 * captures *only* the owner's microphone in volatile 2-second RAM windows,
 * sends to Fish ASR -> Drama3 TTS -> decodes PCM -> authorized shell uplink.
 * If any prerequisite fails, stops and attempts to restore the real mic.
 */
public final class LiveCallService extends Service {
    public static final String ACTION_START = "com.ontrack.vs.LIVE_START";
    public static final String ACTION_STOP = "com.ontrack.vs.LIVE_STOP";
    public static final String ACTION_SPEAKER = "com.ontrack.vs.LIVE_SPEAKER";
    public static volatile String status = "غير متصل بالشريحة";
    public static volatile boolean active = false;
    public static volatile boolean connecting = false;

    private volatile boolean stopRequested;
    private volatile boolean bridgeMutatedMicrophone;
    private volatile boolean initialMicMuted;
    private volatile String lastError;
    private volatile VsCallBridge bridge;
    private Thread worker;

    private void state(String s) {
        status = s;
        NotificationManager nm = getSystemService(NotificationManager.class);
        try { nm.notify(902, notification(s)); } catch (Exception ignored) {}
        sendBroadcast(new Intent(OverlayService.ACTION_LIVE_STATUS).setPackage(getPackageName()));
    }

    private Notification notification(String text) {
        Notification.Builder builder = Build.VERSION.SDK_INT >= 26
            ? new Notification.Builder(this, "vs_live_call")
            : new Notification.Builder(this);
        return builder.setContentTitle("VS • SIM voice experiment")
            .setContentText(text)
            .setSmallIcon(android.R.drawable.ic_btn_speak_now)
            .setOngoing(true)
            .build();
    }

    @Override public void onCreate() {
        super.onCreate();
        if (Build.VERSION.SDK_INT >= 26) {
            getSystemService(NotificationManager.class).createNotificationChannel(
                new NotificationChannel("vs_live_call", "VS live microphone",
                    NotificationManager.IMPORTANCE_LOW));
        }
    }

    @Override public int onStartCommand(Intent intent, int flags, int startId) {
        String action = intent == null ? ACTION_STOP : intent.getAction();
        if (ACTION_STOP.equals(action)) {
            endSession();
            stopSelf();
            return START_NOT_STICKY;
        }
        if (ACTION_SPEAKER.equals(action)) {
            VsCallBridge activeBridge = bridge;
            if (activeBridge != null && active) {
                new Thread(() -> {
                    try { state(activeBridge.speaker()); }
                    catch (Exception e) { state("فشل تبديل مخرج الصوت"); }
                }, "vs-speaker-toggle").start();
            }
            return START_NOT_STICKY;
        }
        if (!ACTION_START.equals(action)) return START_NOT_STICKY;
        if (worker != null && worker.isAlive()) return START_NOT_STICKY;
        try {
            startForeground(902, notification("جاري فحص ربط شريحة VS ..."));
        } catch (Exception ex) {
            state("تعذّر تشغيل خدمة الميكروفون: " + ex.getClass().getSimpleName());
            stopSelf(); return START_NOT_STICKY;
        }
        stopRequested = false;
        connecting = true;
        lastError = null;
        state("جاري تهيئة جسر الشريحة...");
        worker = new Thread(this::runSession, "vs-fish-sim-live");
        worker.start();
        return START_NOT_STICKY;
    }

    private void runSession() {
        try {
            // The ADB shell helper captures VOICE_CALL, not Android app MIC.
            AudioManager manager = (AudioManager)getSystemService(Context.AUDIO_SERVICE);
            if (manager == null || manager.getMode() != AudioManager.MODE_IN_CALL)
                throw new IllegalStateException("مفيش مكالمة SIM نشطة");
            String apiKey = ConfigStore.apiKey(this);
            if (apiKey.length() < 10)
                throw new IllegalStateException("اضبط مفتاح Fish Audio الأول");
            initialMicMuted = manager.isMicrophoneMute();
            // Receive the same 48k stereo shell VOICE_CALL PCM path that
            // OnTrack Voice uses, then isolate the user's configured channel.
            // Never activate a parallel Android app MIC during a SIM call.
            state("بفتح مسار صوت الشريحة من Shell، من غير تسجيل...");
            bridge = VsCallBridge.connect(this);
            String result = bridge.start();
            if (!result.startsWith("ACTIVE")) {
                throw new IllegalStateException("رفض مسار الشريحة: " + result);
            }
            bridgeMutatedMicrophone = true;
            connecting = false;
            // If the OEM's global mute also silences the VS microphone,
            // fail closed and promptly restore the original call.
            active = true;
            state("مسار OnTrack Shell نشط تجريبيًا؛ يجري التحقق من الصوت...");
            // Keep resampler state between shell packets and Fish windows, as in OnTrack Voice.
            CallPcm converter = new CallPcm("right");
            int emptyWindows = 0;
            while (!stopRequested && manager.getMode() == AudioManager.MODE_IN_CALL) {
                byte[] pcm = readSpeechWindow(bridge, converter);
                if (stopRequested) { Arrays.fill(pcm, (byte)0); break; }
                if (!containsSpeech(pcm)) {
                    emptyWindows++;
                    Arrays.fill(pcm, (byte)0);
                    if (emptyWindows >= 5) {
                        throw new IllegalStateException(
                            "الميكروفون مش واصل بعد كتم الشريحة؛ رجعنا صوتك الطبيعي");
                    }
                    state("مافيش كلام واضح؛ اتكلم جنب الموبايل لاختبار مسار المايك");
                    continue;
                }
                emptyWindows = 0;
                state("Fish Audio: تحويل كلامك، بدون حفظ صوت...");
                String text;
                try { text = FishClient.transcribePcm16(pcm, 16000, apiKey); }
                finally { Arrays.fill(pcm, (byte)0); }
                if (stopRequested) break;
                if (text.isEmpty()) continue;
                int persona = ConfigStore.selectedPersona(this);
                int intensity = ConfigStore.emotionIntensity(this);
                byte[] mp3 = FishClient.synthesize(
                    PersonaProfiles.forTts(text, persona, intensity),
                    apiKey, ConfigStore.voiceId(this, persona));
                if (stopRequested) { Arrays.fill(mp3, (byte)0); break; }
                byte[] pcm48 = FishPcmDecoder.decodeTo48kMono(mp3);
                try {
                    state("إرسال صوت Fish إلى مسار Telephony ...");
                    for (int offset = 0; offset < pcm48.length && !stopRequested;
                         offset += 48000) {
                        int length = Math.min(48000, pcm48.length - offset);
                        if ((length & 1) != 0) length--;
                        if (length < 2) break;
                        byte[] packet = Arrays.copyOfRange(pcm48, offset, offset + length);
                        try {
                            String response = bridge.play(packet);
                            if (!response.startsWith("WRITTEN"))
                                throw new IllegalStateException("فشل إرسال PCM: " + response);
                        } finally { Arrays.fill(packet, (byte)0); }
                    }
                } finally { Arrays.fill(pcm48, (byte)0); }
                state("أُرسلت دفعة PCM لمسار الجهاز؛ السماع عند الطرف التاني يحتاج تأكيد");
            }
        } catch (Exception error) {
            if (!stopRequested) {
                lastError = "توقف آمن: " + error.getMessage();
                state(lastError);
            }
        } finally {
            endSession();
            stopSelf();
        }
    }

    /**
     * Same stereo VOICE_CALL-to-16k downmix as OnTrack Voice's LinkPcm:
     * right side for user's voice, three 48k frames averaged to one 16k
     * sample. Only small temporary RAM buffers; no recording files.
     */
    private byte[] readSpeechWindow(VsCallBridge link, CallPcm converter) throws Exception {
        ByteArrayOutputStream speech = new ByteArrayOutputStream(64000);
        long deadline = android.os.SystemClock.elapsedRealtime() + 3500;
        while (!stopRequested && speech.size() < 64000
                && android.os.SystemClock.elapsedRealtime() < deadline) {
            byte[] stereo = link.capture();
            try {
                byte[] mono = converter.input(stereo);
                try { speech.write(mono, 0, mono.length); }
                finally { Arrays.fill(mono, (byte) 0); }
            } finally { Arrays.fill(stereo, (byte) 0); }
        }
        return speech.toByteArray();
    }

    private boolean containsSpeech(byte[] pcm) {
        if (pcm.length < 16000) return false;
        long absolute = 0;
        int samples = pcm.length / 2;
        for (int i = 0; i + 1 < pcm.length; i += 2) {
            short v = (short)(((pcm[i + 1] & 255) << 8) | (pcm[i] & 255));
            absolute += Math.abs((int)v);
        }
        return absolute / Math.max(1, samples) > 190;
    }

    private void endSession() {
        stopRequested = true;
        active = false;
        connecting = false;
        VsCallBridge b = bridge;
        bridge = null;
        if (b != null) {
            try { b.abort(); } catch (Exception ignored) {}
        }
        // Emergency rollback when the shell transport died: requires normal
        // MODIFY_AUDIO_SETTINGS permission and may still be refused by OEM.
        if (bridgeMutatedMicrophone) {
            try {
                AudioManager audio = (AudioManager)getSystemService(Context.AUDIO_SERVICE);
                if (audio != null && audio.isMicrophoneMute() != initialMicMuted)
                    audio.setMicrophoneMute(initialMicMuted);
            } catch (Exception ignored) {}
        }
        bridgeMutatedMicrophone = false;
        state(lastError == null
            ? "VS متوقف؛ تم طلب استعادة حالة الميكروفون السابقة"
            : lastError + " • تم طلب استعادة الميكروفون السابق");
    }

    @Override public void onDestroy() {
        endSession();
        super.onDestroy();
    }
    @Override public IBinder onBind(Intent intent) { return null; }
}
