package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class OverlayUiModelTest {
    @Test fun idleActiveAndStoppedHaveDistinctStates(){
        val active=overlayUiModel(true,true,false,false,false)
        assertTrue(active.running)
        assertTrue(active.status.contains("نشط"))
        assertFalse(active.canTakeover)
        assertFalse(active.canSwitchSpeaker)
        val stopped=overlayUiModel(true,false,false,false,false)
        assertFalse(stopped.running)
        assertTrue(stopped.status.contains("متوقف"))
        val disconnected=overlayUiModel(false,true,false,false,false)
        assertFalse(disconnected.running)
        assertTrue(disconnected.status.contains("خدمة الهاتف"))
    }

    @Test fun idleGoogleLiveDoesNotMakeRunningSimMonitorRed(){
        val monitor=overlayUiModel(true,true,false,false,false)
        assertTrue(monitor.running)
        assertTrue(monitor.status.contains("جاهز"))
        val notStarted=overlayUiModel(false,true,false,false,false)
        assertFalse(notStarted.running)
        assertTrue(notStarted.status.contains("لم يتأكد"))
    }

    @Test fun ringingCannotClaimOrSwitchAudioBeforeAnswer(){
        val ringing=overlayUiModel(true,true,true,false,false)
        assertTrue(ringing.status.contains("الرنين"))
        assertFalse(ringing.canTakeover)
        assertFalse(ringing.canSwitchSpeaker)
        assertTrue(ringing.takeoverHint.contains("بعد الرد"))
    }

    @Test fun connectedAiCallCanHandOverAndHumanCallCannotRepeatTakeover(){
        val ai=overlayUiModel(true,true,true,true,false,audioActive=true)
        assertTrue(ai.canTakeover)
        assertTrue(ai.canSwitchSpeaker)
        assertEquals("استلام من الإيجنت",ai.takeoverTitle)
        val human=overlayUiModel(true,true,true,true,true,audioActive=false)
        assertFalse(human.canTakeover)
        assertTrue(human.canSwitchSpeaker)
        assertTrue(human.status.contains("استلمت المكالمة"))
        assertEquals("التحكم معاك",human.takeoverTitle)
    }

    @Test fun disablingAgentDuringCallCannotOfferFakeHandoff(){
        val off=overlayUiModel(true,false,true,true,true)
        assertFalse(off.running)
        assertFalse(off.canTakeover)
        assertTrue(off.canSwitchSpeaker)
    }
    @Test fun answeredPhoneWithoutPcmMustNotClaimAiOrHumanTakeover(){
        val pending=overlayUiModel(true,true,true,true,false,audioActive=false)
        assertFalse(pending.canTakeover)
        assertTrue(pending.status.contains("تجهيز صوت"))
        assertTrue(pending.takeoverHint.contains("بعد اتصال صوت"))
        assertFalse(pending.status.contains("استلمت المكالمة"))
    }

    @Test fun actualSaasFailureIsVisibleWithoutInventingHumanControl(){
        val broken=overlayUiModel(true,true,true,true,false,false,
            "تعذر تشغيل صوت الإيجنت: Google Live رفض الجلسة")
        assertTrue(broken.status.contains("Google Live"))
        assertFalse(broken.canTakeover)
        assertFalse(broken.status.contains("استلمت المكالمة"))
        val ambiguous=overlayUiModel(true,true,true,false,false,false,
            "حالة المكالمة ملتبسة بين شريحتين")
        assertTrue(ambiguous.status.contains("شريحتين"))
        assertFalse(ambiguous.canTakeover)
    }

    @Test fun cellularAudioTraceShowsObservedStageNotFakeEndToEndSuccess() {
        val tracing=overlayUiModel(true,true,true,true,false,true,"",
            "Google أرسل الصوت للجسر؛ سماع المتصل غير مؤكد")
        assertTrue(tracing.status.contains("غير مؤكد"))
        assertTrue(tracing.canTakeover)
        val failure=overlayUiModel(true,true,true,true,false,true,
            "فشل الاتصال على الداتا","Google أرسل الصوت")
        assertTrue(failure.status.contains("فشل الاتصال"))
        assertFalse(failure.status.contains("أرسل الصوت"))
    }

    @Test fun stoppedOrRingingCallCannotShowStaleCellularAudioTrace() {
        val ringing=overlayUiModel(true,true,true,false,false,false,"","PCM جاهز")
        assertTrue(ringing.status.contains("الرنين"))
        val idle=overlayUiModel(true,true,false,false,false,false,"","PCM جاهز")
        assertTrue(idle.status.contains("جاهز"))
        assertFalse(idle.status.contains("PCM"))
    }

    @Test fun stoppedCellularDaemonDoesNotShowFalseGreenReady() {
        val cellularDown=overlayUiModel(
            serviceReady=true,enabled=true,inCall=false,answered=false,humanControl=false,
            daemonReady=false,daemonIssue="على الداتا فقط وميزة التشغيل بدون Wi-Fi (5555) غير مفعلة"
        )
        assertFalse(cellularDown.running)
        assertTrue(cellularDown.status.contains("5555"))
    }

}
