package com.ontrack.voiceproof

import okhttp3.Dns
import org.json.JSONObject
import java.io.ByteArrayInputStream
import java.io.ByteArrayOutputStream
import java.io.DataInputStream
import java.io.DataOutputStream
import java.net.DatagramPacket
import java.net.DatagramSocket
import java.net.HttpURLConnection
import java.net.InetAddress
import java.net.SocketTimeoutException
import java.net.URL
import java.net.UnknownHostException
import java.security.SecureRandom
import java.util.concurrent.ConcurrentHashMap

/**
 * Resilient DNS resolver for mobile carrier data networks in Egypt (Vodafone, Orange, WE, Etisalat).
 *
 * Architecture and Safety Constraints:
 * 1. System DNS First (Dns.SYSTEM): Respects Android Private DNS (DoT on port 853) and active VPN
 *    policies. Only when System DNS fails (due to carrier port-853 blockage or broken DNS resolvers)
 *    does fallback activate.
 * 2. UDP Fallback with Verification:
 *    - Uses random 16-bit Transaction IDs (RFC 1035).
 *    - Verifies returned Transaction ID, Question name, and Question type to prevent spoofing/poisoning.
 *    - Fast bounded socket timeout (900ms) to prevent multi-second call startup delays.
 *    - Supports both IPv4 (A) and IPv6 (AAAA) records for Dual-Stack mobile networks.
 * 3. DNS-over-HTTPS (DoH) Fallback:
 *    - Activated when UDP port 53 is blocked, intercepted, or filtered by carrier DPI.
 *    - Connects directly to Cloudflare (https://1.1.1.1/dns-query) or Google using direct IP URL,
 *      eliminating recursive bootstrapping DNS lookup loops.
 *    - Cloudflare TLS certificate officially contains "1.1.1.1" in SAN, maintaining 100% standard TLS validation.
 * 4. Stale Cache Bounds:
 *    - Fresh TTL: 5 minutes (300,000ms).
 *    - Emergency Stale TTL: Maximum 1 hour (3,600,000ms). Never serves indefinitely stale entries.
 *    - Clearable on network handover via [clearCache].
 * 5. Strict Hostname Preservation:
 *    - Resolves raw byte arrays into InetAddress.getByAddress(hostname, rawIp), ensuring TLS SNI
 *      and OkHttp HostnameVerifier strictly validate the genuine domain name against the certificate.
 */
internal object ResilientDns : Dns {
    private val cache = ConcurrentHashMap<String, CacheEntry>()
    private val random = SecureRandom()

    private val UDP_FALLBACK_SERVERS = listOf("8.8.8.8", "1.1.1.1", "8.8.4.4")
    private const val DOH_ENDPOINT = "https://1.1.1.1/dns-query"
    private const val FRESH_TTL_MS = 300_000L         // 5 minutes
    private const val MAX_STALE_TTL_MS = 3_600_000L    // 1 hour max stale limit
    private const val UDP_TIMEOUT_MS = 900            // 900ms per UDP attempt
    private const val DOH_TIMEOUT_MS = 1500           // 1500ms for DoH

    @Volatile var lastResolutionSource: String = "none"
        private set

    internal data class CacheEntry(
        val addresses: List<InetAddress>,
        val createdAtMs: Long,
        val expiresAtMs: Long,
        val source: String
    )

    fun clearCache() {
        cache.clear()
        lastResolutionSource = "cleared"
    }

