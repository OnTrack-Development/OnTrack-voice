package com.ontrack.vs;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.Paint;
import android.graphics.Path;
import android.graphics.PixelFormat;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.os.Build;
import android.os.IBinder;
import android.provider.Settings;
import android.view.Gravity;
import android.view.MotionEvent;
import android.view.View;
import android.view.WindowManager;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;

/**
 * VS adaptation of OnTrack Voice's CallOverlay:
 * 58dp draggable round bubble and expandable 284dp RTL 2x2 call controls.
 * Buttons send real service actions, not simulated preview-only states.
 * No Accessibility service and no call recordings.
 */
public final class OverlayService extends Service {
    public static final String ACTION_TAKE_OVER = "com.ontrack.vs.TAKE_OVER";
    public static final String ACTION_LIVE_STATUS = "com.ontrack.vs.LIVE_STATUS";
    private static final int RED = Color.rgb(220, 20, 45);
    private static final int GREEN = Color.rgb(27, 178, 103);
    private static final int BLACK = Color.rgb(15, 15, 18);
    private static final int WHITE = Color.WHITE;
    private static final int GRAY = Color.rgb(151, 151, 160);
    private WindowManager window;
    private WindowManager.LayoutParams params;
    private LinearLayout root, panel, row1, row2;
    private FrameLayout circle;
    private TextView status;
    private Glyph orbGlyph;
    private boolean expanded;
    private String rendered = "";
    private final BroadcastReceiver updates = new BroadcastReceiver() {
        @Override public void onReceive(Context context, Intent intent) { refresh(); }
    };

    private int dp(int amount) { return (int)(amount * getResources().getDisplayMetrics().density + .5f); }
    private GradientDrawable shape(int color, int radius, int stroke) {
        GradientDrawable d = new GradientDrawable();
        d.setColor(color);
        d.setCornerRadius(dp(radius));
        if (stroke != 0) d.setStroke(dp(2), stroke);
        return d;
    }
    private TextView text(String value, int size, int color, boolean bold) {
        TextView v = new TextView(this);
        v.setText(value);
        v.setTextSize(size);
        v.setTextColor(color);
        v.setGravity(Gravity.CENTER);
        v.setTypeface(Typeface.create("sans-serif", bold ? Typeface.BOLD : Typeface.NORMAL));
        return v;
    }

    /** Native vector line icons ported from the existing OnTrack Voice overlay. */
    private final class Glyph extends View {
        private final String symbol;
        private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
        Glyph(String symbol, int color) {
            super(OverlayService.this);
            this.symbol = symbol;
            paint.setColor(color);
            paint.setStyle(Paint.Style.STROKE);
            paint.setStrokeWidth(2.1f);
            paint.setStrokeCap(Paint.Cap.ROUND);
            paint.setStrokeJoin(Paint.Join.ROUND);
        }
        @Override protected void onDraw(Canvas c) {
            super.onDraw(c);
            int save = c.save();
            float side = Math.min(getWidth(), getHeight());
            c.translate((getWidth() - side)/2f, (getHeight() - side)/2f);
            c.scale(side/24f, side/24f);
            Path p = new Path();
            switch (symbol) {
                case "power":
                    c.drawArc(5,5,19,19,-54,288,false,paint);
                    c.drawLine(12,3,12,12,paint); break;
                case "person":
                    c.drawCircle(12,7,3.1f,paint);
                    c.drawArc(5,11,19,23,187,166,false,paint); break;
                case "speaker":
                    p.moveTo(3,9); p.lineTo(7.4f,9); p.lineTo(12,5);
                    p.lineTo(12,19); p.lineTo(7.4f,15); p.lineTo(3,15); p.close();
                    c.drawPath(p,paint);
                    c.drawArc(10,6,20,18,-66,132,false,paint);
                    c.drawArc(7.5f,2.5f,23,21.5f,-62,124,false,paint); break;
                case "home":
                    p.moveTo(3,11);p.lineTo(12,4);p.lineTo(21,11);
                    p.moveTo(6,10);p.lineTo(6,20);p.lineTo(18,20);p.lineTo(18,10);
                    p.moveTo(10,20);p.lineTo(10,14);p.lineTo(14,14);p.lineTo(14,20);
                    c.drawPath(p,paint); break;
                default:
                    c.drawLine(6,6,18,18,paint);c.drawLine(18,6,6,18,paint);
            }
            c.restoreToCount(save);
        }
    }

