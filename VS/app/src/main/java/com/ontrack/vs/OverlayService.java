package com.ontrack.vs;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.Intent;
import android.graphics.PixelFormat;
import android.os.Build;
import android.os.IBinder;
import android.view.Gravity;
import android.view.MotionEvent;
import android.view.View;
import android.view.WindowManager;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.TextView;

/**
 * Small collapsible, movable VS control bubble. This service controls only preview;
 * it is NOT a telephony uplink adapter, and does not mute the user's phone call.
 */
public final class OverlayService extends Service {
    public static final String ACTION_TAKE_OVER = "com.ontrack.vs.TAKE_OVER";
    private WindowManager manager;
    private WindowManager.LayoutParams layout;
    private LinearLayout root, panel;
    private TextView bubble, modeLabel, simLabel;
    private Button enableButton, disableButton;
    private boolean expanded;

    private int d(int size) { return VsUi.dp(this, size); }

    private Button control(String label, int color) {
        Button b = VsUi.button(this, label, true);
        b.setTextSize(13);
        b.setMinHeight(d(43));
        b.setBackground(VsUi.bg(this, color, 0, 12));
        return b;
    }

    @Override public void onCreate() {
        super.onCreate();
        NotificationManager nm = getSystemService(NotificationManager.class);
        if (Build.VERSION.SDK_INT >= 26)
            nm.createNotificationChannel(new NotificationChannel("vs_preview",
                "VS preview controls", NotificationManager.IMPORTANCE_LOW));
        Notification.Builder note = Build.VERSION.SDK_INT >= 26
            ? new Notification.Builder(this, "vs_preview") : new Notification.Builder(this);
        startForeground(501, note.setSmallIcon(android.R.drawable.ic_btn_speak_now)
            .setContentTitle("VS - voice preview controls")
            .setContentText("Tap the small VS bubble to expand or collapse").build());
        if (!android.provider.Settings.canDrawOverlays(this)) { stopSelf(); return; }
        manager = (WindowManager) getSystemService(WINDOW_SERVICE);

        root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        root.setBackground(VsUi.bg(this, VsUi.RED, 0, 28));

        bubble = VsUi.text(this, "VS", 19, VsUi.WHITE, true);
        bubble.setGravity(Gravity.CENTER);
        root.addView(bubble, new LinearLayout.LayoutParams(d(58), d(58)));

        panel = new LinearLayout(this);
        panel.setOrientation(LinearLayout.VERTICAL);
        panel.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        panel.setPadding(d(11), d(8), d(11), d(12));
        panel.setVisibility(View.GONE);
        root.addView(panel, new LinearLayout.LayoutParams(-1, -2));

        modeLabel = VsUi.text(this, "", 13, VsUi.WHITE, true);
        panel.addView(modeLabel);
        VsUi.space(panel, this, 6);

        LinearLayout toggles = new LinearLayout(this);
        toggles.setOrientation(LinearLayout.HORIZONTAL);
        toggles.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        enableButton = control("▶ تشغيل", VsUi.GREEN);
        disableButton = control("■ إيقاف", VsUi.RED);
        toggles.addView(enableButton, new LinearLayout.LayoutParams(0, d(45), 1f));
        VsUi.space(toggles, this, 6);
        toggles.addView(disableButton, new LinearLayout.LayoutParams(0, d(45), 1f));
        panel.addView(toggles);
        VsUi.space(panel, this, 10);

        simLabel = VsUi.text(this,
            "الشريحة: لا يوجد حقن صوت مؤكد", 11, VsUi.MUTED, false);
        panel.addView(simLabel);
        VsUi.space(panel, this, 8);

        Button check = control("فحص حالة الشريحة", VsUi.PANEL);
        check.setTextSize(12);
        check.setOnClickListener(v -> simLabel.setText(SimAudioStatus.report(this)));
        panel.addView(check);
        VsUi.space(panel, this, 5);

        Button open = control("فتح التطبيق", VsUi.PANEL);
        open.setTextSize(12);
        open.setOnClickListener(v -> {
            Intent activity = new Intent(this, MainActivity.class);
            activity.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_SINGLE_TOP);
            startActivity(activity);
        });
        panel.addView(open);
        VsUi.space(panel, this, 5);

