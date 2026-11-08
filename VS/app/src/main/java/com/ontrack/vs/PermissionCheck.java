package com.ontrack.vs;

import android.Manifest;
import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.provider.Settings;
import android.widget.Toast;

/** Inspect ONLY the permissions currently needed by the VS preview.
 *  No Accessibility, call log, contacts, or phone-state permissions.
 */
public final class PermissionCheck {
    private PermissionCheck() {}
    public static String report(Activity activity) {
        boolean mic = activity.checkSelfPermission(Manifest.permission.RECORD_AUDIO)
            == PackageManager.PERMISSION_GRANTED;
        boolean overlay = Settings.canDrawOverlays(activity);
        boolean notification = Build.VERSION.SDK_INT < 33
            || activity.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS)
            == PackageManager.PERMISSION_GRANTED;
        return "صلاحيات تطبيق VS (للتجربة فقط):\n"
            + (mic ? "✓" : "✗") + " الميكروفون: "
            + (mic ? "مفعّل" : "غير مفعّل - مطلوب لالتقاط المايك المباشر بدون حفظ صوت") + "\n"
            + (overlay ? "✓" : "✗") + " الظهور فوق التطبيقات: "
            + (overlay ? "مفعّل" : "غير مفعّل - للفقاعة العائمة") + "\n"
            + (notification ? "✓" : "•") + " الإشعارات: "
            + (notification ? "متاحة" : "اختيارية") + "\n\n"
            + "مسار إرسال الصوت داخل SIM: غير متاح للتطبيق العادي. "
            + "هذه ليست صلاحية ناقصة يمكن تفعيلها من الإعدادات. "
            + "الضغط على «تفعيل الإذن» يفتح إعدادات المعاينة فقط.";
    }
    /** Request missing required permissions one at a time with direct destination. */
    public static void activateNext(Activity activity) {
        if (activity.checkSelfPermission(Manifest.permission.RECORD_AUDIO)
                != PackageManager.PERMISSION_GRANTED) {
            if (Build.VERSION.SDK_INT >= 23) {
                activity.requestPermissions(new String[]{Manifest.permission.RECORD_AUDIO}, 402);
            }
            return;
        }
        if (!Settings.canDrawOverlays(activity)) {
            try {
                activity.startActivity(new Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
                    Uri.parse("package:" + activity.getPackageName())));
            } catch (Exception e) {
                openAppDetails(activity);
            }
            return;
        }
        Toast.makeText(activity, "تصاريح المعاينة مكتملة. إرسال صوت Fish عبر SIM غير متاح.", Toast.LENGTH_LONG).show();
    }
    public static void openAppDetails(Activity activity) {
        Intent intent = new Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
            Uri.parse("package:" + activity.getPackageName()));
        activity.startActivity(intent);
    }
}
