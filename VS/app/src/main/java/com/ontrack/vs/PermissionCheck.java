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
        return "فحص صلاحيات VS (التجربة):\n"
            + (mic ? "✅" : "❌") + " الميكروفون: " + (mic ? "مفعّل" : "مطلوب لتجربة صوتك") + "\n"
            + (overlay ? "✅" : "❌") + " الظهور فوق التطبيقات: " + (overlay ? "مفعّل" : "مطلوب للأيقونة العائمة") + "\n"
            + (notification ? "✅" : "⚠️") + " الإشعارات: "
            + (notification ? "متاحة" : "اختيارية لعرض حالة الأيقونة") + "\n"
            + "اضغط «تفعيل المطلوب» لفتح الإذن الناقص مباشرة.\n"
            + "لا نطلب Accessibility أو سجل المكالمات أو جهات الاتصال.";
    }
    /** Request missing required permissions one at a time with direct destination. */
    public static void activateNext(Activity activity) {
        if (activity.checkSelfPermission(Manifest.permission.RECORD_AUDIO)
                != PackageManager.PERMISSION_GRANTED) {
            if (Build.VERSION.SDK_INT >= 23) {
                activity.requestPermissions(new String[]{Manifest.permission.RECORD_AUDIO}, 401);
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
        Toast.makeText(activity, "كل الصلاحيات المطلوبة للتجربة مفعّلة", Toast.LENGTH_LONG).show();
    }
    public static void openAppDetails(Activity activity) {
        Intent intent = new Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
            Uri.parse("package:" + activity.getPackageName()));
        activity.startActivity(intent);
    }
}
