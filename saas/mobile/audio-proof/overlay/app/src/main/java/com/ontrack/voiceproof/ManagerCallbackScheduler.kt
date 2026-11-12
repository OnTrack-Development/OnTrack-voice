package com.ontrack.voiceproof

import kotlin.math.ceil

internal data class PendingManagerCallback(
    val slot: Int,
    val phone: String,
    val name: String,
    val callId: String,
)

/**
 * Coordinates the strict 10-second post-call manager callback timer after CALL_STATE_IDLE.
 * Pure elapsed-time state machine so timer countdown and cancellation are deterministic in tests.
 */
internal class ManagerCallbackScheduler {
    companion object {
        const val DELAY_MS: Long = 10_000L
    }

    var pendingSlot: Int = -1
        private set
    var pendingPhone: String = ""
        private set
    var pendingName: String = ""
        private set
    var pendingCallId: String = ""
        private set
    var dueAtElapsedMs: Long = 0L
        private set

    val active: Boolean
        get() = pendingSlot in 0..1 && pendingPhone.isNotBlank() && dueAtElapsedMs > 0L

    fun schedule(
        slot: Int,
        phone: String,
        name: String = "المدير المناوب",
        callId: String = "",
        nowElapsedMs: Long,
    ) {
        if (slot !in 0..1 || phone.isBlank()) return
        pendingSlot = slot
        pendingPhone = phone.trim()
        pendingName = name.trim().ifBlank { "المدير المناوب" }
        pendingCallId = callId.trim()
        dueAtElapsedMs = nowElapsedMs + DELAY_MS
    }

    fun cancel() {
        pendingSlot = -1
        pendingPhone = ""
        pendingName = ""
        pendingCallId = ""
        dueAtElapsedMs = 0L
    }

    fun remainingSeconds(nowElapsedMs: Long): Int {
        if (!active) return 0
        val remainingMs = (dueAtElapsedMs - nowElapsedMs).coerceAtLeast(0L)
        if (remainingMs == 0L) return 0
        return ceil(remainingMs / 1000.0).toInt().coerceIn(1, 10)
    }

    fun isDue(nowElapsedMs: Long): Boolean = active && nowElapsedMs >= dueAtElapsedMs

    fun consumeDue(nowElapsedMs: Long): PendingManagerCallback? {
        if (!isDue(nowElapsedMs)) return null
        val item = PendingManagerCallback(
            slot = pendingSlot,
            phone = pendingPhone,
            name = pendingName,
            callId = pendingCallId,
        )
        cancel()
        return item
    }

    fun maskedPhone(): String {
        val raw = pendingPhone.trim()
        if (raw.length <= 6) return raw
        return raw.take(4) + "****" + raw.takeLast(3)
    }
}

