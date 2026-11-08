package com.ontrack.vs;

import android.content.Context;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.TextView;

/** Standalone VS visual system. No dependency on the separate AI application. */
public final class VsUi {
    public static final int BACK = Color.rgb(13, 14, 18);
    public static final int CARD = Color.rgb(25, 27, 34);
    public static final int PANEL = Color.rgb(38, 40, 48);
    public static final int RED = Color.rgb(239, 54, 69);
    public static final int GREEN = Color.rgb(34, 170, 96);
    public static final int WHITE = Color.rgb(247, 248, 251);
    public static final int MUTED = Color.rgb(171, 178, 191);
    public static final int EDGE = Color.rgb(60, 63, 74);
    private VsUi() {}

    public static int dp(Context c, float n) {
        return Math.round(n * c.getResources().getDisplayMetrics().density);
    }

    public static GradientDrawable bg(Context c, int color, int border, int r) {
        GradientDrawable d = new GradientDrawable();
        d.setColor(color);
        d.setCornerRadius(dp(c, r));
        if (border != 0) d.setStroke(dp(c, 1), border);
        return d;
    }

    public static TextView text(Context c, String value, float size, int color, boolean strong) {
        TextView v = new TextView(c);
        v.setText(value);
        v.setTextColor(color);
        v.setTextSize(size);
        v.setGravity(Gravity.START | Gravity.CENTER_VERTICAL);
        if (strong) v.setTypeface(Typeface.DEFAULT, Typeface.BOLD);
        return v;
    }

    public static TextView heading(Context c, String value) {
        TextView v = text(c, value, 16, WHITE, true);
        v.setPadding(0, dp(c, 15), 0, dp(c, 7));
        return v;
    }

    public static TextView note(Context c, String value) {
        TextView v = text(c, value, 14, MUTED, false);
        v.setLineSpacing(dp(c, 3), 1.0f);
        v.setPadding(dp(c, 13), dp(c, 13), dp(c, 13), dp(c, 13));
        v.setBackground(bg(c, CARD, EDGE, 13));
        return v;
    }

    public static TextView warning(Context c, String value) {
        TextView v = text(c, value, 14, WHITE, true);
        v.setPadding(dp(c, 14), dp(c, 14), dp(c, 14), dp(c, 14));
        v.setLineSpacing(dp(c, 3), 1.0f);
        v.setBackground(bg(c, Color.rgb(65, 28, 35), RED, 13));
        return v;
    }

    public static EditText input(Context c, String hint, String initial, boolean password) {
        EditText v = new EditText(c);
        v.setHint(hint);
        v.setHintTextColor(MUTED);
        v.setTextColor(WHITE);
        v.setTextSize(15);
        v.setSingleLine(true);
        v.setPadding(dp(c, 15), dp(c, 10), dp(c, 15), dp(c, 10));
        v.setBackground(bg(c, PANEL, EDGE, 12));
        v.setMinHeight(dp(c, 50));
        if (password) v.setInputType(android.text.InputType.TYPE_CLASS_TEXT
            | android.text.InputType.TYPE_TEXT_VARIATION_PASSWORD);
        v.setText(initial);
        return v;
    }

    public static Button button(Context c, String title, boolean primary) {
        Button b = new Button(c);
        b.setAllCaps(false);
        b.setText(title);
        b.setTextSize(15);
        b.setTextColor(WHITE);
        b.setTypeface(Typeface.DEFAULT, Typeface.BOLD);
        b.setMinHeight(dp(c, 52));
        b.setBackground(bg(c, primary ? RED : PANEL, primary ? 0 : EDGE, 13));
        return b;
    }

    public static LinearLayout card(Context c) {
        LinearLayout box = new LinearLayout(c);
        box.setOrientation(LinearLayout.VERTICAL);
        box.setPadding(dp(c, 17), dp(c, 13), dp(c, 17), dp(c, 17));
        box.setBackground(bg(c, CARD, EDGE, 17));
        return box;
    }

    public static void space(LinearLayout l, Context c, int h) {
        View v = new View(c);
        l.addView(v, new LinearLayout.LayoutParams(1, dp(c, h)));
    }

    public static void addSection(LinearLayout root, LinearLayout card, Context c) {
        root.addView(card, new LinearLayout.LayoutParams(-1, -2));
        space(root, c, 14);
    }
}
