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
}
