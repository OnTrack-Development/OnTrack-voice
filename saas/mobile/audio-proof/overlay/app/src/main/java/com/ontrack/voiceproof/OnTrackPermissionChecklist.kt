package com.ontrack.voiceproof

/** Diagnostic-only permission inventory. Never requests grants by itself.
 *
 * Inbound SIM audio is handled by the authenticated local PCM bridge; the
 * recording/microphone/media-library permissions are intentionally absent.
 * Special Android settings are optional and always require a user tap.
 */
internal data class VoicePermissionFacts(
    val phoneState: Boolean,
    val notifications: Boolean?,
    val outboundCalling: Boolean,
    val overlay: Boolean,
    val packageInstall: Boolean,
    val backgroundRecovery: Boolean
)

internal object OnTrackPermissionChecklist {
    fun evaluate(f: VoicePermissionFacts): List<VoiceSetupCheck> {
        val checks = mutableListOf<VoiceSetupCheck>()
        fun permission(
            id: String, name: String, granted: Boolean,
            required: Boolean, purpose: String, action: CheckAction,
            button: String
        ) {
            val state = when {
                granted -> CheckState.READY
                required -> CheckState.ACTION
                else -> CheckState.OPTIONAL
            }
            val detail = if (granted) {
                "مفعّل ✓ — $purpose"
            } else if (required) {
                "ناقص — $purpose. اضغط تفعيل للسماح من Android."
            } else {
                "غير مفعّل (اختياري) — $purpose. مش مطلوب لتشغيل المكالمات الواردة."
            }
            checks += VoiceSetupCheck(
                id, name, state, detail,
                if (granted) CheckAction.NONE else action,
                if (granted) "" else button
            )
        }
        permission(
            "permission_phone", "إذن حالة الهاتف / READ_PHONE_STATE",
            f.phoneState, true, "تحديد الشريحة المرتبطة ومتابعة المكالمات",
            CheckAction.PHONE_PERMISSION, "السماح بحالة الهاتف"
        )
        if (f.notifications != null) {
            permission(
                "permission_notifications", "إشعارات التطبيق",
                f.notifications, false, "إشعارات التشغيل والتحديثات",
                CheckAction.NOTIFICATIONS, "فتح إذن الإشعارات"
            )
        }
        permission(
            "permission_calls", "إجراء مكالمات صادرة",
            f.outboundCalling, false, "الحملات أو إبلاغ المدير فقط",
            CheckAction.OUTBOUND_CALLS, "السماح بالمكالمات الصادرة"
        )
        permission(
            "permission_overlay", "الظهور فوق التطبيقات",
            f.overlay, false, "نافذة التحكم العائمة فقط",
            CheckAction.OVERLAY, "فتح إعداد النافذة العائمة"
        )
        permission(
            "permission_install", "تثبيت تحديثات Android",
            f.packageInstall, false, "تثبيت APK بعد موافقتك، مش مطلوب للمكالمات",
            CheckAction.INSTALL_UPDATES, "فتح السماح بالتحديثات"
        )
        checks += VoiceSetupCheck(
            "permission_background", "استعادة ADB بعد إعادة التشغيل",
            if (f.backgroundRecovery) CheckState.READY else CheckState.OPTIONAL,
            if (f.backgroundRecovery) "مفعّلة — صلاحية Android الخاصة محفوظة."
            else "غير مفعّلة (اختياري) — قد تحتاج تجهيز ربط ADB مرة واحدة لاستعادة المحرك بعد إعادة التشغيل.",
            if (f.backgroundRecovery) CheckAction.NONE else CheckAction.RECONNECT,
            if (f.backgroundRecovery) "" else "فحص استعادة ADB"
        )
        checks += VoiceSetupCheck(
            "permission_not_needed", "تسجيل المكالمات والميكروفون وسجل المكالمات",
            CheckState.OPTIONAL,
            "غير مطلوبة لتشغيل Voice AI؛ التطبيق لا يطلب أذونات تسجيل الصوت أو قراءة التسجيلات أو سجل المكالمات."
        )
        return checks
    }
}
