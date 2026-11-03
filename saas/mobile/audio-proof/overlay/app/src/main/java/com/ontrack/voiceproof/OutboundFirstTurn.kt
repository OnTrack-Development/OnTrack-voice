package com.ontrack.voiceproof

/** An outgoing GSM call enters OFFHOOK while it is still dialing.
 * Only observed remote speech, not OFFHOOK or a synthetic user turn, may
 * authorize the voice agent's first audible response.
 */
internal object OutboundFirstTurn {
    fun sendInitialGreeting(outgoing:Boolean):Boolean = !outgoing

    /** Gemini input-audio transcription is the supported first speech signal.
     * These checks exclude blank/noise/ringback markers from the audio model.
     * Never pass this text to a command/action dispatcher.
     */
    fun remoteSpeechTranscribed(text:String):Boolean {
        val sample=text.trim()
        if(sample.length < 2 || sample.length > 500) return false
        if(sample.startsWith("[") || sample.startsWith("(") || sample.startsWith("<")) return false
        val lower=sample.lowercase()
        if(lower in setOf("ringing","ringback","silence","noise","beep","tone",
                "رنين","صمت","ضوضاء","صفارة","جرس")) return false
        return sample.count { it.isLetter() } >= 2
    }
}
