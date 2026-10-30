package com.ontrack.voiceproof

import android.content.Context
import android.content.pm.PackageManager
import android.os.Process
import com.ontrack.voiceproof.adb.AdbTransport
import com.ontrack.voiceproof.ui.ShellGrant
import org.json.JSONObject

object OnTrackSetupDiagnostics {
    private fun prefs(context: Context) = context.getSharedPreferences("ontrack_proof", Context.MODE_PRIVATE)

    /** Called at the real failure site, before generic vendor advice hides the response. */
    fun recordGrant(context: Context, grant: String, readBack: String?) {
        val report = JSONObject().apply {
            put("timestamp_ms", System.currentTimeMillis())
            put("source", "setup_finish")
            put("package", context.packageName)
            put("app_uid", Process.myUid())
            put("android_user", Process.myUid() / 100000)
            put("grant_output", grant.take(6000))
            put("read_back", readBack?.take(6000) ?: "UNAVAILABLE")
            put("simulated_refusal", ShellGrant.actAsRefused)
        }
        prefs(context).edit().putString("setup_diagnostics", report.toString()).commit()
    }

    /** Explicit user-requested check. Uses current Android user, not an assumed owner profile. */
    suspend fun collect(context: Context): String {
        val report = JSONObject().apply {
            put("timestamp_ms", System.currentTimeMillis())
            put("source", "explicit_diagnostic")
            put("package", context.packageName)
            put("app_uid", Process.myUid())
            put("android_user", Process.myUid() / 100000)
            put("app_process_permission_check", context.checkSelfPermission(SetupDiagnosticFormat.PERMISSION) == PackageManager.PERMISSION_GRANTED)
            put("simulated_refusal", ShellGrant.actAsRefused)
        }
        suspend fun command(key: String, value: String) {
            val result = AdbTransport.exec(value, timeoutMs = 10000)
            report.put(key, result.getOrElse { "ERROR: ${it.message}" }.take(6000))
        }
        try {
            val connected = AdbTransport.isConnected || AdbTransport.autoConnect(context, timeoutMs = 4000).isSuccess
            report.put("adb_connected", connected)
            check(connected) { "ADB غير متصل. استخدم إعداد الاتصال أو زر إعادة الاقتران، ثم أعد التشخيص." }
            command("shell_identity", "id")
            val user = Process.myUid() / 100000
            command("before", SetupDiagnosticFormat.readBack(context.packageName, user))
            command("grant_output", SetupDiagnosticFormat.grant(context.packageName, user))
            command("read_back", SetupDiagnosticFormat.readBack(context.packageName, user))
        } catch (error: Exception) {
            report.put("diagnostic_error", error.message ?: error.javaClass.simpleName)
        } finally {
            prefs(context).edit().putString("setup_diagnostics", report.toString()).commit()
        }
        return "تم حفظ رد Android الأصلي. احفظ تقرير JSON أو انسخ التفاصيل.\n" + report.toString(2)
    }
}
