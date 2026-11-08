package com.ontrack.vs;

import android.Manifest;
import android.app.Activity;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.pm.PackageManager;
import android.content.res.ColorStateList;
import android.media.AudioManager;
import android.media.MediaPlayer;
import android.media.MediaDataSource;
import android.media.MediaRecorder;
import android.media.AudioRecord;
import android.media.AudioFormat;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.RadioButton;
import android.widget.RadioGroup;
import android.widget.ScrollView;
import android.widget.TextView;
import java.io.IOException;
import java.util.Arrays;
import java.io.ByteArrayOutputStream;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

/** VS-only preview UI. Cellular microphone replacement is deliberately NOT claimed. */
public final class MainActivity extends Activity {
    private final ExecutorService queue = Executors.newSingleThreadExecutor();
    private EditText keyField, voiceField, textField;
    private TextView status, personaDetail, permissionStatus, keyStatus;
    private RadioGroup personaGroup, intensityGroup;
    private int selectedPersona, selectedIntensity;
    private Button recordButton;
    private AudioRecord recorder;
    private ByteArrayOutputStream micSamples;
    private Thread captureThread;
    private MediaPlayer player;
    private RamMp3 currentRamMp3;

    /** Decoded by Android directly from volatile memory. Never saved to disk. */
    private static final class RamMp3 extends MediaDataSource {
        private byte[] data;
        RamMp3(byte[] data) { this.data = data; }
        @Override public synchronized int readAt(long position, byte[] buffer, int offset, int size)
                throws IOException {
            if (data == null) throw new IOException("audio data already cleared");
            if (position < 0) return -1;
            if (position >= data.length) return -1;
            int length = (int)Math.min((long)size, (long)data.length - position);
            System.arraycopy(data, (int)position, buffer, offset, length);
            return length;
        }
        @Override public synchronized long getSize() { return data == null ? 0 : data.length; }
        @Override public synchronized void close() {
            if (data != null) { Arrays.fill(data, (byte)0); data = null; }
        }
    }
    private volatile boolean recordingActive;
    private volatile boolean captureRunning;
    private volatile boolean stopped;
    private final BroadcastReceiver takeOver = new BroadcastReceiver() {
        @Override public void onReceive(Context context, Intent intent) {
            stopAll();
            info("تم استلام التحكم وإيقاف صوت المعاينة. الزر العائم ما زال ظاهرًا.\nصوت مكالمة الشريحة لم يتغير.");
        }
    };

    private int d(int value) { return VsUi.dp(this, value); }

    private Button action(String title, boolean primary, View.OnClickListener listener) {
        Button button = VsUi.button(this, title, primary);
        button.setOnClickListener(listener);
        return button;
    }

    private void gap(LinearLayout box, int dp) { VsUi.space(box, this, dp); }

    private void info(String message) {
        runOnUiThread(() -> { if (status != null) status.setText(message); });
    }

    private void updateKeyStatus() {
        try {
            keyStatus.setText(ConfigStore.apiKey(this).isEmpty()
                ? "مفتاح Fish Audio: لم يُحفظ بعد"
                : "مفتاح Fish Audio: محفوظ مشفّرًا على الجهاز ✓");
        } catch (Exception ex) {
            keyStatus.setText("مفتاح Fish Audio: تعذّر قراءة المفتاح المحفوظ");
        }
    }

    private boolean isPhoneAudioBusy() {
        AudioManager manager = (AudioManager) getSystemService(Context.AUDIO_SERVICE);
        if (manager == null) return false;
        int mode = manager.getMode();
        return mode == AudioManager.MODE_IN_CALL || mode == AudioManager.MODE_IN_COMMUNICATION;
    }

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        getWindow().setStatusBarColor(VsUi.BACK);
        getWindow().setNavigationBarColor(VsUi.BACK);

        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        scroll.setBackgroundColor(VsUi.BACK);
        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        root.setPadding(d(18), d(22), d(18), d(34));
        scroll.addView(root);

