package com.ontrack.voiceproof

/** Low latency playback arbitration. A real caller transcript during the last
 * 1.5s of generated speech locally cuts old AI audio even if Google's
 * serverContent.interrupted signal arrives late on cellular.
 * Never infer speaker identity from unlabelled modem PCM.
 */
internal object LiveInterruptionPolicy {
    fun shouldCutPreviousSpeech(nowMs:Long,lastAssistantAudioMs:Long,lastCutMs:Long,callerText:String):Boolean {
        if(callerText.trim().length<3 || lastAssistantAudioMs<=0L)return false
        if(nowMs<lastAssistantAudioMs || nowMs-lastAssistantAudioMs>1500L)return false
        return lastCutMs<=0L || (nowMs>=lastCutMs && nowMs-lastCutMs>=450L)
    }
}

internal object LiveVadConfig {
    const val DEFAULT_MAX_OUTPUT_TOKENS = 1024
    const val MIN_OUTPUT_TOKENS = 512
    const val MAX_OUTPUT_TOKENS = 2048
    // 160ms prefix padding preserves initial Arabic consonants without clipping onset;
    // 320ms silence duration responds snappily without chopping trailing words.
    const val PREFIX_PADDING_MS = 160
    const val SILENCE_DURATION_MS = 320

    fun clampMaxOutputTokens(value: Int): Int =
        value.coerceIn(MIN_OUTPUT_TOKENS, MAX_OUTPUT_TOKENS)
}
