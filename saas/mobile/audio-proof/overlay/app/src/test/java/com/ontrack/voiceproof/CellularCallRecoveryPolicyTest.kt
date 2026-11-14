package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class CellularCallRecoveryPolicyTest {
    @Test fun lteMayRetryBeforeAndroidValidationWhenDefaultRouteHasInternet() {
        assertTrue(CellularCallRecoveryPolicy.mayRetryLive(true,true,false,true))
        assertTrue(CellularCallRecoveryPolicy.mayRetryLive(true,true,true,false))
        assertFalse(CellularCallRecoveryPolicy.mayRetryLive(false,true,true,true))
        assertFalse(CellularCallRecoveryPolicy.mayRetryLive(true,false,true,true))
        assertFalse(CellularCallRecoveryPolicy.mayRetryLive(true,true,false,false))
    }

    @Test fun umtsHspaAndEdgeAreValidCellularRetryCandidatesOnlyWhenInternetPresent() {
        // ConnectivityManager sees every radio generation as TRANSPORT_CELLULAR.
        // LTE-only labels must never decide retry eligibility.
        assertTrue(CellularCallRecoveryPolicy.mayRetryLive(true,true,false,true))
        assertFalse(CellularCallRecoveryPolicy.mayRetryLive(true,false,false,true))
        assertFalse(CellularCallRecoveryPolicy.mayRetryLive(false,true,false,true))
    }

    @Test fun on3gDataReturnRearmNeverOverridesHumanTakeoverOrExistingPcm(){
        fun allowed(offhook:Boolean=true,enabled:Boolean=true,human:Boolean=false,
                    active:Boolean=false,attempts:Int=6)=
            CellularCallRecoveryPolicy.canRearmAfterNetworkReturn(
                offhook,enabled,human,active,attempts)
        assertTrue(allowed())
        assertFalse(allowed(human=true))
        assertFalse(allowed(active=true))
        assertFalse(allowed(offhook=false))
        assertFalse(allowed(enabled=false))
        assertFalse(allowed(attempts=0))
    }

    @Test fun autoAnswerRetriesOnlyWhileRingIsLiveAndCommandIsNotAcknowledged() {
        fun retry(ringing:Boolean=true,ack:Boolean=false,attempts:Int=1,now:Long=3100L,next:Long=3000L)=
            CellularCallRecoveryPolicy.autoAnswerRetryAllowed(ringing,ack,attempts,now,next)
        assertTrue(retry(attempts=0))
        assertTrue(retry(attempts=2))
        assertFalse(retry(attempts=3))
        assertFalse(retry(ringing=false))
        assertFalse(retry(ack=true))
        assertFalse(retry(now=2999L))
    }

    @Test fun cellularRecoveryIsFiniteAndWaitsBetweenAttempts() {
        assertEquals(12,CellularCallRecoveryPolicy.MAX_VOICE_START_ATTEMPTS)
        assertEquals(3,CellularCallRecoveryPolicy.MAX_AUTO_ANSWER_ATTEMPTS)
        assertEquals(1000L,CellularCallRecoveryPolicy.retryDelayMs(1))
        assertEquals(1000L,CellularCallRecoveryPolicy.retryDelayMs(6))
        assertEquals(900L,CellularCallRecoveryPolicy.answerRetryDelayMs(1))
        assertEquals(2700L,CellularCallRecoveryPolicy.answerRetryDelayMs(3))
    }
}
