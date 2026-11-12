package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class FishTtsFailoverTest {
    @Test fun onlyExplicitAuthenticatedFishProviderErrorsAllowVoiceSwap(){
        assertEquals("credit",FishTtsFailover.reason(402,"premium_tts_credit_exhausted"))
        assertEquals("rate_limit",FishTtsFailover.reason(429,"premium_tts_rate_limited"))
        assertEquals("provider_unavailable",FishTtsFailover.reason(503,"premium_tts_provider_unavailable"))
        assertEquals("provider_unavailable",FishTtsFailover.reason(503,"premium_tts_network_error"))
        assertEquals("tts_failed",FishTtsFailover.reason(502,"premium_tts_empty_audio"))
        assertNull(FishTtsFailover.reason(403,"premium_voice_not_available"))
        assertNull(FishTtsFailover.reason(403,"voice_subscription_inactive"))
        assertNull(FishTtsFailover.reason(401,"device_not_authorized"))
        assertNull(FishTtsFailover.reason(402,"invalid_credit_claim"))
        assertNull(FishTtsFailover.reason(500,"internal_server_error"))
    }
    @Test fun failoverIsNeverExposedAsAnAuthenticationBypassOrSecret(){
        val reason=FishTtsFailover.label("credit")
        assertTrue(reason.contains("Google Live"))
        assertTrue(reason.contains("نفس المكالمة"))
        assertFalse(reason.contains("API Key"))
        assertEquals("fish_tts_fallback_credit",FishTtsFailoverException("credit").message)
    }
}
