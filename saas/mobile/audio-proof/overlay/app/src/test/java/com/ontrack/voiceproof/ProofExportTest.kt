package com.ontrack.voiceproof

import java.io.ByteArrayInputStream
import java.io.ByteArrayOutputStream
import org.junit.Assert.*
import org.junit.Test

class ProofExportTest {
    @Test fun nonemptySnapshotMustReadBackExactly() {
        val bytes = "{\"result\":\"صوت واضح\"}".toByteArray()
        val out = ByteArrayOutputStream()
        assertEquals(bytes.size, ProofExport.writeVerified(bytes, { out }, { ByteArrayInputStream(out.toByteArray()) }))
    }
    @Test(expected = IllegalStateException::class) fun zeroByteProviderIsNotReportedAsSuccess() {
        ProofExport.writeVerified("{}".toByteArray(), { ByteArrayOutputStream() }, { ByteArrayInputStream(byteArrayOf()) })
    }
    @Test(expected = IllegalStateException::class) fun truncatedDocumentIsNotReportedAsSuccess() {
        ProofExport.writeVerified("{\"result\":true}".toByteArray(), { ByteArrayOutputStream() }, { ByteArrayInputStream("{".toByteArray()) })
    }
    @Test(expected = IllegalArgumentException::class) fun rejectEmptySnapshotBeforeOpeningDocument() {
        ProofExport.writeVerified(byteArrayOf(), { error("must not open") }, { error("must not read") })
    }
}
