package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class TenantPairingContractTest {
    @Test fun acceptsOnlyTwoSlotsAnd96BitCodes() {
        assertEquals(0, TenantPairingContract.slot(0))
        assertEquals(1, TenantPairingContract.slot(1))
        for (slot in listOf(-1,2,3)) assertThrows(IllegalArgumentException::class.java) { TenantPairingContract.slot(slot) }
        assertEquals("ABCD-EF01-2345-6789-ABCD-EF01",
            TenantPairingContract.code("abcd-ef01-2345-6789-abcd-ef01"))
        for (bad in listOf("", "0000", "a".repeat(23), "g".repeat(24), "a".repeat(25)))
            assertThrows(IllegalArgumentException::class.java) { TenantPairingContract.code(bad) }
    }
    @Test fun tokensNeverAcceptLegacyPilotBearer() {
        assertEquals("otdev_"+"a".repeat(64),TenantPairingContract.token("otdev_"+"a".repeat(64)))
        for (bad in listOf("legacyPilotBearer", "otdev_"+"a".repeat(63), "otdev_"+"Z".repeat(64)))
            assertThrows(IllegalArgumentException::class.java) { TenantPairingContract.token(bad) }
    }
    @Test fun qrIntentNeverControlsEndpointOrAutoPairs() {
        val good=TenantPairingContract.qr("ontrackvoice://pair?slot=1&code=abcd-ef01-2345-6789-abcd-ef01")
        assertEquals(1,good?.first)
        assertEquals("ABCD-EF01-2345-6789-ABCD-EF01",good?.second)
        for (bad in listOf("http://example.com/?code="+"a".repeat(24),
            "ontrackvoice://evil?slot=0&code="+"a".repeat(24),
            "ontrackvoice://pair?slot=2&code="+"a".repeat(24),
            "ontrackvoice://pair?slot=0&code="+"a".repeat(24)+"&next=evil"))
            assertNull(TenantPairingContract.qr(bad))
    }
}
