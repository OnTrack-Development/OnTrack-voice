package com.ontrack.voiceproof

import android.telephony.TelephonyManager

/** Label the actual Android data radio, not every cellular connection as 4G/5G.
 * Applies to all mobile operators and is deliberately independent of branding.
 * Unknown is not treated as offline: ConnectivityManager determines internet.
 */
internal object CellularRadioStatus {
    fun label(type:Int):String = when(type) {
        TelephonyManager.NETWORK_TYPE_GPRS -> "2G GPRS"
        TelephonyManager.NETWORK_TYPE_EDGE -> "2G EDGE"
        TelephonyManager.NETWORK_TYPE_GSM -> "2G GSM"
        TelephonyManager.NETWORK_TYPE_UMTS,
        TelephonyManager.NETWORK_TYPE_TD_SCDMA -> "3G UMTS"
        TelephonyManager.NETWORK_TYPE_HSDPA -> "3G HSDPA (H)"
        TelephonyManager.NETWORK_TYPE_HSUPA -> "3G HSUPA (H)"
        TelephonyManager.NETWORK_TYPE_HSPA -> "3G HSPA (H)"
        TelephonyManager.NETWORK_TYPE_HSPAP -> "3G HSPA+ (H+)"
        TelephonyManager.NETWORK_TYPE_CDMA,
        TelephonyManager.NETWORK_TYPE_1xRTT -> "2G CDMA"
        TelephonyManager.NETWORK_TYPE_EVDO_0,
        TelephonyManager.NETWORK_TYPE_EVDO_A,
        TelephonyManager.NETWORK_TYPE_EVDO_B,
        TelephonyManager.NETWORK_TYPE_EHRPD -> "3G EVDO"
        TelephonyManager.NETWORK_TYPE_LTE -> "4G LTE"
        TelephonyManager.NETWORK_TYPE_NR -> "5G NR"
        TelephonyManager.NETWORK_TYPE_IWLAN -> "Wi-Fi calling"
        TelephonyManager.NETWORK_TYPE_UNKNOWN -> "شبكة خلوية (الجيل غير معروف)"
        else -> "شبكة خلوية (نوع $type)"
    }

    fun isSlowCellularType(type:Int):Boolean = when(type) {
        TelephonyManager.NETWORK_TYPE_GPRS,
        TelephonyManager.NETWORK_TYPE_EDGE,
        TelephonyManager.NETWORK_TYPE_GSM,
        TelephonyManager.NETWORK_TYPE_UMTS,
        TelephonyManager.NETWORK_TYPE_TD_SCDMA,
        TelephonyManager.NETWORK_TYPE_HSDPA,
        TelephonyManager.NETWORK_TYPE_HSUPA,
        TelephonyManager.NETWORK_TYPE_HSPA,
        TelephonyManager.NETWORK_TYPE_HSPAP,
        TelephonyManager.NETWORK_TYPE_CDMA,
        TelephonyManager.NETWORK_TYPE_1xRTT,
        TelephonyManager.NETWORK_TYPE_EVDO_0,
        TelephonyManager.NETWORK_TYPE_EVDO_A,
        TelephonyManager.NETWORK_TYPE_EVDO_B,
        TelephonyManager.NETWORK_TYPE_EHRPD -> true
        else -> false
    }

    /** Advisory diagnosis only: never block 3G/H+ or bypass account authentication.
     * The mobile radio may temporarily suspend packet data while a voice call
     * is on 3G/2G, even though Android still shows signal bars.
     */
    fun voiceDataHint(dataInternetAvailable:Boolean, simultaneousVoiceData:Boolean?,
                      callSimEqualsDataSim:Boolean):String = when {
        dataInternetAvailable -> ""
        !callSimEqualsDataSim ->
            "بيانات الهاتف على شريحة مختلفة عن شريحة المكالمة؛ قد تتوقف الداتا أثناء اتصال 3G/H+"
        simultaneousVoiceData==false ->
            "الشبكة الحالية لا تدعم الصوت والداتا معًا أثناء المكالمة؛ انتظر رجوع الإنترنت"
        else -> "اتصال البيانات متوقف أثناء مكالمة SIM؛ سيُعاد ربط الإيجنت عند عودته"
    }
}
