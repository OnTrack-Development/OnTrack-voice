package com.ontrack.voiceproof

/** The new tenant API is deliberately independent from the legacy pilot bearer. */
object TenantPairingContract {
    const val ENDPOINT = "https://app.ontrackegy.com/saas/console/device/v1/index.php"
    private val CODE = Regex("(?i)^[a-f0-9]{4}(?:-?[a-f0-9]{4}){5}$")
    private val TOKEN = Regex("^otdev_[a-f0-9]{64}$")
    fun code(raw: String): String {
        val value = raw.trim()
        require(CODE.matches(value)) { "رمز الاقتران غير صالح (24 حرفًا)" }
        return value.uppercase()
    }
    /** Server locks a single-use code to exactly one SIM slot (92 random bits). */
    fun codeForSlot(raw: String, selectedSlot: Int): String {
        val normalized = code(raw)
        require(normalized[0] == ('0' + slot(selectedSlot))) { "رمز الربط مخصص للشريحة الأخرى" }
        return normalized
    }
    fun slot(value: Int): Int {
        require(value == 0 || value == 1) { "الشريحة غير مدعومة" }
        return value
    }
    fun token(value: String): String {
        require(TOKEN.matches(value)) { "استجابة الربط غير صالحة" }
        return value
    }
    /** Never accept a stale server heartbeat after a newer agent assignment. */
    fun agentRevisionValid(stored:Int,incoming:Int,agentId:Int):Boolean =
        incoming>=1 && incoming>=stored && agentId>0
    /** Pin a call to the assignment observed at its first state transition. */
    fun pinnedCallRevision(activeCallId:String?,pinned:Int,assigned:Int):Int {
        val revision=if(activeCallId==null)assigned else pinned
        require(revision>0) { "إصدار الإيجنت غير متاح للمكالمة" }
        return revision
    }
    /** A manually confirmed number is mandatory for a DNC call outcome. */
    fun humanOptOutValid(outcome:String,phone:String,confirmed:Boolean):Boolean =
        outcome!="do_not_call" || (confirmed && phone.filter { it.isDigit() }.length in 8..40)
    /** Fail closed for legacy pilot telephony while any tenant SIM is paired or pairing. */
    fun pilotAllowed(pairingInProgress:Boolean,hasTenantSim:Boolean):Boolean =
        !pairingInProgress && !hasTenantSim
    /** A native per-slot status label; never infer server authorization from local storage. */
    fun statusLine(slot: Int, linked: Boolean, account: String = "", site: String = "",
                   agent: String = "", error: String? = null): String {
        val prefix = "SIM ${TenantPairingContract.slot(slot) + 1}"
        if (!linked) return "$prefix — غير مربوطة"
        if (error != null) return "$prefix — $error"
        return "$prefix — ${account.ifBlank { "شركة غير معروفة" }} / ${site.ifBlank { "موقع غير معروف" }} / ${agent.ifBlank { "إيجنت غير معروف" }}"
    }
    fun qr(uri: String): Pair<Int, String>? {
        // Strict parsing without allowing an untrusted URL to redirect the API.
        val match = Regex("^ontrackvoice://pair\\?slot=([01])&code=([A-Fa-f0-9-]{24,29})$").matchEntire(uri) ?: return null
        return runCatching {
            val selectedSlot = slot(match.groupValues[1].toInt())
            Pair(selectedSlot, codeForSlot(match.groupValues[2], selectedSlot))
        }.getOrNull()
    }
}