        TextView mark = VsUi.text(this, "VS  /  DRAMA 3", 30, VsUi.RED, true);
        mark.setLetterSpacing(0.06f);
        root.addView(mark);
        TextView subtitle = VsUi.text(this, "مُغيّر الصوت • تجربة Fish Audio", 16, VsUi.WHITE, true);
        root.addView(subtitle);
        gap(root, 14);

        root.addView(VsUi.warning(this,
            "⚠️ غير متصل بصوت مكالمة الشريحة\n"
            + "في النسخة دي الصوت المُولّد بيتسمع على جهازك فقط. "
            + "الشخص اللي على الخط هيسمع صوتك الأصلي. "
            + "صلاحيات المايك والفقاعة مش بتفتح مسار إرسال صوت الشريحة."));
        gap(root, 12);

        status = VsUi.note(this, "حالة التشغيل: معاينة محلية فقط. لا يوجد استبدال لصوت المكالمة.");
        root.addView(status);
        gap(root, 16);

        LinearLayout configCard = VsUi.card(this);
        configCard.addView(VsUi.text(this, "01  /  إعداد الصوت", 19, VsUi.WHITE, true));
        configCard.addView(VsUi.heading(this, "مفتاح Fish Audio API"));
        keyField = VsUi.input(this, "أدخل المفتاح الجديد أو اتركه فارغًا لاستخدام المحفوظ", "", true);
        keyField.setTextDirection(View.TEXT_DIRECTION_LTR);
        keyField.setGravity(Gravity.LEFT | Gravity.CENTER_VERTICAL);
        configCard.addView(keyField);
        gap(configCard, 8);
        keyStatus = VsUi.text(this, "", 13, VsUi.MUTED, false);
        configCard.addView(keyStatus);

        selectedPersona = ConfigStore.selectedPersona(this);
        selectedIntensity = ConfigStore.emotionIntensity(this);
        configCard.addView(VsUi.heading(this, "اختار أسلوب الأداء"));
        personaGroup = new RadioGroup(this);
        personaGroup.setOrientation(RadioGroup.VERTICAL);
        for (int i = 0; i < PersonaProfiles.COUNT; i++) {
            RadioButton option = new RadioButton(this);
            option.setId(2000 + i);
            option.setText(PersonaProfiles.NAMES[i]);
            option.setTextColor(VsUi.WHITE);
            option.setTextSize(16);
            option.setPadding(d(8), d(4), d(8), d(4));
            option.setButtonTintList(ColorStateList.valueOf(VsUi.RED));
            personaGroup.addView(option);
        }
        configCard.addView(personaGroup);
        personaDetail = VsUi.text(this, PersonaProfiles.DESCRIPTIONS[selectedPersona], 14, VsUi.MUTED, false);
        configCard.addView(personaDetail);
        configCard.addView(VsUi.heading(this, "قوة المشاعر في Fish Drama 3"));
        intensityGroup = new RadioGroup(this);
        intensityGroup.setOrientation(RadioGroup.HORIZONTAL);
        intensityGroup.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        String[] intensities = {"طبيعي", "خفيف", "متوسط"};
        for (int i = 0; i < intensities.length; i++) {
            RadioButton choice = new RadioButton(this);
            choice.setId(3000 + i);
            choice.setText(intensities[i]);
            choice.setTextColor(VsUi.WHITE);
            choice.setTextSize(13);
            choice.setButtonTintList(ColorStateList.valueOf(VsUi.RED));
            intensityGroup.addView(choice);
        }
        configCard.addView(intensityGroup);
        configCard.addView(VsUi.text(this,
            "الوضع الافتراضي خفيف. من غير ضحك أو تنهيدات مصطنعة، وممكن تختار طبيعي تمامًا.",
            12, VsUi.MUTED, false));
        configCard.addView(VsUi.heading(this, "Voice ID للشخصية"));
        voiceField = VsUi.input(this, "Fish reference_id", ConfigStore.voiceId(this, selectedPersona), false);
        voiceField.setTextDirection(View.TEXT_DIRECTION_LTR);
        voiceField.setGravity(Gravity.LEFT | Gravity.CENTER_VERTICAL);
        configCard.addView(voiceField);
        gap(configCard, 12);
        configCard.addView(action("حفظ إعدادات الصوت", true, v -> save()));
        VsUi.addSection(root, configCard, this);

