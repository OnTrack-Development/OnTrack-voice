package com.ontrack.voiceproof

import java.security.PublicKey
import java.security.Signature
import java.security.MessageDigest
import java.util.Base64

/** Pure verifier, also run on the JVM with tampered payload regression tests. */
object SignedOta {
    const val MAX_HTML = 524288
    fun verify(payload: String, signature: String, key: PublicKey): Boolean = runCatching {
        require(payload.toByteArray(Charsets.UTF_8).size <= 16384)
        Signature.getInstance("SHA256withRSA").run {
            initVerify(key); update(payload.toByteArray(Charsets.UTF_8))
            verify(Base64.getDecoder().decode(signature))
        }
    }.getOrDefault(false)
    fun hash(bytes: ByteArray): String = MessageDigest.getInstance("SHA-256").digest(bytes).joinToString("") { "%02x".format(it) }
    fun compatible(runtime: Int, minNative: Long, maxNative: Long, native: Long, revision: Long, size: Int, hash: String, file: String): Boolean =
        runtime == 1 && native in minNative..maxNative && revision >= 2 && size in 1..MAX_HTML &&
            hash.matches(Regex("[a-f0-9]{64}")) && file == "ui-$hash.html"
}
