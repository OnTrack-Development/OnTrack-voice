package com.ontrack.voiceproof

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import org.json.JSONArray
import org.json.JSONObject
import java.security.KeyStore
import java.util.UUID
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

/**
 * Secure retry queue for real Gemini Live transcriptions (not recordings).
 * AES-GCM on-device encryption; only one outstanding report is submitted at
 * a time. The server stores a concise report, not the full transcript.
 */
internal class CallReportOutbox(context:Context) {
    private val prefs=context.getSharedPreferences("ontrack_tenant_call_reports_v1",Context.MODE_PRIVATE)
    private val alias="ontrack_call_report_queue_aes_v1"

    private fun key():SecretKey {
        val ks=KeyStore.getInstance("AndroidKeyStore").apply{load(null)}
        (ks.getKey(alias,null) as? SecretKey)?.let{return it}
        val gen=KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES,"AndroidKeyStore")
        gen.init(KeyGenParameterSpec.Builder(alias,
                KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT)
            .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
            .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
            .setKeySize(256).build())
        return gen.generateKey()
    }
    private fun encrypt(value:String):String {
        val cipher=Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.ENCRYPT_MODE,key())
        val bytes=cipher.iv+cipher.doFinal(value.toByteArray(Charsets.UTF_8))
        return Base64.encodeToString(bytes,Base64.NO_WRAP)
    }
    private fun decrypt(value:String):String {
        val bytes=Base64.decode(value,Base64.NO_WRAP)
        require(bytes.size>28){"encrypted report unavailable"}
        val cipher=Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.DECRYPT_MODE,key(),GCMParameterSpec(128,bytes.copyOfRange(0,12)))
        return String(cipher.doFinal(bytes.copyOfRange(12,bytes.size)),Charsets.UTF_8)
    }

    /** Persist text produced by Google Live, even if the socket reconnects.
     * Do not submit for summarization while the carrier call is still active.
     */
    @Synchronized fun appendLiveSegment(slot:Int,deviceId:Int,callId:String,text:String) {
        require(slot in 0..1 && deviceId>0 && callId.matches(Regex("[a-f0-9-]{36}")))
        if(text.length<18)return
        val items=JSONArray(prefs.getString("inflight","[]")?:"[]")
        val next=JSONArray()
        var found=false
        for(i in 0 until items.length()){
            val row=items.optJSONObject(i)?:continue
            if(row.optString("call_id")==callId && row.optInt("device_id",-1)==deviceId &&
                row.optInt("slot",-1)==slot){
                val existing=decrypt(row.getString("ciphertext"))
                val merged=(existing+"\n"+text.trim()).trim().take(12000)
                next.put(JSONObject().put("call_id",callId).put("slot",slot)
                    .put("device_id",deviceId).put("ciphertext",encrypt(merged)))
                found=true
            }else next.put(row)
        }
        if(!found){
            check(next.length()<30){"Too many unfinished voice reports"}
            next.put(JSONObject().put("call_id",callId).put("slot",slot)
                .put("device_id",deviceId).put("ciphertext",encrypt(text.trim().take(12000))))
        }
        check(prefs.edit().putString("inflight",next.toString()).commit()){
            "Could not durably save live transcript"
        }
    }

    /** Called only when no physical SIM call is active. Replay-safe on restart. */
    @Synchronized fun finalizeAtIdle(bindingId:(Int)->Int?):Int {
        val items=JSONArray(prefs.getString("inflight","[]")?:"[]")
        val remaining=JSONArray()
        var completed=0
        for(i in 0 until items.length()){
            val row=items.optJSONObject(i)?:continue
            val slot=row.optInt("slot",-1)
            val deviceId=row.optInt("device_id",-1)
            val callId=row.optString("call_id","")
            if(slot !in 0..1 || deviceId<=0 || bindingId(slot)!=deviceId){
                remaining.put(row);continue
            }
            val moved=runCatching{
                enqueue(slot,deviceId,callId,decrypt(row.getString("ciphertext")))
            }.isSuccess
            if(moved)completed++ else remaining.put(row)
        }
        check(prefs.edit().putString("inflight",remaining.toString()).commit()){
            "Could not finalize live transcripts"
        }
        return completed
    }

    @Synchronized fun enqueue(slot:Int,deviceId:Int,callId:String,transcript:String) {
        require(deviceId>0){"missing SIM binding identity"}
        require(slot in 0..1){"invalid report SIM slot"}
        require(callId.matches(Regex("[a-f0-9-]{36}"))){"invalid report call ID"}
        if(transcript.length<18)return // no content = no generated report
        val text=transcript.take(12000)
        val queue=JSONArray(prefs.getString("pending","[]")?:"[]")
        for(i in 0 until queue.length()){
            if(queue.optJSONObject(i)?.optString("call_id")==callId &&
                queue.optJSONObject(i)?.optInt("device_id",-1)==deviceId)return
        }
        check(queue.length()<30){"Call report queue full — retry before accepting more"}
        val envelope=JSONObject().put("call_id",callId).put("slot",slot).put("device_id",deviceId)
            .put("queued_at",System.currentTimeMillis())
            .put("ciphertext",encrypt(text))
        queue.put(envelope)
        check(prefs.edit().putString("pending",queue.toString()).commit()){
            "Could not persist encrypted call report"
        }
    }


    /** Legacy queued reports without a trusted binding ID stay encrypted locally,
     * not uploaded to whichever account is now paired to the same SIM slot. */
    @Synchronized fun peekEligible(bindingId:(Int)->Int?):JSONObject? {
        val queue=JSONArray(prefs.getString("pending","[]")?:"[]")
        for(i in 0 until queue.length()){
            val row=queue.optJSONObject(i)?:continue
            val slot=row.optInt("slot",-1)
            val originalId=row.optInt("device_id",-1)
            if(slot !in 0..1 || originalId<=0 || bindingId(slot)!=originalId)continue
            return JSONObject().put("call_id",row.getString("call_id"))
                .put("sim_slot",slot).put("device_id",originalId)
                .put("transcript",decrypt(row.getString("ciphertext")))
        }
        return null
    }

    @Synchronized fun acknowledge(sent:JSONObject) {
        val queue=JSONArray(prefs.getString("pending","[]")?:"[]")
        val rest=JSONArray()
        var removed=false
        for(i in 0 until queue.length()){
            val row=queue.optJSONObject(i)?:continue
            val match=!removed && row.optString("call_id")==sent.optString("call_id") &&
                row.optInt("slot",-1)==sent.optInt("sim_slot",-1) &&
                row.optInt("device_id",-1)==sent.optInt("device_id",-1)
            if(match)removed=true else rest.put(row)
        }
        if(removed)check(prefs.edit().putString("pending",rest.toString()).commit()){
            "Could not acknowledge call report"
        }
    }
}
