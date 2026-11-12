package com.ontrack.voiceproof

import org.json.JSONArray
import org.json.JSONObject
import kotlin.math.PI
import kotlin.math.cos

internal data class IvrRouteMatch(
    val digit: String,
    val label: String,
    val departmentPrompt: String,
    val transferPhone: String,
)

internal object IvrDtmfRouter {
    fun findRoute(routes: List<IvrRouteMatch>, digit: String): IvrRouteMatch? {
        if (digit.isBlank()) return null
        val target = digit.trim()
        return routes.firstOrNull { it.digit.trim() == target && it.label.isNotBlank() }
    }

    fun findRoute(routes: JSONArray?, digit: String): IvrRouteMatch? {
        if (routes == null || digit.isBlank()) return null
        val target = digit.trim()
        for (i in 0 until routes.length()) {
            val item = routes.optJSONObject(i) ?: continue
            if (item.optString("digit").trim() == target) {
                val label = item.optString("label").trim()
                if (label.isBlank()) continue
                return IvrRouteMatch(
                    digit = target,
                    label = label,
                    departmentPrompt = item.optString("department_prompt").trim(),
                    transferPhone = item.optString("transfer_phone").trim(),
                )
            }
        }
        return null
    }

    fun buildClientContentPrompt(digit: String, match: IvrRouteMatch?): String {
        if (match == null) {
            return "[حدث نظام IVR: ضغط المتصل على الزر رقم ($digit) - هذا الرقم غير مدرج في القائمة؛ أبلغه باختصار وأعد عليه الخيارات المتاحة]"
        }
        val sb = StringBuilder()
        sb.append("[حدث نظام IVR: ضغط المتصل على الزر رقم (")
            .append(match.digit)
            .append(") - انتقل فوراً إلى قسم: ")
            .append(match.label)
            .append(" ونفّذ تعليماته]")
        if (match.departmentPrompt.isNotBlank()) {
            sb.append("\nتعليمات القسم: ").append(match.departmentPrompt)
        }
        if (match.transferPhone.isNotBlank()) {
            sb.append("\n(تنبيه النظام: تم تسجيل تحويل هاتفي للقسم/المدير بعد انتهاء المكالمة بـ 10 ثوانٍ؛ أبلغ المتصل باختصار وتابع معه).")
        }
        return sb.toString()
    }
}

/**
 * Lightweight ITU-T Goertzel DTMF tone detector for 16 kHz PCM16 mono streams.
 *
 * Uses 320-sample (20 ms) analysis windows advancing every 160 samples (10 ms hop)
 * with second-harmonic and broadband energy checks to avoid false triggers on speech.
 */
