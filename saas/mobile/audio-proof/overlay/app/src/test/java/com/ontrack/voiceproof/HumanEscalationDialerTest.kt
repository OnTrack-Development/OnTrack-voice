package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class HumanEscalationDialerTest {
    @Test fun matchesExactSubscriptionIdFirst() {
        val idx = HumanEscalationDialer.selectPhoneAccountIndex(
            capableSubIds = listOf(1, 2),
            capableHandleIds = listOf("sub-1", "sub-2"),
            targetSubscriptionId = 2,
            activeSimCount = 2
        )
        assertEquals(1, idx)
    }

    @Test fun resolvesXiaomiMultiHandleSameSubscriptionWithoutCrashing() {
        val idx = HumanEscalationDialer.selectPhoneAccountIndex(
            capableSubIds = listOf(3, 3, 5),
            capableHandleIds = listOf("3:voice", "3:ims", "5:voice"),
            targetSubscriptionId = 3,
            activeSimCount = 2
        )
        assertEquals(0, idx)
    }

    @Test fun fallsBackToHandleIdWhenSubscriptionIdUnavailable() {
        val idx = HumanEscalationDialer.selectPhoneAccountIndex(
            capableSubIds = listOf(null, null),
            capableHandleIds = listOf("10:0", "14:1"),
            targetSubscriptionId = 14,
            activeSimCount = 2
        )
        assertEquals(1, idx)
    }

    @Test fun fallsBackToSingleCapableHandleOnSingleSimPhone() {
        val idx = HumanEscalationDialer.selectPhoneAccountIndex(
            capableSubIds = listOf(null),
            capableHandleIds = listOf("icc_xyz_9988"),
            targetSubscriptionId = 4,
            activeSimCount = 1
        )
        assertEquals(0, idx)
    }

    @Test fun refusesAmbiguousDualSimHandlesWhenNoSubOrHandleMatches() {
        val idx = HumanEscalationDialer.selectPhoneAccountIndex(
            capableSubIds = listOf(null, null),
            capableHandleIds = listOf("icc_a", "icc_b"),
            targetSubscriptionId = 7,
            activeSimCount = 2
        )
        assertNull(idx)
    }
}

