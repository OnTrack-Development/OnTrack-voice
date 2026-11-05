package com.ontrack.voiceproof

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Bundle
import android.telecom.PhoneAccountHandle
import android.telecom.TelecomManager
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
        // Android does not provide a universally portable public API to map
        // arbitrary PhoneAccountHandle IDs to SIM slots. When more than one
        // handle exists, NEVER guess and accidentally dial via another tenant.
        val matching:List<PhoneAccountHandle>=capable.filter{
            it.id.trim()==subscriptionId.toString()
        }
        val chosen=if(matching.size==1)matching.single()
            else if(capable.size==1)capable.single()
            else error("تعذر تحديد حساب الاتصال للشريحة بشكل مؤكد؛ أوقفنا الاتصال حمايةً لعزل الشركات")
        val extras=Bundle().apply{
            putParcelable(TelecomManager.EXTRA_PHONE_ACCOUNT_HANDLE,chosen)
        }
        telecom.placeCall(Uri.fromParts("tel",phone,null),extras)
    }
}