        LinearLayout previewCard = VsUi.card(this);
        previewCard.addView(VsUi.text(this, "02  /  جرّب الصوت على موبايلك", 19, VsUi.WHITE, true));
        previewCard.addView(VsUi.heading(this, "الجملة التجريبية"));
        textField = VsUi.input(this, "اكتب جملة باللهجة المصرية", PersonaProfiles.sample(selectedPersona), false);
        previewCard.addView(textField);
        gap(previewCard, 12);
        previewCard.addView(action("▶  تشغيل عينة Drama 3", true, v -> testText()));
        gap(previewCard, 8);
        recordButton = action("🎙  تجربة الميكروفون المباشر (بدون حفظ)", false, v -> toggleRecording());
        previewCard.addView(recordButton);
        gap(previewCard, 9);
        previewCard.addView(VsUi.text(this,
            "التقاط الميكروفون داخل الذاكرة فقط، وإرسال المقطع إلى Fish Audio للتحويل. لا يتم تسجيل أو حفظ مكالمات.", 13, VsUi.MUTED, false));
        VsUi.addSection(root, previewCard, this);

        LinearLayout floatingCard = VsUi.card(this);
        floatingCard.addView(VsUi.text(this, "03  /  فقاعة VS مصغرة", 19, VsUi.WHITE, true));
        gap(floatingCard, 7);
        floatingCard.addView(VsUi.text(this,
            "فقاعة 58dp، اضغط عليها تفتح/تقفل واسحبها لأي مكان. تشغيل أخضر، إيقاف أحمر. حالة الشريحة منفصلة عن معاينة Fish.", 13, VsUi.MUTED, false));
        gap(floatingCard, 12);
        floatingCard.addView(action("إظهار فقاعة VS الصغيرة", true, v -> overlay()));
        gap(floatingCard, 8);
        floatingCard.addView(action("إيقاف المعاينة / استلام", false, v -> {
            ConfigStore.setPreviewPaused(this, true);
            stopAll();
            info("توقفت المعاينة. الفقاعة لا تختفي. صوت المكالمة ما زال طبيعيًا.");
        }));
        gap(floatingCard, 8);
        floatingCard.addView(action("إغلاق الفقاعة يدويًا", false, v -> {
            stopService(new Intent(this, OverlayService.class));
            info("تم إغلاق الفقاعة يدويًا.");
        }));
        gap(floatingCard, 10);
        floatingCard.addView(action("فحص حالة مكالمة الشريحة", false,
            v -> info(SimAudioStatus.report(this))));
        gap(floatingCard, 8);
        floatingCard.addView(action("اختبار مسار إرسال الصوت (صامت)", false,
            v -> {
                info("بفحص توجيه Telephony من غير صوت أو تسجيل أو كتم مكالمتك...");
                queue.execute(() -> info(SimAudioStatus.probePreferredRoute(this)));
            }));
        VsUi.addSection(root, floatingCard, this);

        LinearLayout permissionsCard = VsUi.card(this);
        permissionsCard.addView(VsUi.text(this, "04  /  فحص التصاريح والجهاز", 19, VsUi.WHITE, true));
        gap(permissionsCard, 9);
        permissionStatus = VsUi.text(this, PermissionCheck.report(this), 13, VsUi.MUTED, false);
        permissionsCard.addView(permissionStatus);
        gap(permissionsCard, 13);
        permissionsCard.addView(action("فحص الصلاحيات من جديد", true, v -> refreshPermissions()));
        gap(permissionsCard, 8);
        permissionsCard.addView(action("تفعيل الإذن الناقص مباشرة", false, v -> PermissionCheck.activateNext(this)));
        gap(permissionsCard, 8);
        permissionsCard.addView(action("فتح إعدادات التطبيق", false, v -> PermissionCheck.openAppDetails(this)));
        VsUi.addSection(root, permissionsCard, this);

