package com.ontrack.voiceproof

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.io.ByteArrayOutputStream
import java.io.DataOutputStream
import java.net.InetAddress

class ResilientDnsTest {

    @Test
    fun testDnsQuerySerializationMatchesRfc1035() {
        val method = ResilientDns::class.java.getDeclaredMethod("buildDnsQuery", String::class.java).apply {
            isAccessible = true
        }
        val query = method.invoke(ResilientDns, "app.ontrackegy.com") as ByteArray
        assertTrue(query.size > 12)
        // Transaction ID
        assertEquals(0x1a.toByte(), query[0])
        assertEquals(0x2b.toByte(), query[1])
        // Flags (recursion desired)
        assertEquals(0x01.toByte(), query[2])
        assertEquals(0x00.toByte(), query[3])
        // Question count = 1
        assertEquals(0x00.toByte(), query[4])
        assertEquals(0x01.toByte(), query[5])
    }

    @Test
    fun testDnsResponseParsingExtractsArecords() {
        // Construct a synthetic valid DNS response for app.ontrackegy.com -> 192.0.2.1
        val baos = ByteArrayOutputStream()
        val dos = DataOutputStream(baos)
        dos.writeShort(0x1a2b) // ID
        dos.writeShort(0x8180) // Flags: response, recursion available, no error
        dos.writeShort(1)      // 1 question
        dos.writeShort(1)      // 1 answer
        dos.writeShort(0)
        dos.writeShort(0)
        // Question: app.ontrackegy.com
        for (part in listOf("app", "ontrackegy", "com")) {
            dos.writeByte(part.length)
            dos.write(part.toByteArray(Charsets.US_ASCII))
        }
        dos.writeByte(0)
        dos.writeShort(1) // A
        dos.writeShort(1) // IN

        // Answer: pointer to question name (0xc00c), type 1 (A), class 1 (IN), ttl 300, rdlength 4, IP 192.0.2.1
        dos.writeShort(0xc00c)
        dos.writeShort(1) // A
        dos.writeShort(1) // IN
        dos.writeInt(300) // TTL
        dos.writeShort(4) // length
        dos.write(byteArrayOf(192.toByte(), 0.toByte(), 2.toByte(), 1.toByte()))

        val bytes = baos.toByteArray()
        val parseMethod = ResilientDns::class.java.getDeclaredMethod(
            "parseDnsResponse", ByteArray::class.java, Int::class.javaPrimitiveType
        ).apply { isAccessible = true }

        @Suppress("UNCHECKED_CAST")
        val results = parseMethod.invoke(ResilientDns, bytes, bytes.size) as List<ByteArray>
        assertEquals(1, results.size)
        assertEquals(192.toByte(), results[0][0])
        assertEquals(0.toByte(), results[0][1])
        assertEquals(2.toByte(), results[0][2])
        assertEquals(1.toByte(), results[0][3])

        // Verify InetAddress creation preserves hostname for TLS validation
        val addr = InetAddress.getByAddress("app.ontrackegy.com", results[0])
        assertEquals("app.ontrackegy.com", addr.hostName)
        assertEquals("192.0.2.1", addr.hostAddress)
    }

    @Test
    fun testLookupPreservesHostForLocalOrLoopback() {
        val addresses = ResilientDns.lookup("localhost")
        assertTrue(addresses.isNotEmpty())
        assertNotNull(addresses.first())
    }
}
