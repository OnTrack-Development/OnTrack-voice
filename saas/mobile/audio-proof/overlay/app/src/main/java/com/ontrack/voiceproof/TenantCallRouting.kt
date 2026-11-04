package com.ontrack.voiceproof

/**
 * Native phone-call decision rules shared by incoming, manually answered
 * incoming and outgoing SIM calls. An enabled monitor is not an answered call.
 */
internal object TenantCallRouting {
    // A global shell call-state has no SIM identity: only a single currently
    // active, owner-paired subscription may safely use the legacy Q bridge.
    fun nativeSingleSimSlot(activeSlots:List<Int>,pairedSlots:Set<Int>):Int? =
        activeSlots.filter{it in 0..1}.distinct().singleOrNull()
            ?.takeIf{it in pairedSlots}

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
