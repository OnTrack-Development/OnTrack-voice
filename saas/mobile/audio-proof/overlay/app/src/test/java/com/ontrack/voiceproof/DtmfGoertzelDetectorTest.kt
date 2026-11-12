package com.ontrack.voiceproof

import org.json.JSONArray
import org.json.JSONObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.math.PI
import kotlin.math.sin

class DtmfGoertzelDetectorTest {
    private fun synthesizePcm16(
        sampleRate: Int = 16000,
        durationMs: Int,
        generator: (tSec: Double) -> Double,
    ): ByteArray {
        val count = (sampleRate * durationMs) / 1000
        val out = ByteArray(count * 2)
        for (i in 0 until count) {
            val v = (generator(i.toDouble() / sampleRate) * 32767.0).toInt().coerceIn(-32768, 32767)
            out[i * 2] = (v and 0xFF).toByte()
            out[i * 2 + 1] = ((v ushr 8) and 0xFF).toByte()
        }
        return out
    }

    @Test
    fun detectsAllStandardKeypadDigitsAt16kHz() {
        val tones = mapOf(
            '1' to (697.0 to 1209.0),
            '2' to (697.0 to 1336.0),
            '3' to (697.0 to 1477.0),
            '4' to (770.0 to 1209.0),
            '5' to (770.0 to 1336.0),
            '6' to (770.0 to 1477.0),
            '7' to (852.0 to 1209.0),
            '8' to (852.0 to 1336.0),
            '9' to (852.0 to 1477.0),
            '*' to (941.0 to 1209.0),
            '0' to (941.0 to 1336.0),
            '#' to (941.0 to 1477.0),
        )

        val detector = DtmfGoertzelDetector()
        var nowMs = 1000L
        for ((expectedDigit, freqs) in tones) {
            val (fRow, fCol) = freqs
            val tonePcm = synthesizePcm16(durationMs = 80) { t ->
                0.30 * sin(2.0 * PI * fRow * t) + 0.30 * sin(2.0 * PI * fCol * t)
            }
            // Feed in 20ms chunks like carrier audio packets
            var detected: Char? = null
            for (pos in tonePcm.indices step 640) {
                val chunk = tonePcm.copyOfRange(pos, minOf(pos + 640, tonePcm.size))
                val d = detector.feedPcm16(chunk, nowMs)
                if (d != null) detected = d
                nowMs += 20L
            }
            assertEquals("Expected DTMF digit $expectedDigit", expectedDigit, detected)

            // Feed 60ms silence gap between presses
            val silence = ByteArray(16000 * 2 * 60 / 1000)
            for (pos in silence.indices step 640) {
                assertNull(detector.feedPcm16(silence.copyOfRange(pos, minOf(pos + 640, silence.size)), nowMs))
                nowMs += 20L
            }
        }
    }

    @Test
    fun debouncesSingleLongTonePressToOneEvent() {
        val detector = DtmfGoertzelDetector()
        val tone = synthesizePcm16(durationMs = 200) { t ->
            0.35 * sin(2.0 * PI * 697.0 * t) + 0.35 * sin(2.0 * PI * 1209.0 * t)
        }
        val hits = mutableListOf<Char>()
        var now = 5000L
        for (pos in tone.indices step 640) {
            detector.feedPcm16(tone.copyOfRange(pos, minOf(pos + 640, tone.size)), now)?.let { hits.add(it) }
            now += 20L
        }
        assertEquals(listOf('1'), hits)
    }

    @Test
    fun ignoresSilenceSingleToneAndSpeechHarmonics() {
        val detector = DtmfGoertzelDetector()
        val silence = ByteArray(3200)
        assertNull(detector.feedPcm16(silence, 100L))

        val singleTone = synthesizePcm16(durationMs = 120) { t -> 0.45 * sin(2.0 * PI * 697.0 * t) }
        assertNull(detector.feedPcm16(singleTone, 300L))

        val speechLike = synthesizePcm16(durationMs = 120) { t ->
            0.22 * sin(2.0 * PI * 180.0 * t) +
                0.20 * sin(2.0 * PI * 360.0 * t) +
                0.20 * sin(2.0 * PI * 770.0 * t) +
                0.18 * sin(2.0 * PI * 1540.0 * t)
        }
        assertNull(detector.feedPcm16(speechLike, 500L))
    }

    @Test
    fun resolvesIvrRouteAndBuildsClientContentPromptWithTransferPhone() {
        val routes = listOf(
            IvrRouteMatch(digit = "1", label = "المبيعات", departmentPrompt = "ساعد في العروض", transferPhone = ""),
            IvrRouteMatch(digit = "2", label = "الإدارة", departmentPrompt = "تحويل فوري للمدير", transferPhone = "+201099887766"),
        )

        val m1 = IvrDtmfRouter.findRoute(routes, "1")
        assertNotNull(m1)
        val p1 = IvrDtmfRouter.buildClientContentPrompt("1", m1)
        assertTrue(p1.contains("[حدث نظام IVR: ضغط المتصل على الزر رقم (1) - انتقل فوراً إلى قسم: المبيعات ونفّذ تعليماته]"))
        assertTrue(p1.contains("ساعد في العروض"))

        val m2 = IvrDtmfRouter.findRoute(routes, "2")
        assertNotNull(m2)
        assertEquals("+201099887766", m2!!.transferPhone)
        val p2 = IvrDtmfRouter.buildClientContentPrompt("2", m2)
        assertTrue(p2.contains("[حدث نظام IVR: ضغط المتصل على الزر رقم (2) - انتقل فوراً إلى قسم: الإدارة ونفّذ تعليماته]"))
        assertTrue(p2.contains("10 ثوانٍ"))
    }
}

