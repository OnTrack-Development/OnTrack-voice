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
