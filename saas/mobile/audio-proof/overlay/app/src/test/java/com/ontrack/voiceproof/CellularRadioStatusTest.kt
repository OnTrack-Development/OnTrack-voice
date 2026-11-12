package com.ontrack.voiceproof

import android.content.Context
import android.net.ConnectivityManager
import android.telephony.TelephonyManager
import org.junit.Assert.*
import org.junit.Test
import org.mockito.Mockito.`when`
import org.mockito.Mockito.mock

class CellularRadioStatusTest {
    @Test fun recognizesAllEgyptianMobileRadioGenerationsWithoutCallingHPlusLte(){
        assertEquals("3G UMTS", CellularRadioStatus.label(TelephonyManager.NETWORK_TYPE_UMTS))
        assertEquals("3G HSDPA (H)", CellularRadioStatus.label(TelephonyManager.NETWORK_TYPE_HSDPA))
        assertEquals("3G HSUPA (H)", CellularRadioStatus.label(TelephonyManager.NETWORK_TYPE_HSUPA))
        assertEquals("3G HSPA (H)", CellularRadioStatus.label(TelephonyManager.NETWORK_TYPE_HSPA))
        assertEquals("3G HSPA+ (H+)", CellularRadioStatus.label(TelephonyManager.NETWORK_TYPE_HSPAP))
        assertEquals("2G EDGE", CellularRadioStatus.label(TelephonyManager.NETWORK_TYPE_EDGE))
        assertEquals("2G GPRS", CellularRadioStatus.label(TelephonyManager.NETWORK_TYPE_GPRS))
        assertEquals("4G LTE", CellularRadioStatus.label(TelephonyManager.NETWORK_TYPE_LTE))
        assertEquals("5G NR", CellularRadioStatus.label(TelephonyManager.NETWORK_TYPE_NR))
        assertTrue(CellularRadioStatus.label(TelephonyManager.NETWORK_TYPE_UNKNOWN).contains("غير معروف"))
    }
    @Test fun voiceDataDiagnosisDoesNotMistakeSignalBarsForInternet(){
        assertEquals("",CellularRadioStatus.voiceDataHint(true,false,true))
        assertTrue(CellularRadioStatus.voiceDataHint(false,null,false).contains("شريحة مختلفة"))
        assertTrue(CellularRadioStatus.voiceDataHint(false,false,true).contains("لا تدعم الصوت والداتا"))
        assertTrue(CellularRadioStatus.voiceDataHint(false,null,true).contains("متوقف"))
    }
    @Test fun securityExceptionsFromTelephonyOrConnectivityNeverCrash() {
        val tm = mock(TelephonyManager::class.java)
        `when`(tm.dataNetworkType).thenThrow(SecurityException("READ_PHONE_STATE missing on Android 11+"))
        `when`(tm.networkType).thenThrow(SecurityException("networkType restricted"))
        `when`(tm.createForSubscriptionId(7)).thenThrow(SecurityException("sub denied"))
        `when`(tm.isConcurrentVoiceAndDataSupported).thenThrow(SecurityException("concurrent check denied"))

        assertEquals(TelephonyManager.NETWORK_TYPE_UNKNOWN, CellularRadioStatus.safeDataNetworkType(tm, -1))
        assertEquals(TelephonyManager.NETWORK_TYPE_UNKNOWN, CellularRadioStatus.safeDataNetworkType(tm, 7))

        val ctx = mock(Context::class.java)
        val cm = mock(ConnectivityManager::class.java)
        `when`(ctx.getSystemService(TelephonyManager::class.java)).thenReturn(tm)
        `when`(ctx.getSystemService(ConnectivityManager::class.java)).thenReturn(cm)
        `when`(cm.activeNetwork).thenThrow(SecurityException("network state restricted"))

        assertFalse(CellularRadioStatus.isCellularLink(ctx))
        assertFalse(CellularRadioStatus.isSlowCellularLink(ctx))
        assertEquals(TelephonyManager.NETWORK_TYPE_UNKNOWN, CellularRadioStatus.safeDataNetworkType(ctx))
        assertTrue(CellularRadioStatus.safeDataRadioLabel(ctx).contains("شبكة خلوية"))
        assertNull(CellularRadioStatus.safeConcurrentVoiceAndData(ctx, 7))
        assertTrue(CellularRadioStatus.safeInternetRoute(ctx).isNotBlank())
    }
    @Test fun fallbackToLegacyNetworkTypeWhenDataNetworkTypeThrows() {
        val tm = mock(TelephonyManager::class.java)
        `when`(tm.dataNetworkType).thenThrow(SecurityException("dataNetworkType restricted"))
        `when`(tm.networkType).thenReturn(TelephonyManager.NETWORK_TYPE_LTE)

        assertEquals(TelephonyManager.NETWORK_TYPE_LTE, CellularRadioStatus.safeDataNetworkType(tm, -1))
    }
}
