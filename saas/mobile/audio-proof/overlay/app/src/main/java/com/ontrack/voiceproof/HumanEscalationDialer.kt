package com.ontrack.voiceproof

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Bundle
import android.os.Build
import android.telecom.PhoneAccountHandle
import android.telecom.TelecomManager
import android.telephony.SubscriptionManager
import android.telephony.TelephonyManager
import androidx.core.content.ContextCompat

internal class PreDialFailureException(message:String, cause:Throwable?=null):IllegalStateException(message, cause)

/** Owner-authorized follow-up only, never a public/prompt-supplied dialer.
 * All numbers originate from a server-selected active staff contact belonging
 * to the same company/agent/device; no untrusted transcript can change them.
 */
internal object HumanEscalationDialer {
    private val internationalPhone=Regex("\\+[1-9][0-9]{7,14}")

    internal fun selectPhoneAccountIndex(
        capableSubIds: List<Int?>,
        capableHandleIds: List<String>,
        targetSubscriptionId: Int,
        activeSimCount: Int
    ): Int? {
        if (capableSubIds.isEmpty()) return null
        val subMatches = capableSubIds.indices.filter { capableSubIds[it] == targetSubscriptionId }
        if (subMatches.isNotEmpty()) return subMatches.first()
        val idStr = targetSubscriptionId.toString()
        val idMatches = capableHandleIds.indices.filter { idx ->
            val h = capableHandleIds[idx].trim()
            h == idStr || h.startsWith("$idStr:") || h.endsWith(":$idStr")
        }
        if (idMatches.isNotEmpty()) return idMatches.first()
        if (capableSubIds.size == 1 && activeSimCount <= 1) return 0
        return null
    }

    fun placeOnPairedSim(context:Context,slot:Int,phone:String) {
        if(slot !in 0..1) throw PreDialFailureException("Invalid SIM slot")
        if(!internationalPhone.matches(phone)) throw PreDialFailureException("Invalid phone format")
        if(ContextCompat.checkSelfPermission(context,Manifest.permission.CALL_PHONE)!=
            PackageManager.PERMISSION_GRANTED) {
            throw PreDialFailureException("اسمح بإذن الاتصال (CALL_PHONE) من إعدادات Android لتشغيل المكالمات الصادرة")
        }
        if(ContextCompat.checkSelfPermission(context,Manifest.permission.READ_PHONE_STATE)!=
            PackageManager.PERMISSION_GRANTED) {
            throw PreDialFailureException("تعذر التأكد من الشريحة المربوطة (READ_PHONE_STATE)")
        }
        val subscriptionId = try {
            TenantSimHardware.subscriptionId(context, slot)
        } catch (e: Exception) {
            throw PreDialFailureException(e.message ?: "تعذر قراءة معرّف الشريحة", e)
        }
        val telecom=context.getSystemService(TelecomManager::class.java)
            ?: throw PreDialFailureException("خدمة الاتصال غير متاحة على الهاتف")
        val capable=telecom.callCapablePhoneAccounts
        if(capable.isEmpty()) throw PreDialFailureException("لم يتم العثور على شريحة صالحة للمكالمات")
        val telephony=context.getSystemService(TelephonyManager::class.java)
            ?: throw PreDialFailureException("تعذر فحص حساب الشريحة")
        val subMgr=context.getSystemService(SubscriptionManager::class.java)
        val activeSimCount=runCatching{ subMgr?.activeSubscriptionInfoList?.size ?: 1 }.getOrDefault(1)
        val capableSubIds=capable.map{ handle ->
            if(Build.VERSION.SDK_INT>=30) runCatching{ telephony.getSubscriptionId(handle) }.getOrNull() else null
        }
        val capableHandleIds=capable.map{ it.id }
        val chosenIdx=selectPhoneAccountIndex(capableSubIds, capableHandleIds, subscriptionId, activeSimCount)
            ?: throw PreDialFailureException("لم يثبت تطابق شريحة الاتصال (SIM ${slot + 1}) مع حساب Android؛ راجع شريحة الاتصال الافتراضية")
        val chosen=capable[chosenIdx]
        if(!telecom.isOutgoingCallPermitted(chosen)){
            throw PreDialFailureException("خط الهاتف مشغول أو Android لا يسمح بمكالمة أخرى الآن")
        }
        val extras=Bundle().apply{
            putParcelable(TelecomManager.EXTRA_PHONE_ACCOUNT_HANDLE,chosen)
        }
        try {
            telecom.placeCall(Uri.fromParts("tel",phone,null),extras)
        } catch (e: Exception) {
            throw PreDialFailureException("فشل فتح المكالمة من Android: " + (e.message ?: e.javaClass.simpleName), e)
        }
    }
}
