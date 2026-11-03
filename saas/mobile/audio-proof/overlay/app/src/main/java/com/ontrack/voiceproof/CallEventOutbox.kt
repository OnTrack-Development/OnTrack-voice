package com.ontrack.voiceproof

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.net.Uri
import android.provider.CallLog
import android.provider.ContactsContract
import androidx.core.content.ContextCompat
import android.os.SystemClock
import org.json.JSONArray
import org.json.JSONObject
import java.util.UUID

/** Durable event queue. Identity enrichment requires user-granted call-log access and a unique verified record. */
internal class CallEventOutbox(private val context: Context) {
    private val prefs=context.getSharedPreferences("ontrack_call_events",Context.MODE_PRIVATE)

    @Synchronized fun observe(state:Int) {
        if(state !in 0..2)return
        val previous=prefs.getInt("last_state",0)
        if(state==previous)return
        var id=prefs.getString("active_id","").orEmpty()
        var sequence=prefs.getInt("sequence",0)
        var began=prefs.getLong("started_elapsed",0L)
        var answered=prefs.getLong("answered_elapsed",0L)
        var beganWall=prefs.getLong("started_wall_ms",0L)
        val pending=JSONArray(prefs.getString("pending","[]")?:"[]")
        check(pending.length()<1000){"Call event queue full; cannot discard events"}
        if(state!=0 && id.isEmpty()){
            id=UUID.randomUUID().toString()
            sequence=0
            began=SystemClock.elapsedRealtime()
            beganWall=System.currentTimeMillis()
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
        val lookups=JSONArray(prefs.getString("pending_lookup","[]")?:"[]")
        if(state==0 && beganWall>0L){
            // Leave attribution unknown until a unique Android CallLog entry is found.
            if(lookups.length()<40)lookups.put(JSONObject()
                .put("call_id",id).put("sequence",sequence+2)
                .put("started",beganWall).put("ended",System.currentTimeMillis())
                .put("duration",duration))
        }
        val reports=JSONArray(prefs.getString("pending_report","[]") ?: "[]")
        if(state==0 && answered>0L){
            check(reports.length()<350) { "Call reports full; finalization not queued" }
            // Enqueued after all previously observed transcripts; the server only summarizes genuine text.
            reports.put(JSONObject().put("call_id",id).put("entry_id",UUID.randomUUID().toString())
                .put("kind","finalize").put("role","system").put("text","end_of_call"))
        }
        check(prefs.edit().putString("pending",pending.toString())
            .putString("pending_report",reports.toString())
            .putString("pending_lookup",lookups.toString())
            .putInt("last_state",state)
            .putString("active_id",if(state==0)"" else id)
            .putInt("sequence",if(state==0)0 else sequence+1)
            .putLong("started_elapsed",if(state==0)0L else began)
            .putLong("answered_elapsed",if(state==0)0L else answered)
            .putLong("started_wall_ms",if(state==0)0L else beganWall).commit()){
                "Call event persistence failed"
            }
    }


    @Synchronized fun activeCallId(): String? =
        prefs.getString("active_id", null)?.takeIf { it.isNotBlank() }

    /** Model transcription and handoff signals are queued separately from telephony phases. */
    @Synchronized fun appendReport(callId: String, kind: String, role: String, text: String) {
        require(kind in listOf("transcript","handoff")) { "unsupported report event" }
        require(role in listOf("caller","agent","system")) { "unsupported report author" }
        require(kind != "transcript" || role != "system") { "transcript must identify the speaker" }
        val clean = text.trim().take(1500)
        if(clean.isBlank()) return
        require(UUID.fromString(callId).toString() == callId) { "invalid call id" }
        val queue = JSONArray(prefs.getString("pending_report","[]") ?: "[]")
        check(queue.length() < 350) { "Call transcript queue is full" }
        queue.put(JSONObject().put("call_id",callId).put("entry_id",UUID.randomUUID().toString())
            .put("kind",kind).put("role",role).put("text",clean))
        check(prefs.edit().putString("pending_report",queue.toString()).commit()) {
            "Transcript persistence failed"
        }
    }

    @Synchronized fun peekReport(): JSONObject? =
        runCatching { JSONArray(prefs.getString("pending_report","[]") ?: "[]").optJSONObject(0) }.getOrNull()

    @Synchronized fun acknowledgeReport(sent: JSONObject) {
        val queue = JSONArray(prefs.getString("pending_report","[]") ?: "[]")
        val first = queue.optJSONObject(0) ?: return
        if(first.optString("entry_id") != sent.optString("entry_id") ||
            first.optString("call_id") != sent.optString("call_id")) return
        val rest = JSONArray()
        for(i in 1 until queue.length()) rest.put(queue.get(i))
        check(prefs.edit().putString("pending_report",rest.toString()).commit()) {
            "Report acknowledgment failed"
        }
    }

    /** Retry for eventual CallLog consistency; never guess from unrelated saved contacts. */
    @Synchronized fun enrichFromCallLog() {
        if(ContextCompat.checkSelfPermission(context,Manifest.permission.READ_CALL_LOG)!=PackageManager.PERMISSION_GRANTED)return
        val lookups=JSONArray(prefs.getString("pending_lookup","[]")?:"[]")
        if(lookups.length()==0)return
        val now=System.currentTimeMillis()
        val rest=JSONArray()
        var consumed=false
        for(i in 0 until lookups.length()){
            val item=lookups.optJSONObject(i)?:continue
            if(now-item.optLong("ended")>3600000L)continue // bounded history; never endless polling
            if(consumed || now-item.optLong("ended")<2500L){rest.put(item);continue}
            consumed=true
            val identified=runCatching{matchingCall(item)}.getOrNull()
            if(identified!=null){
                val pending=JSONArray(prefs.getString("pending","[]")?:"[]")
                if(pending.length()>=1000){rest.put(item);continue}
                val event=JSONObject().put("call_id",item.getString("call_id"))
                    .put("sequence",item.getInt("sequence"))
                    .put("phase","identified")
                    .put("duration_seconds",item.getInt("duration"))
                    .put("phone_number",identified.first)
                    .put("contact_name",identified.second)
                pending.put(event)
                check(prefs.edit().putString("pending",pending.toString()).commit()){"Identity event persistence failed"}
            } else rest.put(item)
        }
        check(prefs.edit().putString("pending_lookup",rest.toString()).commit()){"Lookup state persistence failed"}
    }

    private fun matchingCall(item:JSONObject):Pair<String,String?>? {
        val start=item.optLong("started");val end=item.optLong("ended")
        val observed=item.optInt("duration")
        val projection=arrayOf(CallLog.Calls.DATE,CallLog.Calls.NUMBER,
            CallLog.Calls.DURATION,CallLog.Calls.CACHED_NAME,CallLog.Calls.TYPE)
        val found=mutableListOf<Pair<String,String?>>()
        val selection="${CallLog.Calls.DATE} >= ? AND ${CallLog.Calls.DATE} <= ?"
        context.contentResolver.query(CallLog.Calls.CONTENT_URI,projection,selection,
            arrayOf((start-30000L).toString(),(end+10000L).toString()),
            CallLog.Calls.DATE+" DESC")?.use{cursor->
            val date=cursor.getColumnIndexOrThrow(CallLog.Calls.DATE)
            val number=cursor.getColumnIndexOrThrow(CallLog.Calls.NUMBER)
            val duration=cursor.getColumnIndexOrThrow(CallLog.Calls.DURATION)
            val cached=cursor.getColumnIndexOrThrow(CallLog.Calls.CACHED_NAME)
            val type=cursor.getColumnIndexOrThrow(CallLog.Calls.TYPE)
            while(cursor.moveToNext()&&found.size<3){
                val origin=cursor.getLong(date)
                val phone=cursor.getString(number)?.trim().orEmpty()
                val seconds=cursor.getLong(duration)
                val kind=cursor.getInt(type)
                if(origin<start-30000L||origin>end+10000L)continue
                if(kind !in listOf(CallLog.Calls.INCOMING_TYPE,CallLog.Calls.OUTGOING_TYPE,CallLog.Calls.MISSED_TYPE))continue
                if(!phone.matches(Regex("[+0-9()\\s-]{3,40}")))continue
                if(kotlin.math.abs(seconds-observed.toLong())>15L)continue
                val name=resolveName(phone)?:cursor.getString(cached)?.trim()?.take(100)?.ifBlank{null}
                found.add(phone to name)
            }
        }
        return found.singleOrNull()
    }

    private fun resolveName(number:String):String? {
        if(ContextCompat.checkSelfPermission(context,Manifest.permission.READ_CONTACTS)!=PackageManager.PERMISSION_GRANTED)return null
        val uri=Uri.withAppendedPath(ContactsContract.PhoneLookup.CONTENT_FILTER_URI,Uri.encode(number))
        context.contentResolver.query(uri,arrayOf(ContactsContract.PhoneLookup.DISPLAY_NAME),null,null,null)?.use{cursor->
            if(cursor.moveToFirst())return cursor.getString(0)?.trim()?.take(100)?.ifBlank{null}
        }
        return null
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
