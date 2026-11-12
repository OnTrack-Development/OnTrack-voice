package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test
import java.util.concurrent.atomic.AtomicInteger

class LiveBargeInAndSessionMutexTest {
    @Test fun generationControllerDropsStalePacketsAfterBargeIn() {
        val controller = BargeInGenerationController()
        val g0 = controller.current()
        assertTrue(controller.shouldPlay(g0))

        val g1 = controller.bump()
        assertEquals(g0 + 1, g1)
        assertFalse(controller.isCurrent(g0))
        assertFalse(controller.shouldPlay(g0))
        assertTrue(controller.isCurrent(g1))
        assertTrue(controller.shouldPlay(g1))
    }

    @Test fun activeCallSessionMutexStopsPreviousOwnerAndIgnoresStaleRelease() {
        val stops = AtomicInteger(0)
        var t1 = 0L
        var t2 = 0L

        t1 = ActiveCallSessionMutex.acquire {
            stops.incrementAndGet()
            ActiveCallSessionMutex.release(t1)
        }
        assertTrue(ActiveCallSessionMutex.isActive(t1))

        t2 = ActiveCallSessionMutex.acquire {
            stops.incrementAndGet()
            ActiveCallSessionMutex.release(t2)
        }
        assertEquals(1, stops.get())
        assertFalse(ActiveCallSessionMutex.isActive(t1))
        assertTrue(ActiveCallSessionMutex.isActive(t2))

        // Out-of-order stale release from t1 must never disarm active session t2.
        ActiveCallSessionMutex.release(t1)
        assertTrue(ActiveCallSessionMutex.isActive(t2))

        ActiveCallSessionMutex.release(t2)
        assertFalse(ActiveCallSessionMutex.isActive(t2))
    }

    @Test fun callMemorySwitchesToFemaleAndLocksIrreversibly() {
        val tracker = CallMemoryTracker(maxTurns = 6)
        assertEquals("unknown", tracker.gender)
        assertFalse(tracker.genderLocked)

        tracker.observeAssistant("مرحبا يا حلو، كيف الخدمة اليوم؟")
        tracker.observeCaller("أهلاً")
        tracker.observeCaller("أنا عاوز")
        assertEquals("male", tracker.gender)
        assertFalse(tracker.genderLocked)

        // Split transcription chunks announcing feminine speaker lock female mode permanently.
        tracker.observeCaller("لأ أنا")
        tracker.observeCaller("بنت ومبسوطة جداً")
        assertEquals("female", tracker.gender)
        assertTrue(tracker.genderLocked)

        // Later noisy masculine tokens must NEVER revert locked female state.
        tracker.observeCaller("أحمد")
        tracker.observeCaller("مبسوط")
        assertEquals("female", tracker.gender)
        assertTrue(tracker.genderLocked)

        tracker.observeCaller("تقييمي ليكم 9 من عشرة")
        assertEquals("9/10", tracker.rating)

        val summary = tracker.buildReconnectSummary()
        assertTrue(summary.contains("مقفولة على صيغة المؤنث"))
        assertTrue(summary.contains("9/10"))

        val failoverPrompt = VoiceFailoverPolicy.restoredContext("المتصل: شكراً", summary)
        assertTrue(failoverPrompt.contains("صيغة المؤنث"))
        assertTrue(failoverPrompt.length < 4000)
    }

    @Test fun callMemoryKeepsBoundedSixTurnsSlidingWindow() {
        val tracker = CallMemoryTracker(maxTurns = 6)
        for (i in 1..10) {
            tracker.observeCaller("سؤال رقم $i")
            tracker.observeAssistant("جواب رقم $i")
        }
        val turns = tracker.recentTurnsSnapshot()
        assertEquals(6, turns.size)
        assertFalse(turns.any { it.contains("رقم 1\n") || it.endsWith("رقم 1") })
        assertTrue(turns.last().contains("رقم 10"))
    }
}

