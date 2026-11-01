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
    @Test fun oneTimeCodesAreLockedToTheSelectedPhysicalSlot() {
        assertEquals("0BCD-EF01-2345-6789-ABCD-EF01",
            TenantPairingContract.codeForSlot("0bcd-ef01-2345-6789-abcd-ef01",0))
        assertEquals("1BCD-EF01-2345-6789-ABCD-EF01",
            TenantPairingContract.codeForSlot("1bcd-ef01-2345-6789-abcd-ef01",1))
        assertThrows(IllegalArgumentException::class.java) {
            TenantPairingContract.codeForSlot("0bcd-ef01-2345-6789-abcd-ef01",1)
        }
        assertThrows(IllegalArgumentException::class.java) {
            TenantPairingContract.codeForSlot("1bcd-ef01-2345-6789-abcd-ef01",0)
        }
    }
    @Test fun tokensNeverAcceptLegacyPilotBearer() {
        assertEquals("otdev_"+"a".repeat(64),TenantPairingContract.token("otdev_"+"a".repeat(64)))
        for (bad in listOf("legacyPilotBearer", "otdev_"+"a".repeat(63), "otdev_"+"Z".repeat(64)))
            assertThrows(IllegalArgumentException::class.java) { TenantPairingContract.token(bad) }
    }
    @Test fun physicalSimMustBeReadyBeforePairingOrHeartbeat() {
        assertNull(TenantSimHardware.problemForState(android.telephony.TelephonyManager.SIM_STATE_READY))
        assertTrue(TenantSimHardware.problemForState(android.telephony.TelephonyManager.SIM_STATE_ABSENT)!!.contains("لا توجد"))
        assertNotNull(TenantSimHardware.problemForState(android.telephony.TelephonyManager.SIM_STATE_PIN_REQUIRED))
        assertNotNull(TenantSimHardware.problemForState(android.telephony.TelephonyManager.SIM_STATE_UNKNOWN))
    }
    @Test fun pilotTelephonyDeniedWhilePairingOrAnyTenantSlotActive() {
        assertTrue(TenantPairingContract.pilotAllowed(false,false))
        assertFalse(TenantPairingContract.pilotAllowed(true,false))
        assertFalse(TenantPairingContract.pilotAllowed(false,true))
        assertFalse(TenantPairingContract.pilotAllowed(true,true))
    }
    @Test fun statusDistinguishesTwoCompaniesAndServerRevocation() {
        assertEquals("SIM 1 — غير مربوطة", TenantPairingContract.statusLine(0,false))
        val first=TenantPairingContract.statusLine(0,true,"شركة ألف","المتجر","مبيعات")
        val second=TenantPairingContract.statusLine(1,true,"شركة باء","العيادة","حجوزات")
        assertTrue(first.contains("شركة ألف"))
        assertFalse(first.contains("شركة باء"))
        assertTrue(second.contains("شركة باء"))
        assertFalse(second.contains("شركة ألف"))
        assertEquals("SIM 2 — تم إلغاء الاعتماد من السيرفر",
            TenantPairingContract.statusLine(1,true,error="تم إلغاء الاعتماد من السيرفر"))
        assertEquals("SIM 1 — تعذر التحقق؛ الربط غير مؤكد",
            TenantPairingContract.statusLine(0,true,error="تعذر التحقق؛ الربط غير مؤكد"))
    }
    @Test fun qrIntentNeverControlsEndpointOrAutoPairs() {
        val good=TenantPairingContract.qr("ontrackvoice://pair?slot=1&code=1bcd-ef01-2345-6789-abcd-ef01")
        assertEquals(1,good?.first)
        assertEquals("1BCD-EF01-2345-6789-ABCD-EF01",good?.second)
        assertNull(TenantPairingContract.qr("ontrackvoice://pair?slot=0&code=1bcd-ef01-2345-6789-abcd-ef01"))
        for (bad in listOf("http://example.com/?code="+"a".repeat(24),
            "ontrackvoice://evil?slot=0&code="+"a".repeat(24),
            "ontrackvoice://pair?slot=2&code="+"a".repeat(24),
            "ontrackvoice://pair?slot=0&code="+"a".repeat(24)+"&next=evil"))
            assertNull(TenantPairingContract.qr(bad))
    }
}
