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
import android.view.View;
import android.view.WindowManager;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.TextView;

/** Always-visible, explicitly dismissible preview controls; never a SIM audio bridge. */
public final class OverlayService extends Service {
    public static final String ACTION_TAKE_OVER = "com.ontrack.vs.TAKE_OVER";
    private WindowManager manager;
    private View overlay;
    private Button pauseButton;
    private TextView stateLabel;

    @Override public void onCreate() {
        super.onCreate();
        NotificationManager notifications = getSystemService(NotificationManager.class);
        notifications.createNotificationChannel(new NotificationChannel(
            "vs_preview", "VS preview controls", NotificationManager.IMPORTANCE_LOW));
        Notification.Builder notification = Build.VERSION.SDK_INT >= 26
            ? new Notification.Builder(this, "vs_preview")
            : new Notification.Builder(this);
        startForeground(501, notification.setSmallIcon(android.R.drawable.ic_btn_speak_now)
            .setContentTitle("VS - local preview controls")
            .setContentText("Floating preview; not routing cellular call audio").build());

        if (!android.provider.Settings.canDrawOverlays(this)) { stopSelf(); return; }
        manager = (WindowManager) getSystemService(WINDOW_SERVICE);
        LinearLayout panel = new LinearLayout(this);
        panel.setOrientation(LinearLayout.VERTICAL);
        panel.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        panel.setPadding(VsUi.dp(this, 12), VsUi.dp(this, 12),
            VsUi.dp(this, 12), VsUi.dp(this, 12));
        panel.setBackground(VsUi.bg(this, VsUi.CARD, VsUi.RED, 15));

        TextView name = VsUi.text(this, "● VS", 18, VsUi.RED, true);
        panel.addView(name);
        stateLabel = VsUi.text(this, "", 12, VsUi.WHITE, false);
        panel.addView(stateLabel);
        VsUi.space(panel, this, 8);

        pauseButton = VsUi.button(this, "", true);
        pauseButton.setTextSize(13);
        pauseButton.setOnClickListener(v -> {
            boolean paused = !ConfigStore.isPreviewPaused(this);
            ConfigStore.setPreviewPaused(this, paused);
            if (paused) {
                sendBroadcast(new Intent(ACTION_TAKE_OVER).setPackage(getPackageName()));
            }
            refresh();
        });
        panel.addView(pauseButton);
        VsUi.space(panel, this, 6);

        Button open = VsUi.button(this, "فتح إعدادات VS", false);
        open.setTextSize(12);
        open.setOnClickListener(v -> {
            Intent activity = new Intent(this, MainActivity.class);
            activity.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_SINGLE_TOP);
            startActivity(activity);
        });
        panel.addView(open);
        VsUi.space(panel, this, 6);

        Button close = VsUi.button(this, "×  إخفاء الفقاعة", false);
        close.setTextSize(12);
        close.setOnClickListener(v -> stopSelf());
        panel.addView(close);

        WindowManager.LayoutParams params = new WindowManager.LayoutParams(
            VsUi.dp(this, 203), WindowManager.LayoutParams.WRAP_CONTENT,
            WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE
                | WindowManager.LayoutParams.FLAG_NOT_TOUCH_MODAL,
            PixelFormat.TRANSLUCENT);
        params.gravity = Gravity.END | Gravity.CENTER_VERTICAL;
        manager.addView(panel, params);
        overlay = panel;
        refresh();
    }

    private void refresh() {
        boolean paused = ConfigStore.isPreviewPaused(this);
        stateLabel.setText(paused
            ? "المعاينة: متوقفة\nمكالمة SIM: صوت طبيعي"
            : "المعاينة: مسموحة\nمكالمة SIM: صوت طبيعي");
        pauseButton.setText(paused
            ? "▶  استئناف المعاينة"
            : "■  استلام / إيقاف");
    }

    @Override public int onStartCommand(Intent intent, int flags, int startId) {
        refreshIfReady();
        return START_STICKY;
    }

    private void refreshIfReady() {
        if (pauseButton != null && stateLabel != null) refresh();
    }

    @Override public void onDestroy() {
        if (manager != null && overlay != null) {
            try { manager.removeView(overlay); } catch (Exception ignored) {}
            overlay = null;
        }
        super.onDestroy();
    }

    @Override public IBinder onBind(Intent intent) { return null; }
}