    override fun lookup(hostname: String): List<InetAddress> {
        val now = System.currentTimeMillis()

        // 0. Check fresh cache
        cache[hostname]?.let { entry ->
            if (now < entry.expiresAtMs && entry.addresses.isNotEmpty()) {
                lastResolutionSource = "cache(${entry.source})"
                return entry.addresses
            }
        }

        // 1. Try system DNS first (honors Android Private DNS / DoT and VPN)
        try {
            val systemResult = Dns.SYSTEM.lookup(hostname)
            if (systemResult.isNotEmpty()) {
                lastResolutionSource = "system"
                cache[hostname] = CacheEntry(systemResult, now, now + FRESH_TTL_MS, "system")
                return systemResult
            }
        } catch (_: UnknownHostException) {
            // System DNS failed, proceed to fallback
        } catch (_: Throwable) {
            // Socket or security exception on system DNS, proceed to fallback
        }

        // 2. Query fallback DNS servers over UDP (supports IPv4 A and IPv6 AAAA)
        val resolvedUdpIps = mutableListOf<ByteArray>()
        for (server in UDP_FALLBACK_SERVERS) {
            try {
                // Query A (IPv4)
                val aRecords = queryDnsUdp(hostname, server, qtype = 1)
                resolvedUdpIps.addAll(aRecords)

                // Query AAAA (IPv6)
                try {
                    val aaaaRecords = queryDnsUdp(hostname, server, qtype = 28)
                    resolvedUdpIps.addAll(aaaaRecords)
                } catch (_: Throwable) {
                    // Ignore IPv6 query failure if IPv4 succeeded
                }

                if (resolvedUdpIps.isNotEmpty()) {
                    val addresses = resolvedUdpIps.map { ip ->
                        InetAddress.getByAddress(hostname, ip)
                    }
                    val src = "fallback_udp($server)"
                    lastResolutionSource = src
                    cache[hostname] = CacheEntry(addresses, now, now + FRESH_TTL_MS, src)
                    return addresses
                }
            } catch (e: AuthoritativeNxDomainException) {
                // Authoritative NXDOMAIN: domain definitely does not exist, do not query other servers
                throw UnknownHostException("Authoritative NXDOMAIN: host \"$hostname\" does not exist")
            } catch (_: Throwable) {
                // Timeout or network unreachable on this server, try next
            }
        }

        // 3. Fallback to DNS-over-HTTPS (DoH) if UDP/53 was blocked or dropped
        try {
            val dohIps = queryDoH(hostname)
            if (dohIps.isNotEmpty()) {
                val addresses = dohIps.map { ip ->
                    InetAddress.getByAddress(hostname, ip)
                }
                val src = "fallback_doh(cloudflare)"
                lastResolutionSource = src
                cache[hostname] = CacheEntry(addresses, now, now + FRESH_TTL_MS, src)
                return addresses
            }
        } catch (e: AuthoritativeNxDomainException) {
            throw UnknownHostException("Authoritative DoH NXDOMAIN: host \"$hostname\" does not exist")
        } catch (_: Throwable) {
            // DoH failed as well
        }

        // 4. Stale cache fallback (strictly bounded to MAX_STALE_TTL_MS = 1 hour)
        cache[hostname]?.let { entry ->
            val age = now - entry.createdAtMs
            if (entry.addresses.isNotEmpty() && age < MAX_STALE_TTL_MS) {
                lastResolutionSource = "stale_cache(${entry.source},age=${age / 1000}s)"
                return entry.addresses
            }
        }

        throw UnknownHostException("Unable to resolve host \"$hostname\": system, UDP fallback, and DoH failed")
    }

    private fun queryDnsUdp(hostname: String, dnsServerIp: String, qtype: Int): List<ByteArray> {
        val txId = random.nextInt(0xFFFF) and 0xFFFF
        val socket = DatagramSocket().apply {
            soTimeout = UDP_TIMEOUT_MS
        }
        try {
            val query = buildDnsQuery(hostname, txId, qtype)
            val serverAddr = InetAddress.getByName(dnsServerIp)
            val requestPacket = DatagramPacket(query, query.size, serverAddr, 53)
            socket.send(requestPacket)

            val buffer = ByteArray(512)
            val responsePacket = DatagramPacket(buffer, buffer.size)
            socket.receive(responsePacket)

            // Strict source IP and port validation
            if (responsePacket.address != serverAddr || responsePacket.port != 53) {
                return emptyList()
            }

            return parseDnsResponse(buffer, responsePacket.length, txId, hostname, qtype)
        } finally {
            runCatching { socket.close() }
        }
    }

    private fun buildDnsQuery(hostname: String, txId: Int, qtype: Int): ByteArray {
        val baos = ByteArrayOutputStream()
        val dos = DataOutputStream(baos)
        dos.writeShort(txId)   // Random Transaction ID
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
        dos.writeByte(0)      // Root null label
        dos.writeShort(qtype) // QTYPE: 1 (A) or 28 (AAAA)
        dos.writeShort(1)     // QCLASS: IN (Internet)
        return baos.toByteArray()
    }

