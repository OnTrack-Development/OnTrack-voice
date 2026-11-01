package com.ontrack.voiceproof

import android.Manifest
import android.app.Service
import android.content.Context
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Bundle
import android.telecom.TelecomManager
import androidx.core.content.ContextCompat
import org.json.JSONObject

/**
 * Permission-gated SIM campaign executor. Never re-dials an ambiguous number.
 * Requires owner opt-in AND explicitly armed campaign AND DNC verification.
 * It only runs inside the existing foreground phone-link service.
 */
internal class CampaignDialer(
    private val host: Service,
    private val api: (JSONObject)->JSONObject,
    private val notice:(String)->Unit
) {
    private val prefs=host.getSharedPreferences("ontrack_link",Context.MODE_PRIVATE)
    private var lastPoll=0L
    private var pendingSince=prefs.getLong("campaign_pending_at",0L)
    private var pendingAttempt=prefs.getString("campaign_pending_attempt","").orEmpty()
    private var sawActive=prefs.getBoolean("campaign_saw_active",false)

    private fun finish(result:String) {
        if(pendingAttempt.isEmpty())return
        api(JSONObject().put("action","finish").put("confirmed",true)
            .put("attempt_id",pendingAttempt).put("result",result))
        pendingAttempt="";pendingSince=0;sawActive=false
        prefs.edit().remove("campaign_pending_attempt").remove("campaign_pending_at")
            .remove("campaign_saw_active").remove("campaign_contact_name")
            .remove("campaign_contact_phone").remove("campaign_contact_id")
            .remove("campaign_id").remove("campaign_scenario").commit()
    }

    fun tick(callState:Int,agentEnabled:Boolean) {
        // No pilot claim, report or dial while either SIM belongs to a tenant.
        if(MobileLinkService.tenantPairingInProgress || TenantDeviceEnrollment(host).hasAny())return
        if(pendingAttempt.isNotEmpty()) {
            if(callState==2 && !sawActive) {
                sawActive=true
                prefs.edit().putBoolean("campaign_saw_active",true).commit()
            }
            if(callState==0 && sawActive) {
                finish("completed")
            } else if(callState==0 && pendingSince>0 && System.currentTimeMillis()-pendingSince>120000L) {
                // Could be a blocked call or a crash; never assume it is safe to redial.
                finish("unknown")
            }
            return
        }
        if(callState!=0||!agentEnabled||!prefs.getBoolean("campaign_opt_in",false))return
        if(ContextCompat.checkSelfPermission(host,Manifest.permission.CALL_PHONE)!=PackageManager.PERMISSION_GRANTED)return
        val now=System.currentTimeMillis()
        if(now-lastPoll<10000L)return
        lastPoll=now
        val payload=api(JSONObject().put("action","claim").put("confirmed",true))
        val claim=payload.optJSONObject("claim")?:return
        val attempt=claim.optString("id")
        val number=claim.optString("phone")
        if(!attempt.matches(Regex("[a-f0-9]{32}")) ||
            !number.matches(Regex("\\+[1-9][0-9]{7,14}")))error("Invalid leased call")
        pendingAttempt=attempt;pendingSince=now;sawActive=false
        check(prefs.edit().putString("campaign_pending_attempt",attempt)
            .putLong("campaign_pending_at",now)
            .putBoolean("campaign_saw_active",false)
            .putString("campaign_id",claim.optString("campaign_id"))
            .putString("campaign_contact_id",claim.optString("contact_id"))
            .putString("campaign_contact_name",claim.optString("name"))
            .putString("campaign_contact_phone",number)
            .putString("campaign_scenario",claim.optString("scenario").take(8000)).commit()){"Cannot persist leased call"}
        try {
            val verify=api(JSONObject().put("action","verify").put("confirmed",true)
                .put("attempt_id",attempt)).getJSONObject("verification")
            if(!verify.optBoolean("allowed",false)) {
                notice("تم استبعاد الرقم بعد مراجعة الحظر")
                finish("failed");return
            }
            if(!prefs.getBoolean("campaign_opt_in",false) ||
                ContextCompat.checkSelfPermission(host,Manifest.permission.CALL_PHONE)!=PackageManager.PERMISSION_GRANTED)
                error("Owner opted out")
            // Recheck immediately before Android placeCall: enrollment can start
            // while the network verify request is in flight.
            check(!MobileLinkService.tenantPairingInProgress &&
                !TenantDeviceEnrollment(host).hasAny()){"Tenant SIM enrollment took ownership"}
            val telecom=host.getSystemService(TelecomManager::class.java)
                ?:error("Telecom manager unavailable")
            telecom.placeCall(Uri.fromParts("tel",number,null),Bundle())
            notice("تم طلب الاتصال من الشريحة؛ في انتظار حالة المكالمة")
        } catch(e:Exception) {
            // A platform error may be ambiguous, so record it without a retry.
            runCatching{finish("unknown")}
            notice("لم يتم تأكيد الاتصال من Android؛ لن نكرر المحاولة تلقائيًا")
        }
    }
}
