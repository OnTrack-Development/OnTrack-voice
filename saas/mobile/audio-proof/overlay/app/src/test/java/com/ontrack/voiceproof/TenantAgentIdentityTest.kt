package com.ontrack.voiceproof

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class TenantAgentIdentityTest {
    @Test fun scopedAgentRefresh() {
        assertTrue(TenantAgentIdentity.canRefreshAgent(1,6,0,1,6,0,7))
        assertFalse(TenantAgentIdentity.canRefreshAgent(1,6,0,2,6,0,7))
        assertFalse(TenantAgentIdentity.canRefreshAgent(1,6,0,1,8,0,7))
        assertFalse(TenantAgentIdentity.canRefreshAgent(1,6,0,1,6,1,7))
        assertFalse(TenantAgentIdentity.canRefreshAgent(1,6,0,1,6,0,0))
    }
}
