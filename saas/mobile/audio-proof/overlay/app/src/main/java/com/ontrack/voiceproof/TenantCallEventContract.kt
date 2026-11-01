package com.ontrack.voiceproof

/**
 * Per-subscription phone-state transitions only: no phone numbers, audio,
 * recordings, dial requests or assumption that an AI agent spoke.
 * Android CALL_STATE_* constants are 0 idle, 1 ringing, 2 off-hook.
 */
internal object TenantCallEventContract {
    data class State(val callId:String? = null, val startedElapsedMs:Long = 0L, val lastState:Int = 0)
    data class Event(val callId:String, val phase:String, val durationSeconds:Int, val occurredAt:String)
    data class Result(val state:State, val event:Event? = null)
    fun observe(previous:State, state:Int, elapsedMs:Long, newCallId:String, occurredAt:String):Result {
        require(state in 0..2) { "Invalid Android call state" }
        if(state==previous.lastState)return Result(previous)
        if(state==0) {
            val id=previous.callId ?: return Result(State(lastState=0))
            val seconds=if(previous.startedElapsedMs>0L && elapsedMs>=previous.startedElapsedMs)
                ((elapsedMs-previous.startedElapsedMs)/1000L).coerceIn(0L,86400L).toInt() else 0
            return Result(State(lastState=0),Event(id,"ended",seconds,occurredAt))
        }
        val id=previous.callId ?: newCallId
        require(Regex("^[a-f0-9-]{36}$").matches(id)) { "Invalid call identifier" }
        // Duration starts when off-hook (answered), not while ringing.
        val start=if(state==2 && previous.lastState!=2)elapsedMs else previous.startedElapsedMs
        return Result(State(id,start,state),Event(id,if(state==1)"ringing" else "active",0,occurredAt))
    }
}
