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
    @Test fun subscriptionChangeFailsClosedWithoutLeakingCarrierIdentifiers() {
        assertNull(TenantSimHardware.subscriptionProblem(17,17))
        assertNotNull(TenantSimHardware.subscriptionProblem(17,18))
        assertNotNull(TenantSimHardware.subscriptionProblem(17,-1))
        assertNotNull(TenantSimHardware.subscriptionProblem(null,17))
        assertNotNull(TenantSimHardware.subscriptionProblem(-1,17))
        val changed=TenantSimHardware.subscriptionProblem(17,18)!!
        assertTrue(changed.contains("أعد ربط"))
        assertFalse(changed.contains("17"))
        assertFalse(changed.contains("18"))
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
    @Test fun staleAgentRevisionCannotOverwriteNewerSimAssignment() {
        assertTrue(TenantPairingContract.agentRevisionValid(1,2,8))
        assertTrue(TenantPairingContract.agentRevisionValid(2,2,8))
        assertFalse(TenantPairingContract.agentRevisionValid(3,2,8))
        assertFalse(TenantPairingContract.agentRevisionValid(0,0,8))
        assertFalse(TenantPairingContract.agentRevisionValid(1,2,0))
    }
    @Test fun serverRefreshShowsReassignedAgentPerSim() {
        val before=TenantPairingContract.statusLine(0,true,"شركة ألف","المتجر","إيجنت قديم")
        val after=TenantPairingContract.statusLine(0,true,"شركة ألف","المتجر","إيجنت جديد")
        assertTrue(before.contains("إيجنت قديم"))
        assertTrue(after.contains("إيجنت جديد"))
        assertFalse(after.contains("إيجنت قديم"))
    }
    @Test fun callKeepsOriginalSimAgentRevisionAcrossOfflineReassignment() {
        assertEquals(1,TenantPairingContract.pinnedCallRevision("call-a",1,2))
        assertEquals(2,TenantPairingContract.pinnedCallRevision(null,1,2))
        assertEquals(4,TenantPairingContract.pinnedCallRevision("call-b",4,0))
        assertThrows(IllegalArgumentException::class.java) {
            TenantPairingContract.pinnedCallRevision("call-c",0,5)
        }
        assertThrows(IllegalArgumentException::class.java) {
            TenantPairingContract.pinnedCallRevision(null,1,0)
        }
    }
    @Test fun agentRevisionCanBePinnedBeforeOfflineOutboxFlush() {
        val observedAtStart=1
        val serverAfterReassign=2
        assertTrue(TenantPairingContract.agentRevisionValid(observedAtStart,serverAfterReassign,8))
        // A call retains the revision captured at first telephony observation.
        assertEquals(1,observedAtStart)
        assertFalse(TenantPairingContract.agentRevisionValid(serverAfterReassign,observedAtStart,1))
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
    @Test fun simCallLifecycleRecordsRealTransitionsWithoutNumbersOrAudio() {
        val idA="01234567-89ab-4cde-8f01-23456789abcd"
        val idB="12345678-9abc-4def-8abc-123456789abc"
        val whenUtc="2026-11-01T19:00:00Z"
        val ringing=TenantCallEventContract.observe(
            TenantCallEventContract.State(),1,1000L,idA,whenUtc)
        assertEquals("ringing",ringing.event?.phase)
        assertEquals(idA,ringing.state.callId)
        assertEquals(0,ringing.event?.durationSeconds)
        val duplicate=TenantCallEventContract.observe(ringing.state,1,1100L,idB,whenUtc)
        assertNull(duplicate.event)
        assertEquals(idA,duplicate.state.callId)
        val active=TenantCallEventContract.observe(ringing.state,2,3000L,idB,whenUtc)
        assertEquals("active",active.event?.phase)
        assertEquals(idA,active.event?.callId)
        val ended=TenantCallEventContract.observe(active.state,0,6300L,idB,whenUtc)
        assertEquals("ended",ended.event?.phase)
        assertEquals(3,ended.event?.durationSeconds)
        assertNull(ended.state.callId)
        assertNull(TenantCallEventContract.observe(ended.state,0,7000L,idB,whenUtc).event)
        val missed=TenantCallEventContract.observe(ringing.state,0,5000L,idB,whenUtc)
        assertEquals(0,missed.event?.durationSeconds)
        assertEquals("ended",missed.event?.phase)
        val second=TenantCallEventContract.observe(
            TenantCallEventContract.State(),2,1000L,idB,whenUtc)
        assertEquals(idB,second.event?.callId)
        assertEquals("active",second.event?.phase)
        assertThrows(IllegalArgumentException::class.java) {
            TenantCallEventContract.observe(second.state,7,2000L,idB,whenUtc)
        }
        assertFalse(ended.event.toString().contains("phone"))
        assertFalse(ended.event.toString().contains("audio"))
    }

}
