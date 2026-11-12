package com.ontrack.voiceproof

import android.content.Context
import android.Manifest
import android.content.pm.PackageManager
import android.telephony.SubscriptionManager
import androidx.core.content.ContextCompat
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

    /**
     * Android subscriptionId is a local, best-effort change detector, NOT a
     * carrier identity, ICCID, proof of SIM ownership, or stable hardware ID.
     * It may change after reboot/eSIM reconfiguration; fail closed and re-pair.
     * No phone number, IMSI or ICCID is read, persisted or transmitted.
     */
    fun subscriptionId(context: Context, slot: Int): Int {
        requireReady(context, slot)
        check(ContextCompat.checkSelfPermission(context, Manifest.permission.READ_PHONE_STATE)
            == PackageManager.PERMISSION_GRANTED) {
            "اسمح بالتحقق من الشريحة (حالة الهاتف) قبل الربط أو التحقق"
        }
        val manager = context.getSystemService(SubscriptionManager::class.java)
            ?: error("تعذر قراءة الاشتراك النشط؛ الربط متوقف")
        val subscription = try {
            manager.getActiveSubscriptionInfoForSimSlotIndex(slot)
        } catch (_: SecurityException) {
            error("Android منع قراءة الاشتراك النشط؛ الربط متوقف")
        } catch (_: RuntimeException) {
            error("تعذر التحقق من الاشتراك النشط؛ الربط متوقف")
        }
        check(subscription?.simSlotIndex == slot) { "الاشتراك لا يطابق منفذ الشريحة؛ الربط متوقف" }
        val id = subscription?.subscriptionId ?: SubscriptionManager.INVALID_SUBSCRIPTION_ID
        check(id >= 0) { "الاشتراك النشط غير مؤكد؛ الربط متوقف" }
        return id
    }

    fun subscriptionProblem(saved: Int?, current: Int): String? = when {
        saved == null || saved < 0 -> "الربط القديم بلا بصمة اشتراك؛ افصل الشريحة وأعد ربطها"
        current < 0 -> "الاشتراك النشط غير مؤكد؛ الربط متوقف"
        saved != current -> "تغيّر اشتراك الشريحة؛ افصلها وأعد ربط الحساب بعد التحقق"
        else -> null
    }

    fun requireReady(context: Context, slot: Int) {
        val issue = problem(context, slot)
        check(issue == null) { issue ?: "الشريحة غير جاهزة" }
    }
}
