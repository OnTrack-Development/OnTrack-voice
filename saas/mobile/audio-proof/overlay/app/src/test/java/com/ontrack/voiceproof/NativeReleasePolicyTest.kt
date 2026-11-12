package com.ontrack.voiceproof
import org.junit.Assert.*
import org.junit.Test

class NativeReleasePolicyTest {
    private val hash="a".repeat(64)
    @Test fun signedNativeFileNameIsStrictAndSized(){
        assertTrue(NativeReleasePolicy.valid(12,11,54L*1024*1024,hash,"ontrack-voice-v12-$hash.apk"))
        assertFalse(NativeReleasePolicy.valid(12,11,54L*1024*1024,hash,"../ontrack-voice-v12-$hash.apk"))
        assertFalse(NativeReleasePolicy.valid(12,11,54L*1024*1024,hash,"ontrack-voice-v13-$hash.apk"))
        assertFalse(NativeReleasePolicy.valid(12,11,NativeReleasePolicy.MAX_APK+1,hash,"ontrack-voice-v12-$hash.apk"))
        assertFalse(NativeReleasePolicy.valid(12,11,5,"not-hash","ontrack-voice-v12-not-hash.apk"))
    }

    @Test fun resumableRangeAndMetaValidationWorkCleanly() {
        val total = 11_665_855L
        assertNull(NativeDownloadResumePolicy.rangeHeaderFor(0L, total))
        assertNull(NativeDownloadResumePolicy.rangeHeaderFor(total, total))
        assertEquals("bytes=4194304-", NativeDownloadResumePolicy.rangeHeaderFor(4_194_304L, total))

        assertTrue(NativeDownloadResumePolicy.shouldAppend206(206, "bytes 4194304-11665854/11665855", 4_194_304L))
        assertFalse(NativeDownloadResumePolicy.shouldAppend206(200, null, 4_194_304L))
        assertFalse(NativeDownloadResumePolicy.shouldAppend206(206, "bytes 0-11665854/11665855", 4_194_304L))

        val token = NativeDownloadResumePolicy.metaToken(hash, total)
        assertTrue(NativeDownloadResumePolicy.isPartialMetaValid(token, hash, total))
        assertFalse(NativeDownloadResumePolicy.isPartialMetaValid("other:$total", hash, total))
    }
}
