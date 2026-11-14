package com.ontrack.voiceproof

/** The overlay reports observed PCM audio, not just a requested AI session. */
internal data class OverlayUiModel(
    val running: Boolean,
    val status: String,
    val announcement: String,
    val canTakeover: Boolean,
    val canSwitchSpeaker: Boolean,
    val takeoverTitle: String,
    val takeoverHint: String
)

internal fun overlayUiModel(
    serviceReady: Boolean, enabled: Boolean, inCall: Boolean,
    answered: Boolean, humanControl: Boolean,
    audioActive: Boolean = false, callFailure: String = "", audioDiagnostic: String = "",
    daemonReady: Boolean = true, daemonIssue: String = ""
): OverlayUiModel {
    val running=serviceReady && enabled && daemonReady
    val canTakeover=inCall && answered && !humanControl && enabled && audioActive
    val canSwitchSpeaker=inCall && answered
    val status=when {
        inCall && humanControl -> "أنت استلمت المكالمة من الإيجنت"
        inCall && callFailure.isNotBlank() -> callFailure.take(110)
        inCall && !answered -> "المكالمة قيد الرنين أو الاتصال"
        inCall && audioActive && audioDiagnostic.isNotBlank() -> audioDiagnostic.take(115)
        inCall && audioActive -> "جلسة الصوت متصلة — سماع الطرف الآخر لم يُتحقق منه"
        inCall && !enabled -> "المكالمة تحت تحكم الهاتف — الإيجنت متوقف"
        inCall -> "المكالمة متصلة — جارٍ تجهيز صوت الإيجنت"
        !serviceReady -> "خدمة الهاتف غير نشطة — لم يتأكد التشغيل"
        enabled && !daemonReady -> daemonIssue.ifBlank { "محرك الصوت المحلي متوقف — اضغط لتنشيط المساعد" }.take(115)
        enabled -> "الإيجنت نشط وجاهز"
        else -> "الإيجنت متوقف"
    }
    return OverlayUiModel(
        running=running,
        status=status,
        announcement="IO Voice، $status، اضغط لفتح عناصر التحكم",
        canTakeover=canTakeover,
        canSwitchSpeaker=canSwitchSpeaker,
        takeoverTitle=if(humanControl)"التحكم معاك" else "استلام من الإيجنت",
        takeoverHint=when {
            humanControl -> "استلمت المكالمة"
            !inCall -> "عند وجود مكالمة"
            !answered -> "متاح بعد الرد"
            !enabled -> "الإيجنت متوقف"
            !audioActive -> "بعد اتصال صوت الإيجنت"
            else -> "نقل المكالمة إليك"
        }
    )
}
