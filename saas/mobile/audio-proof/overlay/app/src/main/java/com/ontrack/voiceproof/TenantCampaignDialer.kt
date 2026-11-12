package com.ontrack.voiceproof

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.provider.CallLog
import androidx.core.content.ContextCompat

/** Consent-gated campaign dialing. The server chooses the phone; the device
 * selects only its paired SIM. Neither Gemini nor a transcript chooses a phone.
 */
internal object TenantCampaignDialer {
    private val international=Regex("^\\+[1-9][0-9]{7,14}$")
    private val egyptian=Regex("^01[0-9]{9}$")
    private val digits=Regex("^[0-9]+$")

    fun shouldDefaultOptIn(
        paired:Boolean,
        hasCallPhone:Boolean,
        hasReadPhone:Boolean,
        agentEnabled:Boolean,
        userExplicitlyDisabled:Boolean,
    ):Boolean = paired && hasCallPhone && hasReadPhone && agentEnabled && !userExplicitlyDisabled

    fun e164(raw:String):String {
        val value=raw.trim().replace(" ","").replace("-","")
        val normal=when {
            international.matches(value)->value
            egyptian.matches(value)->"+20"+value.substring(1)
            value.startsWith("0020") && digits.matches(value)->"+"+value.substring(2)
            value.startsWith("20") && digits.matches(value)->"+"+value
            else->throw IllegalArgumentException("رقم الحملة ليس E.164 أو مصريًا صالحًا")
        }
        require(international.matches(normal)){"صيغة الرقم غير صالحة للمكالمة"}
        return normal
    }
    fun place(context:Context,slot:Int,serverPhone:String):String {
        val phone=e164(serverPhone)
        HumanEscalationDialer.placeOnPairedSim(context,slot,phone)
        return phone
    }
    /** Android call-log evidence, never equate Telecom.placeCall() to a
     * connected call. Without a matching outgoing log the result is UNKNOWN.
     * A completed carrier log with duration >0 is the strongest local proof.
     */
    fun finishResult(context:Context,phone:String,placedAtMs:Long):String {
        if(ContextCompat.checkSelfPermission(context,Manifest.permission.READ_CALL_LOG)!=
                PackageManager.PERMISSION_GRANTED)return "unknown"
        val columns=arrayOf(CallLog.Calls.NUMBER,CallLog.Calls.DATE,
            CallLog.Calls.DURATION,CallLog.Calls.TYPE)
        return runCatching {
            context.contentResolver.query(CallLog.Calls.CONTENT_URI,columns,
                CallLog.Calls.DATE+">=?",
                arrayOf((placedAtMs-15000L).coerceAtLeast(0).toString()),
                CallLog.Calls.DATE+" DESC")?.use { cursor ->
                val numberIndex=cursor.getColumnIndexOrThrow(CallLog.Calls.NUMBER)
                val durationIndex=cursor.getColumnIndexOrThrow(CallLog.Calls.DURATION)
                val typeIndex=cursor.getColumnIndexOrThrow(CallLog.Calls.TYPE)
                var scanned=0
                while(cursor.moveToNext()&&scanned++<20){
                    if(cursor.getInt(typeIndex)!=CallLog.Calls.OUTGOING_TYPE)continue
                    val logged=runCatching{e164(cursor.getString(numberIndex).orEmpty())}.getOrNull()
                    if(logged!=phone)continue
                    val duration=cursor.getLong(durationIndex)
                    return@use if(duration>0L)"completed" else "no_answer"
                }
                "unknown"
            }?:"unknown"
        }.getOrDefault("unknown")
    }
}
