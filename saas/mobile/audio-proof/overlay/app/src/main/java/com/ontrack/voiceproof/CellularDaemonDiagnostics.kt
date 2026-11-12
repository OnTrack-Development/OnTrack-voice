package com.ontrack.voiceproof

/** Diagnoses why the local ADB/PCM bridge is unavailable, especially on 4G/5G without Wi-Fi. */
internal object CellularDaemonDiagnostics {
    fun diagnose(
        daemonReady: Boolean,
        setupComplete: Boolean,
        onWifi: Boolean,
        wifiFreeReady: Boolean,
        wifiRefused: Boolean = false
    ): String {
        if (daemonReady) return ""
        return when {
            !setupComplete -> "يلزم إكمال اقتران ADB لمرة واحدة من فحص وتجهيز الهاتف"
            !onWifi && !wifiFreeReady -> "على الداتا فقط وميزة التشغيل بدون Wi-Fi (5555) غير مفعلة — اتصل بالـ Wi-Fi وفعّلها مرة واحدة"
            !onWifi && wifiFreeReady -> "منفذ 5555 مفعّل لكن محرك الصوت متوقف — تأكد من USB Debugging (وفي شاومي: USB debugging Security settings) ثم اضغط إعادة تنشيط"
            onWifi && wifiRefused -> "النظام رفض Wireless Debugging على شبكة Wi-Fi الحالية — اسمح لها من خيارات المطوّر"
            else -> "محرك الصوت المحلي متوقف — افتح فحص وتجهيز الهاتف أو اضغط إعادة تنشيط محرك الصوت"
        }
    }
}
