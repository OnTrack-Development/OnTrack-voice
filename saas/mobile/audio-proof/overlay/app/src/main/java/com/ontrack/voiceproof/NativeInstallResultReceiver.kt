package com.ontrack.voiceproof

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.pm.PackageInstaller
import android.os.Build

/** Updates never silently install: Android owns all approval and errors. */
class NativeInstallResultReceiver:BroadcastReceiver(){
    companion object {const val ACTION_RESULT="com.ontrack.voiceproof.NATIVE_INSTALL_RESULT"}
    override fun onReceive(context:Context,intent:Intent?) {
        if(intent?.action!=ACTION_RESULT)return
        val status=intent.getIntExtra(PackageInstaller.EXTRA_STATUS,PackageInstaller.STATUS_FAILURE)
        val detail=intent.getStringExtra(PackageInstaller.EXTRA_STATUS_MESSAGE).orEmpty()
            .replace(Regex("[\\r\\n\\t]")," ").take(240)
        val store=context.getSharedPreferences("ontrack_link",Context.MODE_PRIVATE)
        // Terminal results must unlock retries; only pending user approval blocks duplicates.
        if(status!=PackageInstaller.STATUS_PENDING_USER_ACTION){
            context.getSharedPreferences("ontrack_native_installer",Context.MODE_PRIVATE)
                .edit().remove("session_id").remove("created_at").apply()
        }
        when(status){
            PackageInstaller.STATUS_PENDING_USER_ACTION->{
                @Suppress("DEPRECATION")
                val consent=if(Build.VERSION.SDK_INT>=33)
                    intent.getParcelableExtra(Intent.EXTRA_INTENT,Intent::class.java)
                else intent.getParcelableExtra(Intent.EXTRA_INTENT)
                if(consent!=null) {
                    try {
                        consent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                        context.startActivity(consent)
                        store.edit().putString("last_native_install","بانتظار موافقة Android. إذا ظهر Play Protect اختر Scan app لفحص النسخة؛ لا تعطّل الحماية.").apply()
                    }catch(_:Exception){
                        store.edit().putString("last_native_install","تعذر فتح موافقة التثبيت؛ افتح إعدادات تحديث التطبيق").apply()
                    }
                }else{
                    store.edit().putString("last_native_install",
                        "لم يرسل Android نافذة الموافقة؛ راجع إصدار التطبيق وإذن التثبيت").apply()
                }
            }
            PackageInstaller.STATUS_SUCCESS->store.edit()
                .putString("last_native_install","تم تثبيت التحديث بنجاح").apply()
            else->{
                val code=status
                val reason=when(status){
                    PackageInstaller.STATUS_FAILURE_INVALID->"صيغة حزمة APK مرفوضة"
                    PackageInstaller.STATUS_FAILURE_CONFLICT->"تعارض مع النسخة المثبتة"
                    PackageInstaller.STATUS_FAILURE_INCOMPATIBLE->"الإصدار غير متوافق مع هذا الهاتف"
                    PackageInstaller.STATUS_FAILURE_BLOCKED->"سياسة Android منعت التثبيت"
                    PackageInstaller.STATUS_FAILURE_STORAGE->"لا توجد مساحة تخزين كافية"
                    PackageInstaller.STATUS_FAILURE_ABORTED->"تم إلغاء التثبيت"
                    else->"فشل تثبيت Android"
                }
                store.edit().putString("last_native_install",
                    "$reason · status $code"+if(detail.isBlank())"" else " · $detail").apply()
            }
        }
    }
}
