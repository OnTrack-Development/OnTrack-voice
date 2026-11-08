package com.ontrack.voiceproof

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class TenantEngineRestorePolicyTest {
    @Test fun restoresPreviousExplicitEnableOnly() {
        assertTrue(TenantEngineRestorePolicy.shouldRestore(true,true,false))
        assertFalse(TenantEngineRestorePolicy.shouldRestore(true,false,false))
        assertFalse(TenantEngineRestorePolicy.shouldRestore(false,true,false))
        assertFalse(TenantEngineRestorePolicy.shouldRestore(true,true,true))
    }

    @Test fun publishedAutoAnswerStartsOnlyBeforeAnyLocalUserChoice() {
        assertTrue(TenantEngineRestorePolicy.firstPairPublishedAutoStart(
            true,false,true,false,true,false))
        // An explicitly saved Stop is never overridden by server policy.
        assertFalse(TenantEngineRestorePolicy.firstPairPublishedAutoStart(
            true,true,true,false,true,false))
        assertFalse(TenantEngineRestorePolicy.firstPairPublishedAutoStart(
            true,false,false,false,true,false))
        assertFalse(TenantEngineRestorePolicy.firstPairPublishedAutoStart(
            false,false,true,false,true,false))
        assertFalse(TenantEngineRestorePolicy.firstPairPublishedAutoStart(
            true,false,true,true,true,false))
        assertFalse(TenantEngineRestorePolicy.firstPairPublishedAutoStart(
            true,false,true,false,false,false))
        assertFalse(TenantEngineRestorePolicy.firstPairPublishedAutoStart(
            true,false,true,false,true,true))
    }
}
