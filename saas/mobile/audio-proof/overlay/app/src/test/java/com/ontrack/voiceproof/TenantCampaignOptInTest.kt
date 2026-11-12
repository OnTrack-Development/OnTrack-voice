package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class TenantCampaignOptInTest {
    @Test fun defaultsEnabledWhenPairedPermittedAndAgentActive() {
        assertTrue(
            TenantCampaignDialer.shouldDefaultOptIn(
                paired = true,
                hasCallPhone = true,
                hasReadPhone = true,
                agentEnabled = true,
                userExplicitlyDisabled = false,
            )
        )
    }

    @Test fun respectsExplicitUserDisableAndMissingPermissions() {
        assertFalse(
            TenantCampaignDialer.shouldDefaultOptIn(
                paired = true,
                hasCallPhone = true,
                hasReadPhone = true,
                agentEnabled = true,
                userExplicitlyDisabled = true,
            )
        )
        assertFalse(
            TenantCampaignDialer.shouldDefaultOptIn(
                paired = true,
                hasCallPhone = false,
                hasReadPhone = true,
                agentEnabled = true,
                userExplicitlyDisabled = false,
            )
        )
        assertFalse(
            TenantCampaignDialer.shouldDefaultOptIn(
                paired = false,
                hasCallPhone = true,
                hasReadPhone = true,
                agentEnabled = true,
                userExplicitlyDisabled = false,
            )
        )
        assertFalse(
            TenantCampaignDialer.shouldDefaultOptIn(
                paired = true,
                hasCallPhone = true,
                hasReadPhone = true,
                agentEnabled = false,
                userExplicitlyDisabled = false,
            )
        )
    }
}

