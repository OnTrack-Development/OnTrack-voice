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
internal class CallEventOutbox(private val context: Context,storeName:String="ontrack_call_events") {
    private val prefs=context.getSharedPreferences(storeName,Context.MODE_PRIVATE)

    /** The same UUID used by the existing durable event outbox.
     * Read it BEFORE resetting idle state; never generate a second call ID.
     */
    @Synchronized fun currentCallId(slot:Int):String? {
        if(slot !in 0..1 || prefs.getInt("active_sim_slot",-1)!=slot)return null
        val id=prefs.getString("active_id","").orEmpty()
        return id.takeIf{it.matches(Regex("[a-f0-9-]{36}"))}
    }

    /** Caller explicitly requested an employee in Google's live input text.
     * Enqueue only a status flag, never the transcript or any phone number.
     * This is separate from handset takeover and survives a network outage.
     */
    @Synchronized fun recordCustomerHumanRequest(slot:Int,deviceId:Int,reason:String="",notes:String=""):Boolean {
        if(slot !in 0..1 || deviceId<=0 ||
            prefs.getInt("active_sim_slot",-1)!=slot ||
            prefs.getInt("active_device_id",-1)!=deviceId ||
            prefs.getInt("last_state",0)!=2)return false
        val callId=prefs.getString("active_id","").orEmpty()
        if(!callId.matches(Regex("[a-f0-9-]{36}")) ||
            prefs.getString("customer_human_request_id","")==callId)return false
        val pending=JSONArray(prefs.getString("pending","[]")?:"[]")
        check(pending.length()<1000){"Call event queue full"}
        val sequence=prefs.getInt("sequence",0)+1
        pending.put(JSONObject().put("call_id",callId).put("sequence",sequence)
            .put("phase","customer_human_request").put("duration_seconds",0)
            .put("handoff_reason",reason.trim().take(350))
            .put("customer_notes",notes.trim().take(700))
            .put("sim_slot",slot).put("device_id",deviceId))
        prefs.edit().putString("pending",pending.toString())
            .putInt("sequence",sequence).putString("customer_human_request_id",callId).apply()
        return true
    }

    /**
     * Gemini Live's structured call notes are stored through the normal
     * durable device event outbox. Unlike inputTranscription, this can still
     * work on a cellular call whose transcript stream is missing or partial.
     * It is labelled an AI working note, NOT a verified recording.
     */
    @Synchronized fun recordAgentCallNote(slot:Int,deviceId:Int,summary:String,notes:String):Boolean {
        val safeSummary=summary.trim().take(1000)
        val safeNotes=notes.trim().take(1000)
        if(safeSummary.isBlank() && safeNotes.isBlank())return false
        if(slot !in 0..1||deviceId<=0 ||
            prefs.getInt("active_sim_slot",-1)!=slot ||
            prefs.getInt("active_device_id",-1)!=deviceId ||
            prefs.getInt("last_state",0)!=2)return false
        val callId=prefs.getString("active_id","").orEmpty()
        if(!callId.matches(Regex("[a-f0-9-]{36}")))return false
        val pending=JSONArray(prefs.getString("pending","[]")?:"[]")
        check(pending.length()<1000){"Call event queue full"}
        val sequence=prefs.getInt("sequence",0)+1
        pending.put(JSONObject().put("call_id",callId).put("sequence",sequence)
            .put("phase","agent_report").put("duration_seconds",0)
            .put("summary",safeSummary).put("customer_notes",safeNotes)
            .put("sim_slot",slot).put("device_id",deviceId))
        prefs.edit().putString("pending",pending.toString()).putInt("sequence",sequence).apply()
        return true
    }

