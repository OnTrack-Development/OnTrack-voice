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

    @Test fun vadAndTokenLimitsPreventEndOfWordTruncation() {
        assertEquals(1024, LiveVadConfig.DEFAULT_MAX_OUTPUT_TOKENS)
        assertEquals(120, LiveVadConfig.PREFIX_PADDING_MS)
        assertEquals(400, LiveVadConfig.SILENCE_DURATION_MS)
        assertEquals(1024, LiveVadConfig.clampMaxOutputTokens(1024))
        assertEquals(512, LiveVadConfig.clampMaxOutputTokens(10))
        assertEquals(2048, LiveVadConfig.clampMaxOutputTokens(4096))
    }
}
