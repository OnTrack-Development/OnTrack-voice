package com.ontrack.voiceproof
import org.junit.Assert.*
import org.junit.Test
import java.security.KeyPairGenerator
import java.security.Signature
import java.util.Base64
class SignedOtaTest {
    @Test fun modifiedPayloadAndWrongSignerAreRejected(){
        val generator=KeyPairGenerator.getInstance("RSA").apply{initialize(2048)}
        val pair=generator.generateKeyPair();val raw="signed UI metadata"
        val signature=Signature.getInstance("SHA256withRSA").run{initSign(pair.private);update(raw.toByteArray());Base64.getEncoder().encodeToString(sign())}
        assertTrue(SignedOta.verify(raw,signature,pair.public))
        assertFalse(SignedOta.verify(raw+"!",signature,pair.public))
        assertFalse(SignedOta.verify(raw,signature,generator.generateKeyPair().public))
        assertFalse(SignedOta.verify(raw,"not base64",pair.public))
    }
    @Test fun incompatibleRuntimePathTraversalAndOversizedBundleAreRejected(){
        val hash="a".repeat(64)
        fun accepts(runtime:Int=1,min:Long=7,max:Long=7,size:Int=8,file:String="ui-$hash.html")=SignedOta.compatible(runtime,min,max,7,2,size,hash,file)
        assertTrue(accepts());assertFalse(accepts(runtime=2));assertFalse(accepts(min=8,max=9));assertFalse(accepts(size=SignedOta.MAX_HTML+1));assertFalse(accepts(file="../ui-$hash.html"))
    }
}
