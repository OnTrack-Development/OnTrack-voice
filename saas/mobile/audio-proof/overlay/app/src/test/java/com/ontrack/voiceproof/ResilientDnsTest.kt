package com.ontrack.voiceproof

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.io.ByteArrayOutputStream
import java.io.DataOutputStream
import java.net.InetAddress

class ResilientDnsTest {

    private fun buildSyntheticResponse(
        txId: Int,
        hostname: String,
        qtype: Int,
        answerType: Int,
        ipBytes: ByteArray
    ): ByteArray {
        val baos = ByteArrayOutputStream()
        val dos = DataOutputStream(baos)
        dos.writeShort(txId)
        dos.writeShort(0x8180) // Flags: response, no error
        dos.writeShort(1)      // 1 question
        dos.writeShort(1)      // 1 answer
        dos.writeShort(0)
        dos.writeShort(0)

        // Question
        for (part in hostname.split('.')) {
            dos.writeByte(part.length)
            dos.write(part.toByteArray(Charsets.US_ASCII))
        }
        dos.writeByte(0)
        dos.writeShort(qtype)
        dos.writeShort(1) // IN

        // Answer
        dos.writeShort(0xc00c) // pointer to question
        dos.writeShort(answerType)
        dos.writeShort(1) // IN
        dos.writeInt(300)
        dos.writeShort(ipBytes.size)
        dos.write(ipBytes)

        return baos.toByteArray()
    }

    @Test
    fun testDnsQuerySerializationMatchesRfc1035() {
        val method = ResilientDns::class.java.getDeclaredMethod(
            "buildDnsQuery", String::class.java, Int::class.javaPrimitiveType, Int::class.javaPrimitiveType
        ).apply { isAccessible = true }

        val testTxId = 0x4f82
        val query = method.invoke(ResilientDns, "app.ontrackegy.com", testTxId, 1) as ByteArray
        assertTrue(query.size > 12)
        // Transaction ID matches exactly
        assertEquals(0x4f.toByte(), query[0])
        assertEquals(0x82.toByte(), query[1])
        // Flags: 0x0100 (recursion desired)
        assertEquals(0x01.toByte(), query[2])
        assertEquals(0x00.toByte(), query[3])
        // Question count = 1
        assertEquals(0x00.toByte(), query[4])
        assertEquals(0x01.toByte(), query[5])
        // QTYPE = 1 (A)
        assertEquals(0x00.toByte(), query[query.size - 4])
        assertEquals(0x01.toByte(), query[query.size - 3])
        // QCLASS = 1 (IN)
        assertEquals(0x00.toByte(), query[query.size - 2])
        assertEquals(0x01.toByte(), query[query.size - 1])
    }

    @Test
    fun testDnsResponseParsingExtractsAandAaaaRecordsWithTxIdVerification() {
        val txId = 0x3d7a
        val parseMethod = ResilientDns::class.java.getDeclaredMethod(
            "parseDnsResponse",
            ByteArray::class.java,
            Int::class.javaPrimitiveType,
            Int::class.javaPrimitiveType,
            String::class.java,
            Int::class.javaPrimitiveType
        ).apply { isAccessible = true }

        // 1. IPv4 A record test
        val ipv4Bytes = byteArrayOf(65.toByte(), 21.toByte(), 208.toByte(), 232.toByte())
        val bytesA = buildSyntheticResponse(txId, "app.ontrackegy.com", qtype = 1, answerType = 1, ipv4Bytes)

        @Suppress("UNCHECKED_CAST")
        val resultsA = parseMethod.invoke(ResilientDns, bytesA, bytesA.size, txId, "app.ontrackegy.com", 1) as List<ByteArray>
        assertEquals(1, resultsA.size)
        assertEquals(65.toByte(), resultsA[0][0])
        assertEquals(21.toByte(), resultsA[0][1])
        assertEquals(208.toByte(), resultsA[0][2])
        assertEquals(232.toByte(), resultsA[0][3])

        // Verify InetAddress creation strictly preserves genuine domain name for SNI
        val addr = InetAddress.getByAddress("app.ontrackegy.com", resultsA[0])
        assertEquals("app.ontrackegy.com", addr.hostName)
        assertEquals("65.21.208.232", addr.hostAddress)

        // 2. IPv6 AAAA record test
        val ipv6Bytes = ByteArray(16) { it.toByte() }
        val bytesAaaa = buildSyntheticResponse(txId, "app.ontrackegy.com", qtype = 28, answerType = 28, ipv6Bytes)

        @Suppress("UNCHECKED_CAST")
        val resultsAaaa = parseMethod.invoke(ResilientDns, bytesAaaa, bytesAaaa.size, txId, "app.ontrackegy.com", 28) as List<ByteArray>
        assertEquals(1, resultsAaaa.size)
        assertEquals(16, resultsAaaa[0].size)

        // 3. Spoofed packet with mismatched TxID: must be rejected
        @Suppress("UNCHECKED_CAST")
        val rejectedResults = parseMethod.invoke(ResilientDns, bytesA, bytesA.size, 0x9999, "app.ontrackegy.com", 1) as List<ByteArray>
        assertTrue("Mismatched transaction ID must return empty results", rejectedResults.isEmpty())

        // 4. Mismatched Question type: must be rejected
        @Suppress("UNCHECKED_CAST")
        val mismatchedQtype = parseMethod.invoke(ResilientDns, bytesA, bytesA.size, txId, "app.ontrackegy.com", 28) as List<ByteArray>
        assertTrue("Mismatched question type must return empty results", mismatchedQtype.isEmpty())
    }

    @Test
    fun testCacheClearingOnNetworkHandover() {
        ResilientDns.clearCache()
        assertEquals("cleared", ResilientDns.lastResolutionSource)
    }

    @Test
    fun testLookupPreservesHostForLocalhost() {
        val addresses = ResilientDns.lookup("localhost")
        assertTrue(addresses.isNotEmpty())
        assertNotNull(addresses.first())
        assertEquals("localhost", addresses.first().hostName)
    }

    @Test
    fun testPreferIpv4PlacesInet4AddressFirst() {
        val ipv6 = InetAddress.getByAddress("example.com", ByteArray(16) { 1 })
        val ipv4 = InetAddress.getByAddress("example.com", byteArrayOf(1, 2, 3, 4))
        val mixed = listOf(ipv6, ipv4)
        val sorted = ResilientDns.preferIpv4(mixed)
        assertEquals(2, sorted.size)
        assertTrue(sorted[0] is java.net.Inet4Address)
        assertTrue(sorted[1] is java.net.Inet6Address)
    }
}
