package com.ontrack.voiceproof

/** Restore only a previously enabled and still-paired SaaS SIM. */
internal object TenantEngineRestorePolicy {
    fun shouldRestore(paired:Boolean, savedEnabled:Boolean, pairingInProgress:Boolean):Boolean =
        paired && savedEnabled && !pairingInProgress

    /** The owner already enabled auto-answer in the PUBLISHED web policy and
     * intentionally paired this SIM. Allow the first ever monitor start only
     * when the user has not yet made a local Start/Stop choice. A saved false
     * is an explicit opt-out and must never be overridden remotely. */
    fun firstPairPublishedAutoStart(
        paired:Boolean,manualChoiceSaved:Boolean,publishedAutoAnswerReady:Boolean,
        pairingInProgress:Boolean,readPhoneGranted:Boolean,inCall:Boolean
    ):Boolean =
        paired && !manualChoiceSaved && publishedAutoAnswerReady &&
            !pairingInProgress && readPhoneGranted && !inCall
}
