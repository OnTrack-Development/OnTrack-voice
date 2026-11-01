package com.ontrack.voiceproof

/** The same truthful action rules power both collapsed and expanded native controls. */
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
    connected: Boolean, enabled: Boolean, inCall: Boolean,
    answered: Boolean, humanControl: Boolean
): OverlayUiModel {
    val running=connected && enabled
    val canTakeover=inCall && answered && !humanControl && enabled
    val canSwitchSpeaker=inCall && answered
    val status=when {
        inCall && humanControl -> "أنت استلمت المكالمة من الإيجنت"
        inCall && !answered -> "المكالمة قيد الرنين أو الاتصال"
        inCall && enabled -> "المكالمة متصلة — الإيجنت قيد التشغيل"
        inCall -> "المكالمة تحت تحكم الهاتف"
        !connected -> "اتصال السيرفر غير متاح"
        enabled -> "الإيجنت نشط وجاهز"
        else -> "الإيجنت متوقف"
    }
    return OverlayUiModel(
        running=running,
        status=status,
        announcement="OnTrack Voice، $status، اضغط لفتح عناصر التحكم",
        canTakeover=canTakeover,
        canSwitchSpeaker=canSwitchSpeaker,
        takeoverTitle=if(humanControl)"التحكم معاك" else "استلام من الإيجنت",
        takeoverHint=when {
            humanControl -> "استلمت المكالمة"
            !inCall -> "عند وجود مكالمة"
            !answered -> "متاح بعد الرد"
            !enabled -> "الإيجنت متوقف"
            else -> "نقل المكالمة إليك"
        }
    )
}