        setContentView(scroll);
        updateKeyStatus();
        personaGroup.check(2000 + selectedPersona);
        intensityGroup.check(3000 + selectedIntensity);
        intensityGroup.setOnCheckedChangeListener((group, id) -> {
            selectedIntensity = PersonaProfiles.validIntensity(id - 3000);
            ConfigStore.saveEmotionIntensity(this, selectedIntensity);
            info("درجة المشاعر: " + new String[]{"طبيعي", "خفيف", "متوسط"}[selectedIntensity]);
        });
        personaGroup.setOnCheckedChangeListener((group, id) -> choosePersona(id - 2000));
        IntentFilter filter = new IntentFilter(OverlayService.ACTION_TAKE_OVER);
        if (Build.VERSION.SDK_INT >= 33) registerReceiver(takeOver, filter, Context.RECEIVER_NOT_EXPORTED);
        else registerReceiver(takeOver, filter);
    }

    private void refreshPermissions() {
        if (permissionStatus != null) permissionStatus.setText(PermissionCheck.report(this));
    }

    private void choosePersona(int newIndex) {
        int next = PersonaProfiles.validIndex(newIndex);
        if (next == selectedPersona) return;
        if (!save()) {
            personaGroup.check(2000 + selectedPersona);
            return;
        }
        selectedPersona = next;
        ConfigStore.saveSelectedPersona(this, selectedPersona);
        voiceField.setText(ConfigStore.voiceId(this, selectedPersona));
        personaDetail.setText(PersonaProfiles.DESCRIPTIONS[selectedPersona]);
        textField.setText(PersonaProfiles.sample(selectedPersona));
        info("تم اختيار " + PersonaProfiles.NAMES[selectedPersona] + ". اختبر الصوت على الجهاز.");
    }

    private boolean save() {
        String voice = voiceField.getText().toString().trim();
        if (!voice.matches("[A-Za-z0-9_-]{4,128}")) {
            info("Voice ID غير صالح. أدخل reference_id الصحيح.");
            return false;
        }
        try {
            ConfigStore.saveApiKey(this, keyField.getText().toString().trim());
            ConfigStore.saveVoiceId(this, selectedPersona, voice);
            ConfigStore.saveSelectedPersona(this, selectedPersona);
            ConfigStore.saveEmotionIntensity(this, selectedIntensity);
            keyField.setText("");
            updateKeyStatus();
            info("تم حفظ إعدادات Fish Audio بأمان. لا يوجد توصيل بمكالمة الشريحة.");
            return true;
        } catch (Exception ex) {
            info("تعذر حفظ المفتاح: " + ex.getClass().getSimpleName());
            return false;
        }
    }

    private boolean mayPreview() {
        if (isPhoneAudioBusy()) {
            info("فيه مكالمة/اتصال صوتي نشط. المعاينة ممنوعة أثناء المكالمة "
                + "عشان الصوت المحلي مايتسمعش بالغلط وكأنه صوت الطرف التاني.");
            return false;
        }
        if (ConfigStore.isPreviewPaused(this)) {
            info("المعاينة متوقفة من الفقاعة. اضغط «استئناف المعاينة» في الفقاعة أولًا.");
            return false;
        }
        return true;
    }

    private void testText() {
        if (!mayPreview() || !save()) return;
        String words = textField.getText().toString().trim();
        if (words.isEmpty()) { info("اكتب جملة للتجربة."); return; }
        stopped = false;
        info("جارٍ توليد عينة Fish Drama 3 للاستماع على الجهاز...");
        final int persona = selectedPersona;
        queue.execute(() -> render(words, persona));
    }

    private void render(String words, int persona) {
        try {
            String key = ConfigStore.apiKey(this);
            byte[] mp3 = FishClient.synthesize(
                PersonaProfiles.forTts(words, persona, selectedIntensity), key, ConfigStore.voiceId(this, persona));
            if (stopped || ConfigStore.isPreviewPaused(this)) {
                Arrays.fill(mp3, (byte)0);
                return;
            }
            runOnUiThread(() -> {
                if (!stopped && !ConfigStore.isPreviewPaused(this) && !isPhoneAudioBusy())
                    play(mp3);
                else Arrays.fill(mp3, (byte)0);
            });
        } catch (Exception ex) {
            if (!stopped) info("Fish Audio: " + ex.getMessage());
        }
    }

    private void play(byte[] mp3) {
        if (!mayPreview() || stopped) {
            Arrays.fill(mp3, (byte)0);
            return;
        }
        try {
            releasePlayback();
            RamMp3 ram = new RamMp3(mp3);
            currentRamMp3 = ram;
            player = new MediaPlayer();
            player.setDataSource(ram);
            player.setOnPreparedListener(p -> {
                if (!stopped && !isPhoneAudioBusy() && !ConfigStore.isPreviewPaused(this))
                    p.start();
                else releasePlayback();
            });
            player.setOnCompletionListener(p -> {
                releasePlayback();
                info("انتهت المعاينة. تم مسح بيانات الصوت المؤقتة من الذاكرة.");
            });
            player.setOnErrorListener((p, what, extra) -> {
                releasePlayback();
                info("تعذّر تشغيل المعاينة");
                return true;
            });
            player.prepareAsync();
            info("المعاينة في RAM فقط، ومش متصلة بقناة إرسال مكالمة الشريحة.");
        } catch (Exception ex) {
            releasePlayback();
            Arrays.fill(mp3, (byte)0);
            info("تعذّر تشغيل العينة: " + ex.getMessage());
        }
    }

    private void releasePlayback() {
        if (player != null) {
            try { player.stop(); } catch (Exception ignored) {}
            try { player.release(); } catch (Exception ignored) {}
            player = null;
        }
        if (currentRamMp3 != null) {
            currentRamMp3.close();
            currentRamMp3 = null;
        }
    }

    /**
     * A bounded foreground microphone segment for Fish ASR. No MediaRecorder,
     * audio file, phone call recording, or persistent microphone history.
     */
    private void toggleRecording() {
        if (recordingActive) { finishRecording(); return; }
        if (!mayPreview()) return;
        if (checkSelfPermission(Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[]{Manifest.permission.RECORD_AUDIO}, 401);
            return;
        }
        if (!save()) return;
        stopped = false;
        try {
            int min = AudioRecord.getMinBufferSize(16000,
                AudioFormat.CHANNEL_IN_MONO, AudioFormat.ENCODING_PCM_16BIT);
            if (min <= 0) throw new IllegalStateException("AudioRecord unsupported on device");
            int buffer = Math.max(min, 6400);
            recorder = new AudioRecord(MediaRecorder.AudioSource.MIC, 16000,
                AudioFormat.CHANNEL_IN_MONO, AudioFormat.ENCODING_PCM_16BIT, buffer);
            if (recorder.getState() != AudioRecord.STATE_INITIALIZED)
                throw new IllegalStateException("AudioRecord could not initialize");
            micSamples = new ByteArrayOutputStream(256000);
            recorder.startRecording();
            captureRunning = true;
            recordingActive = true;
            recordButton.setText("■  تحويل الكلام الملتقط الآن");
            info("التقاط المايك في RAM فقط. لا يوجد ملف تسجيل أو تسجيل مكالمة.");
            captureThread = new Thread(() -> {
                byte[] block = new byte[3200];
                try {
                    while (captureRunning && !stopped) {
                        int length = recorder.read(block, 0, block.length);
                        if (length > 0) {
                            micSamples.write(block, 0, length);
                            if (micSamples.size() >= 256000) {
                                runOnUiThread(() -> {
                                    if (recordingActive) finishRecording();
                                });
                                break;
                            }
                        } else if (length < 0) {
                            break;
                        }
                    }
                } catch (Exception ignored) {
                    // Ended capture, often because the user stopped the microphone.
                }
            }, "vs-volatile-mic");
            captureThread.start();
        } catch (Exception ex) {
            resetRecorder();
            info("تعذر التقاط الميكروفون: " + ex.getMessage());
        }
    }

    private void finishRecording() {
        if (!recordingActive) return;
        recordingActive = false;
        captureRunning = false;
        if (recorder != null) {
            try { recorder.stop(); } catch (Exception ignored) {}
        }
        if (captureThread != null && Thread.currentThread() != captureThread) {
            try { captureThread.join(1200); }
            catch (InterruptedException ex) { Thread.currentThread().interrupt(); }
        }
        byte[] pcm = micSamples == null ? new byte[0] : micSamples.toByteArray();
        resetRecorder();
        if (pcm.length < 3200) {
            info("المقطع قصير جدًا؛ اتكلم ثانية على الأقل.");
            return;
        }
        info("جارٍ إرسال المقطع من الذاكرة إلى Fish ASR ثم Drama 3...");
        final int persona = selectedPersona;
        queue.execute(() -> {
            try {
                String key = ConfigStore.apiKey(this);
                if (key.isEmpty()) throw new Exception("أدخل مفتاح Fish Audio");
                String words = FishClient.transcribePcm16(pcm, 16000, key);
                java.util.Arrays.fill(pcm, (byte) 0);
                if (stopped || ConfigStore.isPreviewPaused(this)) return;
                if (words.isEmpty()) throw new Exception("مش قادر أتعرف على الكلام");
                info("النص: " + words + "\nجارٍ توليد المعاينة...");
                render(words, persona);
            } catch (Exception ex) {
                java.util.Arrays.fill(pcm, (byte) 0);
                if (!stopped) info("فشل اختبار الصوت: " + ex.getMessage());
            }
        });
    }

    private void resetRecorder() {
        captureRunning = false;
        if (recorder != null) {
            try { recorder.stop(); } catch (Exception ignored) {}
            try { recorder.release(); } catch (Exception ignored) {}
            recorder = null;
        }
        captureThread = null;
        micSamples = null;
        recordingActive = false;
        if (recordButton != null) recordButton.setText("🎙  تجربة الميكروفون المباشر (بدون حفظ)");
    }

    private void stopAll() {
        stopped = true;
        resetRecorder();
        releasePlayback();
    }

    private void overlay() {
        if (!Settings.canDrawOverlays(this)) {
            info("الظهور فوق التطبيقات لسه مش مفعّل. افتح الإذن وارجع للتطبيق.");
            startActivity(new Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
                Uri.parse("package:" + getPackageName())));
            return;
        }
        Intent intent = new Intent(this, OverlayService.class);
        if (Build.VERSION.SDK_INT >= 26) startForegroundService(intent);
        else startService(intent);
        info("فقاعة VS ظهرت بحجم صغير. اضغط لتفتح، واسحب لتغيّر مكانها. تشغيل المعاينة لا يحقن صوتًا في الشريحة.");
    }

    @Override public void onRequestPermissionsResult(int code, String[] permissions, int[] results) {
        super.onRequestPermissionsResult(code, permissions, results);
        refreshPermissions();
        if (code == 401 && results.length > 0
                && results[0] == PackageManager.PERMISSION_GRANTED) toggleRecording();
        else if (code == 401) info("الميكروفون مطلوب لالتقاط كلامك مباشرة في الذاكرة، بدون حفظ مكالمات.");
    }

    @Override protected void onResume() {
        super.onResume();
        refreshPermissions();
    }

    @Override protected void onDestroy() {
        stopAll();
        unregisterReceiver(takeOver);
        queue.shutdownNow();
        super.onDestroy();
    }
}
