package com.ontrack.vs;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.Intent;
import android.graphics.PixelFormat;
import android.graphics.Color;
import android.os.Build;
import android.os.IBinder;
import android.provider.Settings;
import android.view.Gravity;
import android.view.WindowManager;
import android.view.View;
import android.widget.Button;
import android.widget.LinearLayout;

/** Visible manual test overlay. No accessibility, no claim of SIM uplink audio injection. */
public final class OverlayService extends Service {
    public static final String ACTION_TAKE_OVER = "com.ontrack.vs.TAKE_OVER";
    private WindowManager manager;
    private View overlay;
    @Override public void onCreate() {
        super.onCreate();
        NotificationManager notifications = getSystemService(NotificationManager.class);
        notifications.createNotificationChannel(new NotificationChannel(
            "vs_preview", "VS floating preview", NotificationManager.IMPORTANCE_LOW));
        Notification.Builder builder = Build.VERSION.SDK_INT >= 26
            ? new Notification.Builder(this, "vs_preview") : new Notification.Builder(this);
        startForeground(501, builder.setSmallIcon(android.R.drawable.ic_btn_speak_now)
            .setContentTitle("VS — Fish Audio").setContentText("Preview controls active").build());
        if (!Settings.canDrawOverlays(this)) { stopSelf(); return; }
        manager = (WindowManager) getSystemService(WINDOW_SERVICE);
        LinearLayout row = new LinearLayout(this);
        row.setOrientation(LinearLayout.VERTICAL);
        row.setPadding(12, 12, 12, 12);
        row.setBackgroundColor(0xDD151515);
        Button open = new Button(this);
        open.setText("VS • تجربة الصوت");
        open.setOnClickListener(v -> {
            Intent intent = new Intent(this, MainActivity.class);
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_SINGLE_TOP);
            startActivity(intent);
        });
        Button handoff = new Button(this);
        handoff.setText("إيقاف VS / استلام");
        handoff.setOnClickListener(v -> {
            sendBroadcast(new Intent(ACTION_TAKE_OVER).setPackage(getPackageName()));
            stopSelf();
        });
        row.addView(open); row.addView(handoff);
        WindowManager.LayoutParams params = new WindowManager.LayoutParams(
            WindowManager.LayoutParams.WRAP_CONTENT, WindowManager.LayoutParams.WRAP_CONTENT,
            WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE,
            PixelFormat.TRANSLUCENT);
        params.gravity = Gravity.END | Gravity.CENTER_VERTICAL;
        manager.addView(row, params);
        overlay = row;
    }
    @Override public void onDestroy() {
        if (manager != null && overlay != null) manager.removeView(overlay);
        super.onDestroy();
    }
    @Override public IBinder onBind(Intent intent) { return null; }
}
