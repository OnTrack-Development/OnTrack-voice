package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class LiveInterruptionPolicyTest {
    @Test fun cutsBufferedAssistantSpeechOnRealCallerTranscription() {
        assertTrue(LiveInterruptionPolicy.shouldCutPreviousSpeech(3000,2450,0,"هو السعر كام؟"))
        assertFalse(LiveInterruptionPolicy.shouldCutPreviousSpeech(3000,500,0,"هو السعر كام؟"))
        assertFalse(LiveInterruptionPolicy.shouldCutPreviousSpeech(3000,2450,2900,"هو السعر كام؟"))
        assertFalse(LiveInterruptionPolicy.shouldCutPreviousSpeech(3000,2450,0,"آه"))
        assertFalse(LiveInterruptionPolicy.shouldCutPreviousSpeech(3000,0,0,"هو السعر كام؟"))
    }
}
