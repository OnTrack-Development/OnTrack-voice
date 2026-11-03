package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class OutboundFirstTurnTest {
    @Test fun outgoingDoesNotGreetOnOffhook() {
        assertFalse(OutboundFirstTurn.sendInitialGreeting(outgoing=true))
        assertTrue(OutboundFirstTurn.sendInitialGreeting(outgoing=false))
    }
    @Test fun firstActualSpeechIsNeeded() {
        for(noise in listOf("", " ", "ringing", "[ringback]", "(silence)", "رنين", "صمت", "tone", "A")) {
            assertFalse("must ignore: $noise",OutboundFirstTurn.remoteSpeechTranscribed(noise))
        }
        for(speech in listOf("ألو", "السلام عليكم", "نعم", "Hello", "Yes", "مين معايا؟")) {
            assertTrue("must accept: $speech",OutboundFirstTurn.remoteSpeechTranscribed(speech))
        }
    }
}
