package com.ontrack.voiceproof

import android.content.Context
import android.telephony.TelephonyManager

/**
 * Fail closed when a requested SIM slot is absent, locked or unverifiable.
 * SIM_STATE_READY is a hardware readiness signal, NOT an ICCID attestation,
 * proof of carrier identity, permission to dial, or voice-path E2E.
 */
internal object TenantSimHardware {
    fun problemForState(state: Int): String? = when (state) {
        TelephonyManager.SIM_STATE_READY -> null
        TelephonyManager.SIM_STATE_ABSENT -> "لا توجد شريحة في هذا المنفذ"
        TelephonyManager.SIM_STATE_PIN_REQUIRED,
        TelephonyManager.SIM_STATE_PUK_REQUIRED,
        TelephonyManager.SIM_STATE_NETWORK_LOCKED -> "الشريحة مقفلة؛ افتحها من إعدادات Android"
        else -> "حالة الشريحة غير مؤكدة؛ تحقق من تفعيلها على الهاتف"
    }

    fun problem(context: Context, slot: Int): String? {
        TenantPairingContract.slot(slot)
        val telephony = context.getSystemService(TelephonyManager::class.java)
            ?: return "خدمة الشرائح غير متاحة على هذا الجهاز"
        return try {
            problemForState(telephony.getSimState(slot))
        } catch (_: SecurityException) {
            "Android منع التحقق من الشريحة؛ الربط متوقف"
        } catch (_: RuntimeException) {
            "تعذر قراءة حالة الشريحة؛ الربط متوقف"
        }
    }

    fun requireReady(context: Context, slot: Int) {
        val issue = problem(context, slot)
        check(issue == null) { issue ?: "الشريحة غير جاهزة" }
    }
}
