package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class TenantCallRoutingTest {
    @Test fun singleActivePairedSIMMayUseLegacyQButDualSIMCannot(){
        assertEquals(0,TenantCallRouting.nativeSingleSimSlot(listOf(0),setOf(0)))
        assertEquals(1,TenantCallRouting.nativeSingleSimSlot(listOf(1),setOf(1)))
        assertNull(TenantCallRouting.nativeSingleSimSlot(listOf(0,1),setOf(0)))
        assertNull(TenantCallRouting.nativeSingleSimSlot(listOf(0,1),setOf(0,1)))
        assertNull(TenantCallRouting.nativeSingleSimSlot(listOf(1),setOf(0)))
        assertNull(TenantCallRouting.nativeSingleSimSlot(emptyList(),setOf(0)))
    }

    @Test fun incomingAutoAnswersAtConfiguredDelayWithoutWaitingForGemini() {
        assertFalse(TenantCallRouting.autoAnswerDue(true,true,4_999,5,false,false))
        assertTrue(TenantCallRouting.autoAnswerDue(true,true,5_000,5,false,false))
        assertTrue(TenantCallRouting.autoAnswerDue(true,true,0,0,false,false))
        assertFalse(TenantCallRouting.autoAnswerDue(true,false,10_000,5,false,false))
        assertFalse(TenantCallRouting.autoAnswerDue(true,true,10_000,5,true,false))
        assertFalse(TenantCallRouting.autoAnswerDue(true,true,10_000,5,false,true))
        assertFalse(TenantCallRouting.autoAnswerDue(false,true,10_000,5,false,false))
    }

    @Test fun manuallyAnsweredIncomingStartsLiveEvenIfAutoAnswerIsDisabled() {
        // A manual answer is not an explicit human takeover of an AI session.
        // The old 0.3.17 used ringAttempted=true to suppress this route.
        assertTrue(TenantCallRouting.attachAfterOffhook(true,true,false,false))
        assertFalse(TenantCallRouting.attachAfterOffhook(false,true,false,false))
        assertFalse(TenantCallRouting.attachAfterOffhook(true,false,false,false))
    }

    @Test fun explicitHumanTakeoverAndDuplicateSessionAreAlwaysBlocked() {
        assertFalse(TenantCallRouting.attachAfterOffhook(true,true,true,false))
        assertFalse(TenantCallRouting.attachAfterOffhook(true,true,false,true))
    }

    @Test fun outboundOffhookStartsLiveOnce() {
        assertTrue(TenantCallRouting.attachAfterOffhook(true,true,false,false))
        assertFalse(TenantCallRouting.attachAfterOffhook(true,true,false,true))
    }
}
