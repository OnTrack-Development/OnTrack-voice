package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class ManagerCallbackSchedulerTest {
    @Test fun countsDownTenSecondsAndConsumesOnceDue() {
        val scheduler = ManagerCallbackScheduler()
        scheduler.schedule(slot = 1, phone = "+201012345678", name = "أحمد", callId = "c-1", nowElapsedMs = 1_000L)

        assertTrue(scheduler.active)
        assertEquals(1, scheduler.pendingSlot)
        assertEquals("+201****678", scheduler.maskedPhone())
        assertEquals(10, scheduler.remainingSeconds(1_000L))
        assertEquals(5, scheduler.remainingSeconds(6_000L))
        assertFalse(scheduler.isDue(10_999L))
        assertNull(scheduler.consumeDue(10_999L))

        assertTrue(scheduler.isDue(11_000L))
        val due = scheduler.consumeDue(11_000L)
        assertNotNull(due)
        assertEquals(1, due!!.slot)
        assertEquals("+201012345678", due.phone)
        assertFalse(scheduler.active)
        assertEquals(0, scheduler.remainingSeconds(11_000L))
    }

    @Test fun cancelClearsScheduledCallbackWhenNewCallStarts() {
        val scheduler = ManagerCallbackScheduler()
        scheduler.schedule(slot = 0, phone = "+201099887766", nowElapsedMs = 2_000L)
        assertTrue(scheduler.active)
        scheduler.cancel()
        assertFalse(scheduler.active)
        assertNull(scheduler.consumeDue(20_000L))
    }
}

