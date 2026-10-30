package com.ontrack.voiceproof

import java.io.ByteArrayOutputStream

internal class LinkPcm(private val channel: String) {
    private var sum = 0
    private var frames = 0
    fun input(pcm: ByteArray): ByteArray {
        require(pcm.size % 4 == 0)
        val out = ByteArrayOutputStream()
        for (i in pcm.indices step 4) {
            val left = sample(pcm, i); val right = sample(pcm, i+2)
            sum += when(channel) { "left" -> left; "mix" -> (left+right)/2; else -> right }
            frames++
            if (frames == 3) { val v=sum/3; out.write(v and 255);out.write((v shr 8) and 255);sum=0;frames=0 }
        }
        return out.toByteArray()
    }
    companion object {
        private fun sample(p:ByteArray,i:Int):Int = ((p[i].toInt() and 255) or (p[i+1].toInt() shl 8)).toShort().toInt()
        fun output(pcm:ByteArray, rate:Int):ByteArray {
            require(rate in listOf(8000,16000,24000,48000) && pcm.size%2==0)
            val factor=48000/rate;val out=ByteArray(pcm.size*factor)
            for(i in pcm.indices step 2) for(k in 0 until factor) {
                val at=i*factor+k*2;out[at]=pcm[i];out[at+1]=pcm[i+1]
            }
            return out
        }
    }
}
