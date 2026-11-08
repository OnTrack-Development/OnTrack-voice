package com.ontrack.vs;

import android.Manifest;
import android.app.Activity;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.pm.PackageManager;
import android.media.MediaPlayer;
import android.media.MediaRecorder;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.text.InputType;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.RadioButton;
import android.widget.RadioGroup;
import android.widget.ScrollView;
import android.widget.TextView;
import java.io.File;
import java.io.FileOutputStream;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public final class MainActivity extends Activity {
    private final ExecutorService queue = Executors.newSingleThreadExecutor();
    private EditText keyField, voiceField, textField;
    private TextView status, personaDetail;
    private RadioGroup personaGroup;
    private int selectedPersona;
    private Button recordButton;
    private File recording;
    private MediaRecorder recorder;
    private MediaPlayer player;
    private boolean recordingActive;
    private volatile boolean stopped;
    private final BroadcastReceiver takeOver = new BroadcastReceiver() {
        @Override public void onReceive(Context c, Intent intent) { stopAll(); info("تم إيقاف VS. لم تُغيَّر قناة مكالمة الشريحة."); }
    };
    private TextView label(String text) {
        TextView view = new TextView(this); view.setText(text);
        view.setTextSize(17); view.setPadding(0, 16, 0, 8); return view;
    }
    private EditText edit(String hint, String value) {
        EditText view = new EditText(this);
        view.setHint(hint); view.setText(value); view.setSingleLine(false);
        return view;
    }
    private Button button(String title, View.OnClickListener onClick) {
        Button button = new Button(this); button.setText(title);
        button.setOnClickListener(onClick); return button;
    }
    private void info(String message) { runOnUiThread(() -> status.setText(message)); }
    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        ScrollView scroll = new ScrollView(this);
        LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL); box.setPadding(32, 30, 32, 32);
        scroll.addView(box);
        box.addView(label("VS — Fish Audio • Drama 3"));
        box.addView(label("تجربة صوت مستقلة. لا يوجد توصيل مثبت بصوت مكالمة الشريحة، ولا يتم كتم/استبدال الصوت الأصلي."));
        box.addView(label("API Key (يُحفظ مشفّرًا على الهاتف)"));
        keyField = edit("Fish Audio API key", "");
        keyField.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_PASSWORD);
        box.addView(keyField);
        selectedPersona = ConfigStore.selectedPersona(this);
        box.addView(label("اختار واحدة من ٣ شخصيات — Drama 3 (نفس الصوت أو Voice ID لكل واحدة)"));
        personaGroup = new RadioGroup(this);
        personaGroup.setOrientation(LinearLayout.VERTICAL);
        for (int i = 0; i < PersonaProfiles.COUNT; i++) {
            RadioButton option = new RadioButton(this);
            option.setId(2000 + i);
            option.setText(PersonaProfiles.NAMES[i]);
            option.setTextSize(16);
            personaGroup.addView(option);
        }
        box.addView(personaGroup);
        box.addView(label("Voice ID للشخصية المختارة"));
        voiceField = edit("reference_id", ConfigStore.voiceId(this, selectedPersona));
        box.addView(voiceField);
        personaDetail = label(PersonaProfiles.DESCRIPTIONS[selectedPersona]);
        box.addView(personaDetail);
        box.addView(button("حفظ البيانات", v -> save()));
        box.addView(label("جملة التجربة"));
        textField = edit("اكتب النص بالعربي للتجربة", PersonaProfiles.sample(selectedPersona));
        box.addView(textField);
        box.addView(button("تشغيل تجربة Drama 3", v -> testText()));
        recordButton = button("سجّل كلامي", v -> toggleRecording());
        box.addView(recordButton);
        box.addView(button("إظهار الأيقونة العائمة", v -> overlay()));
        box.addView(button("استلام / إيقاف المعالجة", v -> {
            stopAll(); stopService(new Intent(this, OverlayService.class));
            info("تم إيقاف VS. مكالمة الشريحة غير متصلة بهذا النموذج التجريبي.");
        }));
        status = label("جاهز للإعداد. لا يوجد API key مضمن في التطبيق.");
        box.addView(status);
        setContentView(scroll);
        personaGroup.check(2000 + selectedPersona);
        personaGroup.setOnCheckedChangeListener((group, id) -> choosePersona(id - 2000));
        IntentFilter filter = new IntentFilter(OverlayService.ACTION_TAKE_OVER);
        if (Build.VERSION.SDK_INT >= 33) registerReceiver(takeOver, filter, Context.RECEIVER_NOT_EXPORTED);
        else registerReceiver(takeOver, filter);
    }
    private void choosePersona(int newIndex) {
        int next = PersonaProfiles.validIndex(newIndex);
        if (next == selectedPersona) return;
        // Save the old persona's manually entered Voice ID before switching.
        if (!save()) {
            personaGroup.check(2000 + selectedPersona);
            return;
        }
        selectedPersona = next;
        ConfigStore.saveSelectedPersona(this, selectedPersona);
        voiceField.setText(ConfigStore.voiceId(this, selectedPersona));
        personaDetail.setText(PersonaProfiles.DESCRIPTIONS[selectedPersona]);
        textField.setText(PersonaProfiles.sample(selectedPersona));
        info("تم اختيار " + PersonaProfiles.NAMES[selectedPersona] + " — راجع Voice ID وجرب الصوت.");
    }
    private boolean save() {
        String voice = voiceField.getText().toString().trim();
        if (!voice.matches("[A-Za-z0-9_-]{4,128}")) { info("Voice ID غير صالح"); return false; }
        try {
            ConfigStore.saveApiKey(this, keyField.getText().toString());
            ConfigStore.saveVoiceId(this, selectedPersona, voice);
            ConfigStore.saveSelectedPersona(this, selectedPersona);
            keyField.setText("");
            info("اتحفظ الـVoice ID ومفتاح Fish (لو أدخلته) محليًا بتشفير الجهاز.");
            return true;
        } catch (Exception ex) { info("تعذر حفظ المفتاح: " + ex.getClass().getSimpleName()); return false; }
    }
    private void testText() {
        if (!save()) return;
        String text = textField.getText().toString().trim();
        stopped = false; info("جارٍ طلب الصوت من Fish Audio Drama 3...");
        final int persona = selectedPersona;
        queue.execute(() -> render(text, persona));
    }
    private void render(String words, int persona) {
        try {
            String key = ConfigStore.apiKey(this);
            byte[] mp3 = FishClient.synthesize(
                PersonaProfiles.forTts(words, persona), key, ConfigStore.voiceId(this, persona));
            if (stopped) return;
            File file = new File(getCacheDir(), "vs_preview.mp3");
            try (FileOutputStream out = new FileOutputStream(file)) { out.write(mp3); }
            if (!stopped) runOnUiThread(() -> play(file));
        } catch (Exception ex) { if (!stopped) info("خطأ في Fish Audio: " + ex.getMessage()); }
    }
    private void play(File file) {
        try {
            if (player != null) { player.release(); player = null; }
            player = new MediaPlayer();
            player.setDataSource(file.getAbsolutePath());
            player.setOnPreparedListener(p -> { if (!stopped) p.start(); });
            player.setOnCompletionListener(p -> info("التجربة انتهت — الصوت على جهازك فقط."));
            player.prepareAsync();
            info("تم استلام الصوت. التشغيل على سماعة الهاتف للتجربة فقط.");
        } catch (Exception ex) { info("خطأ التشغيل: " + ex.getMessage()); }
    }
    private void toggleRecording() {
        if (recordingActive) { finishRecording(); return; }
        if (checkSelfPermission(Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[]{Manifest.permission.RECORD_AUDIO}, 401); return;
        }
        stopped = false;
        try {
            recording = new File(getCacheDir(), "vs_record.m4a");
            recorder = new MediaRecorder();
            recorder.setAudioSource(MediaRecorder.AudioSource.MIC);
            recorder.setOutputFormat(MediaRecorder.OutputFormat.MPEG_4);
            recorder.setAudioEncoder(MediaRecorder.AudioEncoder.AAC);
            recorder.setOutputFile(recording.getAbsolutePath());
            recorder.prepare(); recorder.start();
            recordingActive = true; recordButton.setText("إيقاف وتحويل صوتي");
            info("جارٍ تسجيل الميكروفون للتجربة — اضغط إيقاف بعد الكلام.");
        } catch (Exception ex) { resetRecorder(); info("لا يمكن التسجيل: " + ex.getMessage()); }
    }
    private void finishRecording() {
        try {
            recorder.stop();
        } catch (Exception ex) { info("التسجيل قصير أو فشل: " + ex.getMessage()); resetRecorder(); return; }
        resetRecorder();
        info("رفع الصوت إلى Fish ASR، ثم توليده عبر Drama 3...");
        final int persona = selectedPersona;
        queue.execute(() -> {
            try {
                String key = ConfigStore.apiKey(this);
                if (key.isEmpty()) throw new Exception("أدخل API Key واحفظه");
                String words = FishClient.transcribe(recording, key);
                if (stopped) return;
                if (words.isEmpty()) throw new Exception("لم يتم التقاط كلام واضح");
                info("النص: " + words + "\nجارٍ توليد صوت Drama 3...");
                render(words, persona);
            } catch (Exception ex) { if (!stopped) info("فشل تحويل الصوت: " + ex.getMessage()); }
        });
    }
    private void resetRecorder() {
        if (recorder != null) { try { recorder.release(); } catch (Exception ignored) {} recorder = null; }
        recordingActive = false;
        if (recordButton != null) recordButton.setText("سجّل كلامي");
    }
    private void stopAll() {
        stopped = true; resetRecorder();
        if (player != null) { try { player.stop(); } catch (Exception ignored) {} player.release(); player = null; }
    }
    private void overlay() {
        if (!Settings.canDrawOverlays(this)) {
            info("امنح صلاحية الظهور فوق التطبيقات ثم اضغط الزر مجددًا.");
            startActivity(new Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
                Uri.parse("package:" + getPackageName()))); return;
        }
        Intent intent = new Intent(this, OverlayService.class);
        if (Build.VERSION.SDK_INT >= 26) startForegroundService(intent);
        else startService(intent);
        info("ظهرت عناصر التحكم العائمة. لا تحتاج Accessibility.");
    }
    @Override public void onRequestPermissionsResult(int code, String[] permissions, int[] results) {
        super.onRequestPermissionsResult(code, permissions, results);
        if (code == 401 && results.length > 0 && results[0] == PackageManager.PERMISSION_GRANTED)
            toggleRecording();
        else info("يلزم إذن الميكروفون للتسجيل.");
    }
    @Override protected void onDestroy() {
        stopAll(); unregisterReceiver(takeOver); queue.shutdownNow(); super.onDestroy();
    }
}
