package com.ontrack.voiceproof

import okhttp3.Dns
import java.io.ByteArrayInputStream
import java.io.ByteArrayOutputStream
import java.io.DataInputStream
import java.io.DataOutputStream
import java.net.DatagramPacket
import java.net.DatagramSocket
import java.net.InetAddress
import java.net.UnknownHostException
import java.util.concurrent.ConcurrentHashMap

/**
 * Resilient DNS resolver for mobile carrier data networks in Egypt (Vodafone, Orange, WE, Etisalat).
 *
 * Carrier DNS on mobile networks frequently fails to resolve public subdomains or suffers from
 * broken IPv6 fallback, resulting in "UnknownHostException: Unable to resolve host app.ontrackegy.com".
 *
 * ResilientDns resolves this securely:
 * 1. Tries standard Android system DNS (Dns.SYSTEM).
 * 2. If system DNS succeeds, caches the resolved InetAddress list (5 minutes TTL).
 * 3. If system DNS throws UnknownHostException or times out, queries public DNS (Google 8.8.8.8, Cloudflare 1.1.1.1)
 *    directly over standard DNS UDP protocol (RFC 1035) for standard A records.
 * 4. Wraps resolved IP in InetAddress.getByAddress(hostname, rawIp) so TLS SNI and HostnameVerifier
 *    strictly validate the canonical domain name and certificate without bypass or hardcoding.
 */
internal object ResilientDns : Dns {
    private val cache = ConcurrentHashMap<String, CacheEntry>()
    private val FALLBACK_SERVERS = listOf("8.8.8.8", "1.1.1.1", "8.8.4.4")

    @Volatile var lastResolutionSource: String = "none"
        private set

    private data class CacheEntry(val addresses: List<InetAddress>, val expiresAtMs: Long, val source: String)

    override fun lookup(hostname: String): List<InetAddress> {
        val now = System.currentTimeMillis()
        cache[hostname]?.let { entry ->
            if (now < entry.expiresAtMs && entry.addresses.isNotEmpty()) {
                lastResolutionSource = "cache(${entry.source})"
                return entry.addresses
            }
        }

        // 1. Try system DNS first
        try {
            val systemResult = Dns.SYSTEM.lookup(hostname)
            if (systemResult.isNotEmpty()) {
                lastResolutionSource = "system"
                cache[hostname] = CacheEntry(systemResult, now + 300_000L, "system")
                return systemResult
            }
        } catch (_: UnknownHostException) {
            // Fall through to resilient fallback
        } catch (_: Throwable) {
            // System DNS threw socket or security exception, fall through
        }

        // 2. Query fallback DNS servers for IPv4 A records
        for (server in FALLBACK_SERVERS) {
            try {
                val resolvedIps = queryDnsUdp(hostname, server)
                if (resolvedIps.isNotEmpty()) {
                    val addresses = resolvedIps.map { ip ->
                        InetAddress.getByAddress(hostname, ip)
                    }
                    val src = "fallback($server)"
                    lastResolutionSource = src
                    cache[hostname] = CacheEntry(addresses, now + 300_000L, src)
                    return addresses
                }
            } catch (_: Throwable) {
                // Continue to next fallback server
            }
        }

        // If cached entry exists even if slightly expired, return it as emergency stale fallback
        cache[hostname]?.let { entry ->
            if (entry.addresses.isNotEmpty()) {
                lastResolutionSource = "stale_cache"
                return entry.addresses
            }
        }

        throw UnknownHostException("Unable to resolve host \"$hostname\": system and fallback DNS failed")
    }

    private fun queryDnsUdp(hostname: String, dnsServerIp: String): List<ByteArray> {
        val socket = DatagramSocket().apply {
            soTimeout = 1800 // 1.8s timeout
        }
        try {
            val query = buildDnsQuery(hostname)
            val serverAddr = InetAddress.getByName(dnsServerIp)
            val requestPacket = DatagramPacket(query, query.size, serverAddr, 53)
            socket.send(requestPacket)

            val buffer = ByteArray(512)
            val responsePacket = DatagramPacket(buffer, buffer.size)
            socket.receive(responsePacket)

            return parseDnsResponse(buffer, responsePacket.length)
        } finally {
            runCatching { socket.close() }
        }
    }

    private fun buildDnsQuery(hostname: String): ByteArray {
        val baos = ByteArrayOutputStream()
        val dos = DataOutputStream(baos)
        dos.writeShort(0x1a2b) // Transaction ID
        dos.writeShort(0x0100) // Flags: standard query, recursion desired
        dos.writeShort(1)      // Questions: 1
        dos.writeShort(0)      // Answer RRs: 0
        dos.writeShort(0)      // Authority RRs: 0
        dos.writeShort(0)      // Additional RRs: 0

        // QNAME: length-prefixed labels
        for (part in hostname.split('.')) {
            val bytes = part.toByteArray(Charsets.US_ASCII)
            dos.writeByte(bytes.size)
            dos.write(bytes)
        }
        dos.writeByte(0) // Root null label
        dos.writeShort(1) // QTYPE: A (IPv4)
        dos.writeShort(1) // QCLASS: IN (Internet)
        return baos.toByteArray()
    }

    private fun parseDnsResponse(data: ByteArray, length: Int): List<ByteArray> {
        if (length < 12) return emptyList()
        val dis = DataInputStream(ByteArrayInputStream(data, 0, length))
        dis.readShort() // ID
        val flags = dis.readShort().toInt() and 0xFFFF
        val rcode = flags and 0x0F
        if (rcode != 0) return emptyList() // Error response
        val qdcount = dis.readShort().toInt() and 0xFFFF
        val ancount = dis.readShort().toInt() and 0xFFFF
        dis.readShort() // nscount
        dis.readShort() // arcount

        // Skip Question section
        for (q in 0 until qdcount) {
            skipName(dis)
            dis.readShort() // qtype
            dis.readShort() // qclass
        }

        val results = mutableListOf<ByteArray>()
        // Parse Answers
        for (a in 0 until ancount) {
            skipName(dis)
            val type = dis.readShort().toInt() and 0xFFFF
            val clazz = dis.readShort().toInt() and 0xFFFF
            dis.readInt() // TTL
            val rdlength = dis.readShort().toInt() and 0xFFFF
            if (type == 1 && clazz == 1 && rdlength == 4) { // A record, IN class
                val ipBytes = ByteArray(4)
                dis.readFully(ipBytes)
                results.add(ipBytes)
            } else {
                dis.skipBytes(rdlength)
            }
        }
        return results
    }

    private fun skipName(dis: DataInputStream) {
        while (true) {
            val b = dis.readByte().toInt() and 0xFF
            if (b == 0) break
            if ((b and 0xC0) == 0xC0) {
                // Compression pointer (2 bytes total, 1 already read)
                dis.readByte()
                break
            } else {
                dis.skipBytes(b)
            }
        }
    }
}
