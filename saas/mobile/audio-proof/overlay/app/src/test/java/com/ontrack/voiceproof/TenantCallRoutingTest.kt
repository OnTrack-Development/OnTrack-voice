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
    @Test fun temporaryCellularInternetLossRetriesOnlyAfterValidatedDataReturns() {
        fun due(net:Boolean, now:Long, attempts:Int=1, next:Long=6000L)=
            TenantCallRouting.voiceRetryDue(
                true,true,false,false,true,attempts,3,net,now,next)
        assertFalse(due(false,7000)) // no cellular internet: do not burn tokens
        assertFalse(due(true,5999))
        assertTrue(due(true,6000))
        assertTrue(due(true,20000,2))
        assertFalse(due(true,20000,3)) // bounded, no infinite call reconnects
    }

    @Test fun reconnectNeverBreaksUserTakeoverOrAnsweredSimOwnership() {
        fun due(offhook:Boolean=true,enabled:Boolean=true,human:Boolean=false,
                pcm:Boolean=false,attempted:Boolean=true,attempts:Int=1)=
            TenantCallRouting.voiceRetryDue(
                offhook,enabled,human,pcm,attempted,attempts,3,true,8000L,5000L)
        assertFalse(due(offhook=false))
        assertFalse(due(enabled=false))
        assertFalse(due(human=true))
        assertFalse(due(pcm=true))
        assertFalse(due(attempted=false))
        assertFalse(due(attempts=0))
        assertTrue(due())
    }

}
