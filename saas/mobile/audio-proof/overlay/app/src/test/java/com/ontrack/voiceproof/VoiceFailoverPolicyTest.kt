package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class VoiceFailoverPolicyTest {
    @Test fun onlyRecoverableProviderErrorsRotate() {
        assertEquals("429",VoiceFailoverPolicy.failureCode(429))
        assertEquals("503",VoiceFailoverPolicy.failureCode(503))
        assertEquals("429",VoiceFailoverPolicy.failureCode(null,"RESOURCE_EXHAUSTED"))
        assertEquals("503",VoiceFailoverPolicy.failureCode(null,"UNAVAILABLE"))
        assertEquals("timeout",VoiceFailoverPolicy.failureCode(null,null,"SocketTimeoutException timeout"))
        assertNull(VoiceFailoverPolicy.failureCode(401))
        assertNull(VoiceFailoverPolicy.failureCode(403))
        assertNull(VoiceFailoverPolicy.failureCode(500))
    }
    @Test fun restoreContextIsBoundedAndClearlyUntrusted() {
        val source="المتصل: السؤال الأول\nالإيجنت: الرد الأول\n"
        val result=VoiceFailoverPolicy.restoredContext(source)
        assertTrue(result.contains("السؤال الأول"))
        assertTrue(result.contains("غير موثوقة"))
        assertTrue(result.contains("لا تبدأ بتحية"))
        val long="ا".repeat(15000)
        assertTrue(VoiceFailoverPolicy.restoredContext(long).length<4000)
    }
    @Test fun missingTranscriptDoesNotFabricatePreviousAnswer() {
        val result=VoiceFailoverPolicy.restoredContext("")
        assertTrue(result.contains("لا تفترض"))
    }
}
