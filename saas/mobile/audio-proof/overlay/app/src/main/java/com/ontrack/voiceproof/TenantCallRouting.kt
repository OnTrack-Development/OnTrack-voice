package com.ontrack.voiceproof

/**
 * Native phone-call decision rules shared by incoming, manually answered
 * incoming and outgoing SIM calls. An enabled monitor is not an answered call.
 */
internal object TenantCallRouting {
    // RCA 0.3.18/0.3.19 (2026-11-04): legacy shell 'Q' reliably
    // reported CALL 0/1/2 on the owner's previous Pilot; newer per-slot
    // Telephony reads can fail or be ambiguous on a dual-VoWiFi device.
    // A global shell call-state has no SIM identity: only a single currently
    // active, owner-paired subscription may safely use the legacy Q bridge.
    fun nativeSingleSimSlot(activeSlots:List<Int>,pairedSlots:Set<Int>):Int? =
        activeSlots.filter{it in 0..1}.distinct().singleOrNull()
            ?.takeIf{it in pairedSlots}

    /**
     * 2026-11-04 LTE regression: a carrier OFFHOOK may survive a temporary
     * loss of the phone's DEFAULT internet network during IMS/VoLTE setup.
     * When Gemini auth/WebSocket/PCM startup fails, the older monitor latched
     * attempted=true for the whole call and never retried even after data
     * returned. Limit retries, preserve the same bound SIM, and never retry a
     * deliberate human takeover or an already live PCM session.
     */
    fun voiceRetryDue(
        offhook:Boolean, enabled:Boolean, humanControl:Boolean, pcmActive:Boolean,
        initialAttempted:Boolean, attempts:Int, maxAttempts:Int, internetValidated:Boolean,
        nowMs:Long, nextRetryAtMs:Long
    ):Boolean =
        offhook && enabled && !humanControl && !pcmActive && initialAttempted &&
        attempts in 1 until maxAttempts &&
        internetValidated && nowMs>=nextRetryAtMs

    fun autoAnswerDue(
        ringing:Boolean, autoAnswerEnabled:Boolean, elapsedMs:Long,
        delaySeconds:Int, answerRequested:Boolean, humanControl:Boolean
    ):Boolean {
        if(!ringing || !autoAnswerEnabled || answerRequested || humanControl) return false
        return elapsedMs >= delaySeconds.coerceIn(0,30)*1000L
    }

    // OFFHOOK is a carrier phone state, not a Gemini network state.
    // It must allow a manually answered incoming call to start AI, even
    // if the published policy disables taking the call automatically.
    fun attachAfterOffhook(
        offhook:Boolean, agentEnabled:Boolean, humanControl:Boolean, attempted:Boolean
    ):Boolean = offhook && agentEnabled && !humanControl && !attempted
}
