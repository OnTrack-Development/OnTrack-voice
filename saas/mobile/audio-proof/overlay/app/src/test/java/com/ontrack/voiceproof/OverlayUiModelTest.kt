package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class OverlayUiModelTest {
    @Test fun enabledMonitorGreenEvenWithoutIdleGoogleSocket() {
        val on=overlayUiModel(false,true,false,false,false)
        assertEquals(OverlayIndicator.GREEN,on.indicator)
        assertTrue(on.running)
        assertTrue(on.status.contains("ينتظر المكالمات"))
        assertFalse(on.canTakeover)
        assertFalse(on.canSwitchSpeaker)
    }
    @Test fun disabledMonitorAlwaysRed() {
        for(connected in listOf(false,true)){
            val off=overlayUiModel(connected,false,false,false,false,reconnecting=true)
            assertEquals(OverlayIndicator.RED,off.indicator)
            assertFalse(off.running)
            assertTrue(off.status.contains("متوقف"))
        }
    }
    @Test fun reconnectingMonitorGrayEvenWithPriorConnection() {
        val retry=overlayUiModel(true,true,true,true,false,reconnecting=true)
        assertEquals(OverlayIndicator.GRAY,retry.indicator)
        assertFalse(retry.running)
        assertTrue(retry.status.contains("إعادة الاتصال"))
        assertTrue(retry.canTakeover)
    }
    @Test fun ringingCannotOfferTakeoverBeforeAnswer() {
        val ringing=overlayUiModel(false,true,true,false,false)
        assertEquals(OverlayIndicator.GREEN,ringing.indicator)
        assertTrue(ringing.status.contains("الرنين"))
        assertFalse(ringing.canTakeover)
        assertFalse(ringing.canSwitchSpeaker)
    }
    @Test fun liveCallOffersHumanTakeoverButNotAfterTransfer() {
        val live=overlayUiModel(true,true,true,true,false)
        assertEquals(OverlayIndicator.GREEN,live.indicator)
        assertTrue(live.canTakeover)
        assertTrue(live.canSwitchSpeaker)
        assertEquals("استلام من الإيجنت",live.takeoverTitle)
        val human=overlayUiModel(true,true,true,true,true)
        assertFalse(human.canTakeover)
        assertTrue(human.status.contains("استلمت المكالمة"))
    }
    @Test fun disabledAgentDuringHumanCallDoesNotOfferFalseTransfer() {
        val off=overlayUiModel(true,false,true,true,true)
        assertEquals(OverlayIndicator.RED,off.indicator)
        assertFalse(off.canTakeover)
        assertTrue(off.canSwitchSpeaker)
    }
}
