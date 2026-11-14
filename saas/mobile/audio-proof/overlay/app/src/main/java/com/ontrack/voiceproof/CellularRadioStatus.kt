package com.ontrack.voiceproof

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import android.telephony.SubscriptionManager
import android.telephony.TelephonyManager
import okhttp3.OkHttpClient

/** Label the actual Android data radio, not every cellular connection as 4G/5G.
 * Applies to all mobile operators and is deliberately independent of branding.
 * Unknown is not treated as offline: ConnectivityManager determines internet.
 * All TelephonyManager / ConnectivityManager reads are wrapped to guarantee
 * zero uncaught SecurityException or RuntimeException on Android 11+.
 */
internal object CellularRadioStatus {
    fun activeNetworkCapabilities(context: Context): NetworkCapabilities? = runCatching {
        val cm = context.getSystemService(ConnectivityManager::class.java) ?: return@runCatching null
        val network = cm.activeNetwork ?: return@runCatching null
        cm.getNetworkCapabilities(network)
    }.getOrNull()

    fun activeNetworkCapabilities(cm: ConnectivityManager?): NetworkCapabilities? = runCatching {
        val network = cm?.activeNetwork ?: return@runCatching null
        cm.getNetworkCapabilities(network)
    }.getOrNull()

    fun isCellularLink(context: Context): Boolean = runCatching {
        val capabilities = activeNetworkCapabilities(context) ?: return@runCatching false
        capabilities.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) &&
            !capabilities.hasTransport(NetworkCapabilities.TRANSPORT_WIFI)
    }.getOrDefault(false)

    @Suppress("DEPRECATION")
    fun safeDataNetworkType(telephony: TelephonyManager?, subscriptionId: Int = -1): Int = runCatching {
        if (telephony == null) return@runCatching TelephonyManager.NETWORK_TYPE_UNKNOWN
        val target = if (subscriptionId >= 0) {
            runCatching { telephony.createForSubscriptionId(subscriptionId) }.getOrNull() ?: telephony
        } else {
            telephony
        }
        val dataType = runCatching { target.dataNetworkType }.getOrNull()
        if (dataType != null && dataType != TelephonyManager.NETWORK_TYPE_UNKNOWN) {
            dataType
        } else {
            runCatching { target.networkType }.getOrDefault(TelephonyManager.NETWORK_TYPE_UNKNOWN)
        }
    }.getOrDefault(TelephonyManager.NETWORK_TYPE_UNKNOWN)

    fun safeDataNetworkType(context: Context): Int = runCatching {
        val sub = runCatching { SubscriptionManager.getDefaultDataSubscriptionId() }.getOrDefault(-1)
        val tm = context.getSystemService(TelephonyManager::class.java)
        safeDataNetworkType(tm, sub)
    }.getOrDefault(TelephonyManager.NETWORK_TYPE_UNKNOWN)

    fun isSlowCellularLink(context: Context): Boolean = runCatching {
        isCellularLink(context) && isSlowCellularType(safeDataNetworkType(context))
    }.getOrDefault(false)

    fun safeDataRadioLabel(context: Context): String = runCatching {
        label(safeDataNetworkType(context))
    }.getOrDefault("شبكة خلوية (الجيل غير معروف)")

    fun safeDataRadioLabel(telephony: TelephonyManager?, subscriptionId: Int = -1): String = runCatching {
        label(safeDataNetworkType(telephony, subscriptionId))
    }.getOrDefault("شبكة خلوية (الجيل غير معروف)")

    fun safeConcurrentVoiceAndData(context: Context, subscriptionId: Int = -1): Boolean? = runCatching {
        safeConcurrentVoiceAndData(context.getSystemService(TelephonyManager::class.java), subscriptionId)
    }.getOrNull()

    fun safeConcurrentVoiceAndData(telephony: TelephonyManager?, subscriptionId: Int = -1): Boolean? = runCatching {
        if (telephony == null) return@runCatching null
        val voice = if (subscriptionId >= 0) {
            runCatching { telephony.createForSubscriptionId(subscriptionId) }.getOrNull() ?: telephony
        } else {
            telephony
        }
        voice.isConcurrentVoiceAndDataSupported
    }.getOrNull()

    fun safeInternetRoute(context: Context): String = runCatching {
        val caps = activeNetworkCapabilities(context)
        when {
            caps == null -> "offline"
            caps.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) -> "Wi-Fi"
            caps.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) -> safeDataRadioLabel(context)
            else -> "network"
        }
    }.getOrDefault("unknown")

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

    fun getActiveCellularNetwork(context: Context): Network? = runCatching {
        val cm = context.getSystemService(ConnectivityManager::class.java) ?: return@runCatching null
        val active = cm.activeNetwork
        if (active != null) {
            val caps = cm.getNetworkCapabilities(active)
            if (caps != null && caps.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) &&
                caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)) {
                return@runCatching active
            }
        }
        for (network in cm.allNetworks) {
            val caps = cm.getNetworkCapabilities(network) ?: continue
            if (caps.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) &&
                caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)) {
                return@runCatching network
            }
        }
        null
    }.getOrNull()

    fun isWifiConnected(context: Context): Boolean = runCatching {
        val caps = activeNetworkCapabilities(context) ?: return@runCatching false
        caps.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) &&
            caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
    }.getOrDefault(false)

    fun bindOkHttpClientToCellularIfNeeded(context: Context, baseClient: OkHttpClient): OkHttpClient {
        return runCatching {
            if (isWifiConnected(context)) return@runCatching baseClient
            val cellNet = getActiveCellularNetwork(context) ?: return@runCatching baseClient
            baseClient.newBuilder().socketFactory(cellNet.socketFactory).build()
        }.getOrDefault(baseClient)
    }
}
