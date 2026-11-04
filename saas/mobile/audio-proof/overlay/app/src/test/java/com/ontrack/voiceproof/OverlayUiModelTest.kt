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
        val ai=overlayUiModel(true,true,true,true,false)
        assertTrue(ai.canTakeover)
        assertTrue(ai.canSwitchSpeaker)
        assertEquals("استلام من الإيجنت",ai.takeoverTitle)
        val human=overlayUiModel(true,true,true,true,true)
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
}
