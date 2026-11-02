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

    @Synchronized fun enqueue(slot:Int,callId:String,transcript:String) {
        require(slot in 0..1){"invalid report SIM slot"}
        require(callId.matches(Regex("[a-f0-9-]{36}"))){"invalid report call ID"}
        if(transcript.length<18)return // no content = no generated report
        val text=transcript.take(12000)
        val queue=JSONArray(prefs.getString("pending","[]")?:"[]")
        for(i in 0 until queue.length()){
            if(queue.optJSONObject(i)?.optString("call_id")==callId)return
        }
        check(queue.length()<30){"Call report queue full — retry before accepting more"}
        val envelope=JSONObject().put("call_id",callId).put("slot",slot)
            .put("queued_at",System.currentTimeMillis())
            .put("ciphertext",encrypt(text))
        queue.put(envelope)
        check(prefs.edit().putString("pending",queue.toString()).commit()){
            "Could not persist encrypted call report"
        }
    }

    /** Peek decrypts in memory only; callers must not log the returned payload. */
    @Synchronized fun peek():JSONObject? {
        val queue=JSONArray(prefs.getString("pending","[]")?:"[]")
        val row=queue.optJSONObject(0)?:return null
        return JSONObject().put("call_id",row.getString("call_id"))
            .put("sim_slot",row.getInt("slot"))
            .put("transcript",decrypt(row.getString("ciphertext")))
    }

    @Synchronized fun acknowledge(sent:JSONObject) {
        val queue=JSONArray(prefs.getString("pending","[]")?:"[]")
        val row=queue.optJSONObject(0)?:return
        if(row.optString("call_id")!=sent.optString("call_id")||
            row.optInt("slot",-1)!=sent.optInt("sim_slot",-1))return
        val rest=JSONArray()
        for(i in 1 until queue.length())rest.put(queue.get(i))
        check(prefs.edit().putString("pending",rest.toString()).commit()){
            "Could not acknowledge call report"
        }
    }
}
