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
}
