package com.ontrack.voiceproof

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Bundle
import android.os.Build
import android.telecom.PhoneAccountHandle
import android.telecom.TelecomManager
import android.telephony.TelephonyManager
import androidx.core.content.ContextCompat

/** Owner-authorized follow-up only, never a public/prompt-supplied dialer.
 * All numbers originate from a server-selected active staff contact belonging
 * to the same company/agent/device; no untrusted transcript can change them.
 */
internal object HumanEscalationDialer {
    private val internationalPhone=Regex("\\+[1-9][0-9]{7,14}")

    fun placeOnPairedSim(context:Context,slot:Int,phone:String) {
        require(slot in 0..1){"Invalid manager call SIM"}
        require(internationalPhone.matches(phone)){"Invalid configured staff phone"}
        check(ContextCompat.checkSelfPermission(context,Manifest.permission.CALL_PHONE)==
            PackageManager.PERMISSION_GRANTED) {
            "اسمح بإذن الاتصال من Android لإبلاغ المدير بعد المكالمة"
        }
        check(ContextCompat.checkSelfPermission(context,Manifest.permission.READ_PHONE_STATE)==
            PackageManager.PERMISSION_GRANTED) {
            "تعذر التأكد من الشريحة المربوطة"
        }
        val subscriptionId=TenantSimHardware.subscriptionId(context,slot)
        val telecom=context.getSystemService(TelecomManager::class.java)
            ?:error("خدمة الاتصال غير متاحة على الهاتف")
        val capable=telecom.callCapablePhoneAccounts
        check(capable.isNotEmpty()){"لم يتم العثور على شريحة صالحة للمكالمات"}
        // Official Android API 30+: resolve each outgoing PhoneAccountHandle to
        // its real telephony subscription ID, rather than guessing from a
        // vendor-specific handle.id string or SIM index.
        val telephony=context.getSystemService(TelephonyManager::class.java)
            ?:error("تعذر فحص حساب الشريحة")
        val matching:List<PhoneAccountHandle>=capable.filter{handle->
            if(Build.VERSION.SDK_INT>=30)
                runCatching{telephony.getSubscriptionId(handle)==subscriptionId}
                    .getOrDefault(false)
            else handle.id.trim()==subscriptionId.toString()
        }
        val chosen=matching.singleOrNull()
            ?:error("لم يثبت تطابق شريحة الاتصال مع حساب Android؛ تم إيقاف محاولة الاتصال")
        check(telecom.isOutgoingCallPermitted(chosen)){
            "خط الهاتف مشغول أو Android لا يسمح بمكالمة أخرى الآن"
        }
        val extras=Bundle().apply{
            putParcelable(TelecomManager.EXTRA_PHONE_ACCOUNT_HANDLE,chosen)
        }
        telecom.placeCall(Uri.fromParts("tel",phone,null),extras)
    }
}