        Button close = control("×  إغلاق الفقاعة", VsUi.PANEL);
        close.setTextSize(12);
        close.setOnClickListener(v -> stopSelf());
        panel.addView(close);

        enableButton.setOnClickListener(v -> {
            ConfigStore.setPreviewPaused(this, false);
            refresh();
            simLabel.setText(SimAudioStatus.callIsActive(this)
                ? "المكالمة نشطة لكن تحويل الصوت مش متصل بـSIM. لن نشغّل الصوت محليًا أثناء المكالمة."
                : "المعاينة جاهزة. افتح VS لتجربة Fish Audio.");
        });
        disableButton.setOnClickListener(v -> {
            ConfigStore.setPreviewPaused(this, true);
            sendBroadcast(new Intent(ACTION_TAKE_OVER).setPackage(getPackageName()));
            refresh();
        });

        layout = new WindowManager.LayoutParams(d(58), d(58),
            WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE
                | WindowManager.LayoutParams.FLAG_NOT_TOUCH_MODAL,
            PixelFormat.TRANSLUCENT);
        layout.gravity = Gravity.TOP | Gravity.END;
        layout.x = d(8);
        layout.y = d(235);
        manager.addView(root, layout);
        bubble.setOnTouchListener(new View.OnTouchListener() {
            private float startX, startY;
            private int baseX, baseY;
            private boolean moved;
            @Override public boolean onTouch(View v, MotionEvent event) {
                switch (event.getActionMasked()) {
                    case MotionEvent.ACTION_DOWN:
                        startX = event.getRawX();
                        startY = event.getRawY();
                        baseX = layout.x; baseY = layout.y;
                        moved = false;
                        return true;
                    case MotionEvent.ACTION_MOVE:
                        float dx = event.getRawX() - startX;
                        float dy = event.getRawY() - startY;
                        if (Math.abs(dx) > d(8) || Math.abs(dy) > d(8)) moved = true;
                        if (moved) {
                            layout.x = Math.max(0, baseX - Math.round(dx));
                            layout.y = Math.max(0, baseY + Math.round(dy));
                            try { manager.updateViewLayout(root, layout); } catch (Exception ignored) {}
                        }
                        return true;
                    case MotionEvent.ACTION_UP:
                        if (!moved) setExpanded(!expanded);
                        return true;
                    default: return true;
                }
            }
        });
        refresh();
    }

    private void setExpanded(boolean next) {
        expanded = next;
        if (root == null || layout == null || manager == null) return;
        root.setBackground(VsUi.bg(this, next ? VsUi.CARD : VsUi.RED,
            next ? VsUi.EDGE : 0, next ? 17 : 29));
        bubble.setText(next ? "VS  ▾" : "VS");
        bubble.setBackground(VsUi.bg(this, next ? VsUi.RED : VsUi.RED, 0, 28));
        panel.setVisibility(next ? View.VISIBLE : View.GONE);
        layout.width = d(next ? 244 : 58);
        layout.height = next ? WindowManager.LayoutParams.WRAP_CONTENT : d(58);
        try { manager.updateViewLayout(root, layout); } catch (Exception ignored) {}
    }

    private void refresh() {
        if (modeLabel == null) return;
        boolean paused = ConfigStore.isPreviewPaused(this);
        modeLabel.setText(paused
            ? "● المعاينة: متوقفة"
            : "● المعاينة: جاهزة فقط");
        modeLabel.setTextColor(paused ? VsUi.RED : VsUi.GREEN);
        // Always expose both controls, using color and brightness for the state.
        enableButton.setEnabled(true);
        disableButton.setEnabled(true);
        enableButton.setAlpha(paused ? 0.85f : 1f);
        disableButton.setAlpha(paused ? 1f : 0.85f);
        if (simLabel != null) simLabel.setText(SimAudioStatus.report(this));
    }

    @Override public int onStartCommand(Intent intent, int flags, int startId) {
        refresh();
        return START_STICKY;
    }

    @Override public void onDestroy() {
        if (manager != null && root != null) {
            try { manager.removeView(root); } catch (Exception ignored) {}
            root = null;
        }
        super.onDestroy();
    }

    @Override public IBinder onBind(Intent intent) { return null; }
}
