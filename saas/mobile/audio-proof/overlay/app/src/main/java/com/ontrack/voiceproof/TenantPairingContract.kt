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
    fun slot(value: Int): Int {
        require(value == 0 || value == 1) { "الشريحة غير مدعومة" }
        return value
    }
    fun token(value: String): String {
        require(TOKEN.matches(value)) { "استجابة الربط غير صالحة" }
        return value
    }
    fun qr(uri: String): Pair<Int, String>? {
        // Strict parsing without allowing an untrusted URL to redirect the API.
        val match = Regex("^ontrackvoice://pair\\?slot=([01])&code=([A-Fa-f0-9-]{24,29})$").matchEntire(uri) ?: return null
        return runCatching { Pair(slot(match.groupValues[1].toInt()), code(match.groupValues[2])) }.getOrNull()
    }
}