internal class DtmfGoertzelDetector(
    private val sampleRate: Int = 16000,
    private val blockSize: Int = 320,
    private val hopSize: Int = 160,
    private val requiredConsecutiveFrames: Int = 2,
    private val sameDigitCooldownMs: Long = 420L,
) {
    private val window = DoubleArray(blockSize)
    private var filled = 0

    private var candidateDigit: Char? = null
    private var candidateStreak = 0
    private var activeLatchedDigit: Char? = null
    private var missStreak = 0
    private var lastEmitAtMs = -10_000L

    private fun coeff(freqHz: Double): Double = 2.0 * cos(2.0 * PI * freqHz / sampleRate.toDouble())

    private val rowCoeffs = DoubleArray(ROW_FREQS.size) { coeff(ROW_FREQS[it]) }
    private val colCoeffs = DoubleArray(COL_FREQS.size) { coeff(COL_FREQS[it]) }
    private val rowHarm2Coeffs = DoubleArray(ROW_FREQS.size) { coeff(ROW_FREQS[it] * 2.0) }
    private val colHarm2Coeffs = DoubleArray(COL_FREQS.size) { coeff(COL_FREQS[it] * 2.0) }

    fun reset() {
        filled = 0
        candidateDigit = null
        candidateStreak = 0
        activeLatchedDigit = null
        missStreak = 0
        lastEmitAtMs = -10_000L
    }

    /**
     * Feed little-endian 16-bit mono PCM bytes at [sampleRate].
     * Returns newly pressed key ('0'..'9', '*', '#') or null.
     */
    fun feedPcm16(pcmBytes: ByteArray, nowMs: Long): Char? {
        if (pcmBytes.size < 2) return null
        var detected: Char? = null
        var idx = 0
        val limit = pcmBytes.size - 1
        while (idx < limit) {
            val lo = pcmBytes[idx].toInt() and 0xFF
            val hi = pcmBytes[idx + 1].toInt() shl 8
            val sample = (lo or hi).toShort().toDouble() / 32768.0
            idx += 2

            window[filled++] = sample
            if (filled == blockSize) {
                val frameDigit = analyzeCurrentWindow()
                val emitted = stepStateMachine(frameDigit, nowMs)
                if (emitted != null && detected == null) {
                    detected = emitted
                }
                // Slide window forward by hopSize samples (50% overlap when hopSize == blockSize / 2)
                val keep = blockSize - hopSize
                if (keep > 0) {
                    System.arraycopy(window, hopSize, window, 0, keep)
                }
                filled = keep
            }
        }
        return detected
    }

    private fun stepStateMachine(frameDigit: Char?, nowMs: Long): Char? {
        if (frameDigit == null) {
            candidateDigit = null
            candidateStreak = 0
            missStreak++
            if (missStreak >= 2) {
                activeLatchedDigit = null
            }
            return null
        }

        missStreak = 0
        if (frameDigit == candidateDigit) {
            candidateStreak++
        } else {
            candidateDigit = frameDigit
            candidateStreak = 1
        }

        if (candidateStreak < requiredConsecutiveFrames) {
            return null
        }

        val latchedSame = activeLatchedDigit == frameDigit && (nowMs - lastEmitAtMs) < sameDigitCooldownMs
        if (latchedSame) {
            return null
        }

        activeLatchedDigit = frameDigit
        lastEmitAtMs = nowMs
        return frameDigit
    }

    private fun analyzeCurrentWindow(): Char? {
        var energy = 0.0
        for (i in 0 until blockSize) {
            val x = window[i]
            energy += x * x
        }
        val meanPower = energy / blockSize.toDouble()
        if (meanPower < 1.5e-5) {
            return null
        }

        var bestRowIdx = -1
        var bestRowPower = 0.0
        var secondRowPower = 0.0
        for (r in rowCoeffs.indices) {
            val p = goertzelPower(rowCoeffs[r])
            if (p > bestRowPower) {
                secondRowPower = bestRowPower
                bestRowPower = p
                bestRowIdx = r
            } else if (p > secondRowPower) {
                secondRowPower = p
            }
        }

        var bestColIdx = -1
        var bestColPower = 0.0
        var secondColPower = 0.0
        for (c in colCoeffs.indices) {
            val p = goertzelPower(colCoeffs[c])
            if (p > bestColPower) {
                secondColPower = bestColPower
                bestColPower = p
                bestColIdx = c
            } else if (p > secondColPower) {
                secondColPower = p
            }
        }

        // Reject extended A/B/C/D column (1633 Hz) or invalid indices
        if (bestRowIdx !in 0..3 || bestColIdx !in 0..2) {
            return null
        }

        if (bestRowPower < 3.2 * maxOf(secondRowPower, 1e-12)) return null
        if (bestColPower < 3.2 * maxOf(secondColPower, 1e-12)) return null

        val twist = bestColPower / maxOf(bestRowPower, 1e-12)
        if (twist < 0.15 || twist > 6.5) return null

        // Parseval expected power for two sinusoids over blockSize samples is energy * blockSize * 0.5
        val expectedDualTonePower = energy * (blockSize * 0.5)
        if ((bestRowPower + bestColPower) < 0.30 * expectedDualTonePower) return null

        // Second harmonic rejection (filters speech vowels)
        val rowH2 = goertzelPower(rowHarm2Coeffs[bestRowIdx])
        val colH2 = goertzelPower(colHarm2Coeffs[bestColIdx])
        if (rowH2 > 0.30 * bestRowPower || colH2 > 0.30 * bestColPower) return null

        return KEYPAD[bestRowIdx][bestColIdx]
    }

    private fun goertzelPower(coeff: Double): Double {
        var s1 = 0.0
        var s2 = 0.0
        for (i in 0 until blockSize) {
            val s0 = window[i] + coeff * s1 - s2
            s2 = s1
            s1 = s0
        }
        val power = s1 * s1 + s2 * s2 - coeff * s1 * s2
        return if (power > 0.0) power else 0.0
    }

    companion object {
        private val ROW_FREQS = doubleArrayOf(697.0, 770.0, 852.0, 941.0)
        private val COL_FREQS = doubleArrayOf(1209.0, 1336.0, 1477.0, 1633.0)
        private val KEYPAD = arrayOf(
            charArrayOf('1', '2', '3'),
            charArrayOf('4', '5', '6'),
            charArrayOf('7', '8', '9'),
            charArrayOf('*', '0', '#'),
        )
    }
}

