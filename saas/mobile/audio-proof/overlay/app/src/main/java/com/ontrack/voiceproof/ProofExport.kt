package com.ontrack.voiceproof

import java.io.InputStream
import java.io.OutputStream

/** The document is successful only after the exact snapshot reads back. */
internal object ProofExport {
    fun writeVerified(bytes: ByteArray, output: () -> OutputStream, input: () -> InputStream): Int {
        require(bytes.isNotEmpty()) { "التقرير فارغ قبل الحفظ" }
        output().use { it.write(bytes); it.flush() }
        val actual = ByteArray(bytes.size + 1)
        val count = input().use {
            var used = 0
            while (used < actual.size) {
                val read = it.read(actual, used, actual.size - used)
                if (read < 0) break
                check(read > 0) { "تعذرت قراءة الملف للتحقق" }
                used += read
            }
            used
        }
        check(count == bytes.size && actual.copyOf(count).contentEquals(bytes)) {
            "الملف المحفوظ ناقص أو فارغ؛ استخدم نسخ التقرير"
        }
        return bytes.size
    }
}
