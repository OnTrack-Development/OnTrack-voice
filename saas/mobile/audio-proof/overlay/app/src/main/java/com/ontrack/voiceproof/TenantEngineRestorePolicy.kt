package com.ontrack.voiceproof

/** Restore only a previously enabled and still-paired SaaS SIM. */
internal object TenantEngineRestorePolicy {
    fun shouldRestore(paired:Boolean, savedEnabled:Boolean, pairingInProgress:Boolean):Boolean =
        paired && savedEnabled && !pairingInProgress
}
