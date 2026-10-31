package com.ontrack.voiceproof

import android.content.Context
import android.os.SystemClock
import org.json.JSONArray
import org.json.JSONObject
import java.util.UUID

/** Durable pilot event queue. Caller identity is intentionally unknown until verified. */
internal class CallEventOutbox(context: Context) {
    private val prefs=context.getSharedPreferences("ontrack_call_events",Context.MODE_PRIVATE)

    @Synchronized fun observe(state:Int) {
        if(state !in 0..2)return
        val previous=prefs.getInt("last_state",0)
        if(state==previous)return
        var id=prefs.getString("active_id","").orEmpty()
        var sequence=prefs.getInt("sequence",0)
        var began=prefs.getLong("started_elapsed",0L)
        var answered=prefs.getLong("answered_elapsed",0L)
        val pending=JSONArray(prefs.getString("pending","[]")?:"[]")
        check(pending.length()<1000){"Call event queue full; cannot discard events"}
        if(state!=0 && id.isEmpty()){
            id=UUID.randomUUID().toString()
            sequence=0
            began=SystemClock.elapsedRealtime()
            answered=0L
        }
        if(state==2 && previous!=2 && answered==0L)answered=SystemClock.elapsedRealtime()
        if(id.isEmpty()){
            prefs.edit().putInt("last_state",state).commit()
            return
        }
        val phase=when(state){
            1->"ringing"
            2->"active"
            else->if(previous==1)"missed" else "ended"
        }
        val duration=if(state==0 && answered>0L)
            ((SystemClock.elapsedRealtime()-answered).coerceAtLeast(0L)/1000L).coerceAtMost(172800L).toInt()
            else 0
        val entry=JSONObject().put("call_id",id).put("sequence",sequence+1)
            .put("phase",phase).put("duration_seconds",duration)
        pending.put(entry)
        check(prefs.edit().putString("pending",pending.toString())
            .putInt("last_state",state)
            .putString("active_id",if(state==0)"" else id)
            .putInt("sequence",if(state==0)0 else sequence+1)
            .putLong("started_elapsed",if(state==0)0L else began)
            .putLong("answered_elapsed",if(state==0)0L else answered).commit()){
                "Call event persistence failed"
            }
    }

    @Synchronized fun peek():JSONObject?=runCatching{
        JSONArray(prefs.getString("pending","[]")?:"[]").optJSONObject(0)
    }.getOrNull()

    @Synchronized fun acknowledge(sent:JSONObject) {
        val pending=JSONArray(prefs.getString("pending","[]")?:"[]")
        val first=pending.optJSONObject(0)?:return
        if(first.optString("call_id")!=sent.optString("call_id") ||
            first.optInt("sequence")!=sent.optInt("sequence"))return
        val rest=JSONArray()
        for(i in 1 until pending.length())rest.put(pending.get(i))
        check(prefs.edit().putString("pending",rest.toString()).commit()){
            "Call event acknowledgment failed"
        }
    }
}