    private fun parseDnsResponse(
        data: ByteArray,
        length: Int,
        expectedTxId: Int,
        expectedHostname: String,
        expectedQtype: Int
    ): List<ByteArray> {
        if (length < 12) return emptyList()
        val dis = DataInputStream(ByteArrayInputStream(data, 0, length))
        val respId = dis.readShort().toInt() and 0xFFFF
        if (respId != expectedTxId) {
            // Mismatched transaction ID: possible stale or spoofed response
            return emptyList()
        }

        val flags = dis.readShort().toInt() and 0xFFFF
        val rcode = flags and 0x0F
        if (rcode == 3) {
            // NXDOMAIN: authoritative response that domain does not exist
            throw AuthoritativeNxDomainException()
        }
        if (rcode != 0) return emptyList() // Other error (e.g. SERVFAIL, REFUSED)

        val qdcount = dis.readShort().toInt() and 0xFFFF
        val ancount = dis.readShort().toInt() and 0xFFFF
        dis.readShort() // nscount
        dis.readShort() // arcount

        // Parse & verify Question section
        for (q in 0 until qdcount) {
            val qname = readName(dis, data)
            val qtype = dis.readShort().toInt() and 0xFFFF
            val qclass = dis.readShort().toInt() and 0xFFFF
            if (!qname.equals(expectedHostname, ignoreCase = true) || qtype != expectedQtype || qclass != 1) {
                return emptyList() // Question mismatch
            }
        }

        val results = mutableListOf<ByteArray>()
        // Parse Answers
        for (a in 0 until ancount) {
            readName(dis, data) // Answer name
            val type = dis.readShort().toInt() and 0xFFFF
            val clazz = dis.readShort().toInt() and 0xFFFF
            dis.readInt() // TTL
            val rdlength = dis.readShort().toInt() and 0xFFFF
            if (clazz == 1 && type == expectedQtype) {
                if (type == 1 && rdlength == 4) { // A record (IPv4)
                    val ipBytes = ByteArray(4)
                    dis.readFully(ipBytes)
                    results.add(ipBytes)
                } else if (type == 28 && rdlength == 16) { // AAAA record (IPv6)
                    val ipBytes = ByteArray(16)
                    dis.readFully(ipBytes)
                    results.add(ipBytes)
                } else {
                    dis.skipBytes(rdlength)
                }
            } else {
                dis.skipBytes(rdlength)
            }
        }
        return results
    }

    private fun queryDoH(hostname: String): List<ByteArray> {
        val results = mutableListOf<ByteArray>()
        for (qtype in listOf("A", "AAAA")) {
            val url = URL("$DOH_ENDPOINT?name=$hostname&type=$qtype")
            val conn = (url.openConnection() as HttpURLConnection).apply {
                requestMethod = "GET"
                setRequestProperty("Accept", "application/dns-json")
                connectTimeout = DOH_TIMEOUT_MS
                readTimeout = DOH_TIMEOUT_MS
                instanceFollowRedirects = false
            }
            try {
                if (conn.responseCode == 200) {
                    val body = conn.inputStream.bufferedReader().use { it.readText() }
                    val json = JSONObject(body)
                    val status = json.optInt("Status", -1)
                    if (status == 3) {
                        throw AuthoritativeNxDomainException()
                    }
                    if (status == 0) {
                        val answers = json.optJSONArray("Answer") ?: continue
                        for (i in 0 until answers.length()) {
                            val item = answers.getJSONObject(i)
                            val type = item.optInt("type")
                            val ipStr = item.optString("data", "").trim()
                            if (ipStr.isNotEmpty()) {
                                if (type == 1) { // A
                                    InetAddress.getByName(ipStr).address?.let { results.add(it) }
                                } else if (type == 28) { // AAAA
                                    InetAddress.getByName(ipStr).address?.let { results.add(it) }
                                }
                            }
                        }
                    }
                }
            } finally {
                conn.disconnect()
            }
        }
        return results
    }

    private fun readName(dis: DataInputStream, fullData: ByteArray): String {
        val sb = StringBuilder()
        while (true) {
            val len = dis.readByte().toInt() and 0xFF
            if (len == 0) break
            if ((len and 0xC0) == 0xC0) {
                // Pointer
                val b2 = dis.readByte().toInt() and 0xFF
                val offset = ((len and 0x3F) shl 8) or b2
                val pointerName = readNameFromOffset(fullData, offset)
                if (sb.isNotEmpty()) sb.append('.')
                sb.append(pointerName)
                return sb.toString()
            } else {
                val labelBytes = ByteArray(len)
                dis.readFully(labelBytes)
                if (sb.isNotEmpty()) sb.append('.')
                sb.append(String(labelBytes, Charsets.US_ASCII))
            }
        }
        return sb.toString()
    }

    private fun readNameFromOffset(fullData: ByteArray, startOffset: Int): String {
        var offset = startOffset
        val sb = StringBuilder()
        while (offset < fullData.size) {
            val len = fullData[offset++].toInt() and 0xFF
            if (len == 0) break
            if ((len and 0xC0) == 0xC0) {
                if (offset >= fullData.size) break
                val b2 = fullData[offset++].toInt() and 0xFF
                val nextOffset = ((len and 0x3F) shl 8) or b2
                val pointerName = readNameFromOffset(fullData, nextOffset)
                if (sb.isNotEmpty()) sb.append('.')
                sb.append(pointerName)
                return sb.toString()
            } else {
                if (offset + len > fullData.size) break
                val label = String(fullData, offset, len, Charsets.US_ASCII)
                offset += len
                if (sb.isNotEmpty()) sb.append('.')
                sb.append(label)
            }
        }
        return sb.toString()
    }

    internal class AuthoritativeNxDomainException : Exception()
}
