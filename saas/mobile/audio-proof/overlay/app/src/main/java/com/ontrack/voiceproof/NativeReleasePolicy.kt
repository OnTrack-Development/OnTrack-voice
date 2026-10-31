package com.ontrack.voiceproof

/** Structural validation happens before an update URL is ever constructed. */
internal object NativeReleasePolicy {
    const val MAX_APK=150L*1024*1024
    fun valid(version:Long, installed:Long, size:Long, hash:String, file:String):Boolean =
        version in 1..1000000 && installed>=1 && size in 1024..MAX_APK &&
        hash.matches(Regex("[a-f0-9]{64}")) &&
        file=="ontrack-voice-v${version}-${hash}.apk"
}
