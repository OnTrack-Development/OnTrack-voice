package com.ontrack.voiceproof

/** Real cellular calls may temporarily lose NET_CAPABILITY_VALIDATED while
 * IMS/VoLTE switches radio state. A validated Wi-Fi network is mandatory,
 * but LTE with an actual default INTERNET-capable network may retry before
 * Android's captive-portal validation completes. There is never a retry
 * with no active network, or after explicit human takeover.
 */
internal object CellularCallRecoveryPolicy {
    const val MAX_VOICE_START_ATTEMPTS = 6
    const val MAX_AUTO_ANSWER_ATTEMPTS = 3

    fun mayRetryLive(
        defaultNetworkPresent:Boolean,
        internetCapability:Boolean,
        validated:Boolean,
        cellularTransport:Boolean,
    ):Boolean =
        defaultNetworkPresent && internetCapability && (validated || cellularTransport)

    fun retryDelayMs(attempts:Int):Long =
        (2000L * attempts.coerceIn(1,6)).coerceAtMost(12000L)

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
