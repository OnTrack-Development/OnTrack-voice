package com.ontrack.voiceproof

/** Real cellular calls may temporarily lose NET_CAPABILITY_VALIDATED while
 * IMS/VoLTE switches radio state. A validated Wi-Fi network is mandatory,
 * but any cellular radio (2G/3G/H/H+/4G/5G) with an INTERNET-capable default route may retry before
 * Android's captive-portal validation completes. There is never a retry
 * with no active network, or after explicit human takeover.
 */
internal object CellularCallRecoveryPolicy {
    const val MAX_VOICE_START_ATTEMPTS = 12
    const val MAX_AUTO_ANSWER_ATTEMPTS = 3

    fun mayRetryLive(
        defaultNetworkPresent:Boolean,
        internetCapability:Boolean,
        validated:Boolean,
        cellularTransport:Boolean,
    ):Boolean =
        defaultNetworkPresent && internetCapability && (validated || cellularTransport)

    /** A changed 4G/H+/3G radio or recovered packet route may earn a
     * limited new voice-start budget, never a second cellular answer.
     * Caller caps this to at most two resets within the same SIM call.
     */
    fun canRearmAfterNetworkReturn(
        offhook:Boolean, enabled:Boolean, humanControl:Boolean,
        pcmActive:Boolean, attempts:Int,
    ):Boolean = offhook && enabled && !humanControl && !pcmActive && attempts>0

    fun retryDelayMs(attempts:Int):Long = 1000L

    fun autoAnswerRetryAllowed(
        ringing:Boolean,
        alreadyAcknowledged:Boolean,
        attempts:Int,
        nowMs:Long,
        nextRetryAtMs:Long,
    ):Boolean =
        ringing && !alreadyAcknowledged &&
        attempts in 0 until MAX_AUTO_ANSWER_ATTEMPTS && nowMs >= nextRetryAtMs

    fun answerRetryDelayMs(attempts:Int):Long =
        900L * attempts.coerceIn(1,MAX_AUTO_ANSWER_ATTEMPTS)
}
