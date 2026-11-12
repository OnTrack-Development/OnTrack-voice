package com.ontrack.voiceproof

import android.telephony.TelephonyManager
import org.junit.Assert.*
import org.junit.Test

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
}
