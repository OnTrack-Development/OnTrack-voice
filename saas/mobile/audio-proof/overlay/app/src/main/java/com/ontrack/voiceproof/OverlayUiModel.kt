package com.ontrack.voiceproof

/** An idle Live websocket is normal: a paired, enabled SIM monitor is still ON. */
internal enum class OverlayIndicator { GREEN, RED, GRAY }

internal data class OverlayUiModel(
    val running: Boolean,
    val indicator: OverlayIndicator,
    val status: String,
    val announcement: String,
    val canTakeover: Boolean,
    val canSwitchSpeaker: Boolean,
    val takeoverTitle: String,
    val takeoverHint: String
)

internal fun overlayUiModel(
    connected: Boolean, enabled: Boolean, inCall: Boolean,
    answered: Boolean, humanControl: Boolean, reconnecting: Boolean=false
): OverlayUiModel {
    // enabled describes the running SIM monitor, not the call-only Google websocket.
    // Reconnect is explicit, rather than treating a quiet/idle line as offline.
    val indicator=when {
        !enabled -> OverlayIndicator.RED
        reconnecting -> OverlayIndicator.GRAY
        else -> OverlayIndicator.GREEN
    }
    val running=indicator==OverlayIndicator.GREEN
    val canTakeover=inCall && answered && !humanControl && enabled
    val canSwitchSpeaker=inCall && answered
    val status=when {
        !enabled -> "الإيجنت متوقف"
        reconnecting -> "جاري إعادة الاتصال"
        inCall && humanControl -> "أنت استلمت المكالمة من الإيجنت"
        inCall && !answered -> "المكالمة قيد الرنين أو الاتصال"
        inCall && enabled && connected -> "المكالمة متصلة — الإيجنت يتحدث"
        inCall && enabled -> "المكالمة متصلة — تحضير جلسة الصوت"
        enabled -> "الإيجنت يعمل وينتظر المكالمات"
        else -> "الإيجنت متوقف"
    }
    return OverlayUiModel(
        running=running,indicator=indicator,status=status,
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