    private LinearLayout tile(String icon, String title, String hint, int tint,
                              boolean enabled, Runnable action) {
        LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL);
        box.setGravity(Gravity.CENTER);
        box.setPadding(dp(6),dp(10),dp(6),dp(8));
        box.setBackground(shape(Color.rgb(31,31,35),14,Color.rgb(61,61,65)));
        box.setAlpha(enabled ? 1f : .44f);
        box.setEnabled(enabled);
        box.setClickable(enabled);
        box.setContentDescription(title + "، " + hint);
        FrameLayout iconBox = new FrameLayout(this);
        iconBox.setBackground(shape(Color.rgb(63,25,34),12,0));
        iconBox.addView(new Glyph(icon,tint),new FrameLayout.LayoutParams(dp(24),dp(24),Gravity.CENTER));
        box.addView(iconBox,new LinearLayout.LayoutParams(dp(44),dp(44)));
        box.addView(text(title,12,WHITE,true),new LinearLayout.LayoutParams(-1,dp(27)));
        TextView help = text(hint,10,Color.rgb(183,183,190),false);
        help.setMaxLines(2);
        box.addView(help,new LinearLayout.LayoutParams(-1,dp(29)));
        if (enabled) box.setOnClickListener(v -> action.run());
        return box;
    }

    private void addTile(LinearLayout row, LinearLayout item, boolean spaced) {
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(0,dp(119),1f);
        if (spaced) lp.setMarginStart(dp(8));
        row.addView(item,lp);
    }

    private void sendLive(String action) {
        Intent intent = new Intent(this,LiveCallService.class).setAction(action);
        if (LiveCallService.ACTION_START.equals(action) && Build.VERSION.SDK_INT >= 26)
            startForegroundService(intent);
        else startService(intent);
    }

    private void startOrStop() {
        if (LiveCallService.active || LiveCallService.connecting) {
            sendLive(LiveCallService.ACTION_STOP);
        } else if (SimAudioStatus.callIsActive(this)) {
            ConfigStore.setPreviewPaused(this,true);
            try { sendLive(LiveCallService.ACTION_START); }
            catch (Exception e) { status.setText("تعذر تشغيل الجسر: " + e.getClass().getSimpleName()); }
        } else {
            status.setText("لا توجد مكالمة شريحة نشطة");
        }
        collapse();
    }

    private void takeover() {
        sendLive(LiveCallService.ACTION_STOP);
        ConfigStore.setPreviewPaused(this,true);
        sendBroadcast(new Intent(ACTION_TAKE_OVER).setPackage(getPackageName()));
        collapse();
    }

    private void renderActions() {
        if (!expanded || row1 == null || row2 == null) return;
        row1.removeAllViews();
        row2.removeAllViews();
        boolean call = SimAudioStatus.callIsActive(this);
        boolean running = LiveCallService.active;
        boolean transitioning = LiveCallService.connecting;
        addTile(row1,tile("power",running || transitioning ? "إيقاف تغيير الصوت" : "تشغيل تغيير الصوت",
            running || transitioning ? "استعادة مايك المكالمة" : "تشغيل Fish للمكالمة",
            running || transitioning ? RED : GREEN,true,this::startOrStop),false);
        addTile(row1,tile("person","استلام المكالمة","إيقاف Fish والعودة لصوتك",
            GREEN,call && (running || transitioning),this::takeover),true);
        addTile(row2,tile("speaker","مخرج الصوت","تبديل السماعة أثناء المكالمة",
            RED,call && running,() -> {
                try { sendLive(LiveCallService.ACTION_SPEAKER); }
                catch (Exception e) { status.setText("تعذر تبديل السماعة"); }
                collapse();
            }),false);
        addTile(row2,tile("home","فتح التطبيق","إعدادات Fish والصوت",RED,true,() -> {
            collapse();
            Intent activity = new Intent(this,MainActivity.class);
            activity.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_SINGLE_TOP);
            startActivity(activity);
        }),true);
    }

    private void collapse() {
        expanded = false;
        if (panel != null) panel.setVisibility(View.GONE);
        if (orbGlyph != null) orbGlyph.invalidate();
        updateLayout();
    }

    private void updateLayout() {
        if (window != null && root != null && params != null)
            try { window.updateViewLayout(root,params); } catch (Exception ignored) { }
    }

    private void attach() {
        root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setGravity(Gravity.END);
        root.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        circle = new FrameLayout(this);
        circle.setBackground(shape(BLACK,60,RED));
        circle.setElevation(dp(7));
        circle.setContentDescription("فتح عناصر تحكم VS");
        orbGlyph = new Glyph("power",WHITE);
        circle.addView(orbGlyph,new FrameLayout.LayoutParams(dp(26),dp(26),Gravity.CENTER));
        LinearLayout.LayoutParams orbParams=new LinearLayout.LayoutParams(dp(58),dp(58));
        orbParams.gravity = Gravity.END;
        root.addView(circle,orbParams);

        panel = new LinearLayout(this);
        panel.setOrientation(LinearLayout.VERTICAL);
        panel.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        panel.setPadding(dp(13),dp(13),dp(13),dp(13));
        panel.setBackground(shape(BLACK,20,Color.rgb(52,52,57)));
        panel.setElevation(dp(10));
        panel.setVisibility(View.GONE);
        panel.addView(text("VS • Fish Audio",16,WHITE,true),
            new LinearLayout.LayoutParams(-1,dp(28)));
        status = text("الحالة",12,GRAY,false);
        panel.addView(status,new LinearLayout.LayoutParams(-1,dp(30)));
        row1 = new LinearLayout(this);
        row1.setOrientation(LinearLayout.HORIZONTAL);
        row2 = new LinearLayout(this);
        row2.setOrientation(LinearLayout.HORIZONTAL);
        panel.addView(row1);
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(-1,-2);
        lp.topMargin=dp(8);
        panel.addView(row2,lp);
        TextView note = text("التحويل يخص المكالمة الحالية فقط؛ لا يوجد تسجيل",10,GRAY,false);
        LinearLayout.LayoutParams noteLp = new LinearLayout.LayoutParams(-1,dp(30));
        noteLp.topMargin=dp(6);
        panel.addView(note,noteLp);
        TextView close = text("×   إغلاق الفقاعة",12,WHITE,false);
        close.setBackground(shape(Color.rgb(31,31,35),12,0));
        close.setOnClickListener(v -> stopSelf());
        panel.addView(close,new LinearLayout.LayoutParams(-1,dp(36)));
        LinearLayout.LayoutParams menuLp = new LinearLayout.LayoutParams(dp(284),-2);
        menuLp.topMargin=dp(8);menuLp.gravity=Gravity.END;
        root.addView(panel,menuLp);
        params=new WindowManager.LayoutParams(-2,-2,
            WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE
                | WindowManager.LayoutParams.FLAG_NOT_TOUCH_MODAL,
            PixelFormat.TRANSLUCENT);
        params.gravity=Gravity.TOP | Gravity.END;
        params.x=dp(14);params.y=dp(110);
        try { window.addView(root,params); }
        catch (Exception e) { root=null; panel=null; params=null;return; }

        circle.setOnTouchListener(new View.OnTouchListener() {
            float x,y; int startX,startY;boolean dragged;
            @Override public boolean onTouch(View v,MotionEvent event) {
                switch(event.getActionMasked()) {
                    case MotionEvent.ACTION_DOWN:
                        x=event.getRawX();y=event.getRawY();
                        startX=params.x;startY=params.y;dragged=false;return true;
                    case MotionEvent.ACTION_MOVE:
                        if (Math.abs(event.getRawX()-x)>dp(10)
                             || Math.abs(event.getRawY()-y)>dp(10)) dragged=true;
                        if (dragged) {
                            params.x=Math.max(0,Math.min(dp(240),startX+Math.round(x-event.getRawX())));
                            params.y=Math.max(dp(28),Math.min(getResources().getDisplayMetrics().heightPixels-dp(170),
                                startY+Math.round(event.getRawY()-y)));
                            updateLayout();
                        }
                        return true;
                    case MotionEvent.ACTION_UP:
                        if (!dragged) {
                            expanded=!expanded;
                            panel.setVisibility(expanded ? View.VISIBLE : View.GONE);
                            if (expanded) renderActions();
                            updateLayout();
                        }
                        return true;
                    default:return true;
                }
            }
        });
    }

    private void refresh() {
        if (circle == null) return;
        boolean working = LiveCallService.active;
        boolean starting = LiveCallService.connecting;
        circle.setBackground(shape(working ? GREEN : RED,60,BLACK));
        String message = starting ? "جارٍ ربط صوت الشريحة..."
            : working ? "مسار الصوت تجريبي؛ تأكيد السماع من الطرف الآخر مطلوب"
            : LiveCallService.status;
        if (status != null) {
            status.setText("● " + message);
            status.setTextColor(working ? GREEN : starting ? WHITE : RED);
        }
        if (expanded) {
            String token=working+":"+starting+":"+SimAudioStatus.callIsActive(this);
            if (!token.equals(rendered)) { rendered=token;renderActions(); }
        }
    }

    @Override public void onCreate() {
        super.onCreate();
        NotificationManager nm = getSystemService(NotificationManager.class);
        if (Build.VERSION.SDK_INT >= 26)
            nm.createNotificationChannel(new NotificationChannel("vs_preview",
                "VS floating controls",NotificationManager.IMPORTANCE_LOW));
        Notification.Builder notification = Build.VERSION.SDK_INT >= 26
            ? new Notification.Builder(this,"vs_preview") : new Notification.Builder(this);
        startForeground(501,notification.setSmallIcon(android.R.drawable.ic_btn_speak_now)
            .setContentTitle("VS • Fish Audio").setContentText("VS floating call controls").build());
        if (!Settings.canDrawOverlays(this)) { stopSelf();return; }
        window=(WindowManager)getSystemService(Context.WINDOW_SERVICE);
        attach();
        IntentFilter filter=new IntentFilter(ACTION_LIVE_STATUS);
        if (Build.VERSION.SDK_INT >= 33) registerReceiver(updates,filter,Context.RECEIVER_NOT_EXPORTED);
        else registerReceiver(updates,filter);
        refresh();
    }
    @Override public int onStartCommand(Intent intent,int flags,int id) { refresh();return START_STICKY; }
    @Override public void onDestroy() {
        try { unregisterReceiver(updates); } catch (Exception ignored) {}
        if (root != null && window != null)
            try { window.removeView(root); } catch (Exception ignored) {}
        root=null;panel=null;circle=null;
        // Hiding the bubble must never unexpectedly end a live phone call.
        super.onDestroy();
    }
    @Override public IBinder onBind(Intent intent) { return null; }
}