    /** User explicitly took the current AI call; keep this event through network loss. */
    @Synchronized fun recordHumanTakeover(slot:Int,deviceId:Int):Boolean {
        if(slot !in 0..1 || deviceId<=0 ||
           prefs.getInt("active_sim_slot",-1)!=slot ||
           prefs.getInt("active_device_id",-1)!=deviceId ||
           prefs.getInt("last_state",0)!=2)return false
        val callId=prefs.getString("active_id","").orEmpty()
        if(!callId.matches(Regex("[a-f0-9-]{36}")) ||
            prefs.getString("handoff_recorded_id","")==callId)return false
        val pending=JSONArray(prefs.getString("pending","[]")?:"[]")
        check(pending.length()<1000){"Call event queue full; human takeover not recorded"}
        val sequence=prefs.getInt("sequence",0)+1
        pending.put(JSONObject().put("call_id",callId).put("sequence",sequence)
            .put("phase","human_takeover").put("duration_seconds",0)
            .put("sim_slot",slot).put("device_id",deviceId))
        prefs.edit().putString("pending",pending.toString())
            .putInt("sequence",sequence).putString("handoff_recorded_id",callId).apply()
        return true
    }

    @Synchronized fun observe(state:Int,slot:Int=-1,deviceId:Int=-1) {
        if(state !in 0..2)return
        val previous=prefs.getInt("last_state",0)
        if(state==previous)return
        var id=prefs.getString("active_id","").orEmpty()
        var sequence=prefs.getInt("sequence",0)
        var simSlot=prefs.getInt("active_sim_slot",-1)
        var originalDevice=prefs.getInt("active_device_id",-1)
        var began=prefs.getLong("started_elapsed",0L)
        var answered=prefs.getLong("answered_elapsed",0L)
        var beganWall=prefs.getLong("started_wall_ms",0L)
        val pending=JSONArray(prefs.getString("pending","[]")?:"[]")
        check(pending.length()<1000){"Call event queue full; cannot discard events"}
        if(state!=0 && id.isEmpty()){
            // Ignore a call from any unpaired SIM, never assign its events later.
            if(slot !in 0..1 || deviceId<=0)return
            simSlot=slot
            originalDevice=deviceId
            id=UUID.randomUUID().toString()
            sequence=0
            began=SystemClock.elapsedRealtime()
            beganWall=System.currentTimeMillis()
            answered=0L
        }
        if(state==2 && previous!=2 && answered==0L)answered=SystemClock.elapsedRealtime()
        if(id.isEmpty()){
            prefs.edit().putInt("last_state",state).apply()
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
            .put("sim_slot",simSlot).put("device_id",originalDevice)
        pending.put(entry)
        val lookups=JSONArray(prefs.getString("pending_lookup","[]")?:"[]")
        if(state==0 && beganWall>0L){
            // Leave attribution unknown until a unique Android CallLog entry is found.
            if(lookups.length()<40)lookups.put(JSONObject()
                .put("call_id",id).put("sequence",sequence+2)
                .put("started",beganWall).put("ended",System.currentTimeMillis())
                .put("duration",duration).put("sim_slot",simSlot)
                .put("device_id",originalDevice))
        }
        prefs.edit().putString("pending",pending.toString())
            .putString("pending_lookup",lookups.toString())
            .putInt("last_state",state)
            .putString("active_id",if(state==0)"" else id)
            .putInt("sequence",if(state==0)0 else sequence+1)
            .putInt("active_sim_slot",if(state==0)-1 else simSlot)
            .putInt("active_device_id",if(state==0)-1 else originalDevice)
            .putLong("started_elapsed",if(state==0)0L else began)
            .putLong("answered_elapsed",if(state==0)0L else answered)
            .putLong("started_wall_ms",if(state==0)0L else beganWall).apply()
    }


    /** Retry for eventual CallLog consistency; never guess from unrelated saved contacts. */
    /** One post-call identity lookup per idle pass; ContentResolver never holds the outbox lock. */
    fun enrichFromCallLog(allowLookup:()->Boolean = { true }) {
        if(ContextCompat.checkSelfPermission(context,Manifest.permission.READ_CALL_LOG)!=PackageManager.PERMISSION_GRANTED || !allowLookup())return
        val now=System.currentTimeMillis()
        val candidate=synchronized(this) {
            val lookups=JSONArray(prefs.getString("pending_lookup","[]")?:"[]")
            (0 until lookups.length()).mapNotNull{lookups.optJSONObject(it)}
                .firstOrNull{now-it.optLong("ended") in 2500L..86400000L}
                ?.let{JSONObject(it.toString())}
        } ?: return
        if(!allowLookup())return
        val identified=runCatching{matchingCall(candidate,allowLookup)}.getOrNull()
        if(!allowLookup())return
        synchronized(this){
            val latest=JSONArray(prefs.getString("pending_lookup","[]")?:"[]")
            val pending=JSONArray(prefs.getString("pending","[]")?:"[]")
            val rest=JSONArray()
            var removed=false
            for(i in 0 until latest.length()){
                val item=latest.optJSONObject(i)?:continue
                if(now-item.optLong("ended")>86400000L)continue
                val same=!removed && item.optString("call_id")==candidate.optString("call_id") &&
                    item.optInt("sequence")==candidate.optInt("sequence") &&
                    item.optInt("device_id",-1)==candidate.optInt("device_id",-1)
                if(same && identified!=null && pending.length()<1000){
                    pending.put(JSONObject().put("call_id",item.getString("call_id"))
                        .put("sequence",item.getInt("sequence")).put("phase","identified")
                        .put("duration_seconds",item.getInt("duration"))
                        .put("sim_slot",item.optInt("sim_slot",-1))
                        .put("device_id",item.optInt("device_id",-1))
                        .put("phone_number",identified.first).put("contact_name",identified.second))
                    removed=true
                }else rest.put(item)
            }
            if(removed)check(prefs.edit().putString("pending",pending.toString())
                .putString("pending_lookup",rest.toString()).commit()){"Identity event persistence failed"}
            else if(rest.length()!=latest.length())
                check(prefs.edit().putString("pending_lookup",rest.toString()).commit()){"Lookup state persistence failed"}
        }
    }

    private fun matchingCall(item:JSONObject,allowLookup:()->Boolean):Pair<String,String?>? {
        if(!allowLookup())return null
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
                if(!allowLookup())return null
                val origin=cursor.getLong(date)
                val phone=cursor.getString(number)?.trim().orEmpty()
                val seconds=cursor.getLong(duration)
                val kind=cursor.getInt(type)
                if(origin<start-30000L||origin>end+10000L)continue
                if(kind !in listOf(CallLog.Calls.INCOMING_TYPE,CallLog.Calls.OUTGOING_TYPE,CallLog.Calls.MISSED_TYPE))continue
                if(!phone.matches(Regex("[+0-9()\\s-]{3,40}")))continue
                if(kotlin.math.abs(seconds-observed.toLong())>15L)continue
                if(!allowLookup())return null
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


    /** Skip legacy or foreign-binding records without deleting their local evidence.
     * An in-place APK update and re-pair must never upload them under a new tenant. */
    @Synchronized fun peekEligible(bindingId:(Int)->Int?):JSONObject? {
        val pending=JSONArray(prefs.getString("pending","[]")?:"[]")
        for(i in 0 until pending.length()){
            val row=pending.optJSONObject(i)?:continue
            val slot=row.optInt("sim_slot",-1)
            val original=row.optInt("device_id",-1)
            if(slot in 0..1 && original>0 && bindingId(slot)==original)return row
        }
        return null
    }

    @Synchronized fun acknowledge(sent:JSONObject) {
        val pending=JSONArray(prefs.getString("pending","[]")?:"[]")
        val rest=JSONArray()
        var removed=false
        for(i in 0 until pending.length()){
            val first=pending.optJSONObject(i)?:continue
            val match=!removed && first.optString("call_id")==sent.optString("call_id") &&
                first.optInt("sequence")==sent.optInt("sequence") &&
                first.optInt("device_id",-1)==sent.optInt("device_id",-1)
            if(match)removed=true else rest.put(first)
        }
        if(removed)check(prefs.edit().putString("pending",rest.toString()).commit()){
            "Call event acknowledgment failed"
        }
    }
}
