package com.ontrack.voiceproof

/** Only a server-authenticated Fish TTS *provider* error permits switching
 * to Google's already-authorized audio during this exact SIM call.
 * Device/subscription/published-flow 401/403 errors MUST NOT bypass access.
 * No provider secret is included in the exception, UI or logs.
 */
internal object FishTtsFailover {
    fun reason(http:Int,code:String):String? = when {
        http==402 && code=="premium_tts_credit_exhausted" -> "credit"
        http==429 && code=="premium_tts_rate_limited" -> "rate_limit"
        http==503 && code in setOf("premium_tts_provider_unavailable","premium_tts_network_error") -> "provider_unavailable"
        http==502 && code in setOf("premium_tts_empty_audio","premium_tts_generation_failed") -> "tts_failed"
        else -> null
    }
    fun httpFallbackReason(http:Int,code:String):String? = reason(http,code) ?: when {
        http in 500..504 -> "provider_unavailable"
        else -> null
    }
    fun label(reason:String):String = when(reason){
        "credit" -> "رصيد Fish Audio انتهى؛ تحويل صوت الإيجنت إلى Google Live داخل نفس المكالمة"
        "rate_limit" -> "Fish Audio مزدحم؛ تحويل الصوت إلى Google Live داخل نفس المكالمة"
        "tts_timeout" -> "تأخر استجابة الصوت على الداتا؛ تحويل الصوت إلى Google Live المباشر داخل نفس المكالمة"
        else -> "صوت Fish Audio غير متاح؛ تحويل الإيجنت إلى Google Live داخل نفس المكالمة"
    }
}
internal class FishTtsFailoverException(val causeCode:String):
    IllegalStateException("fish_tts_fallback_$causeCode")
