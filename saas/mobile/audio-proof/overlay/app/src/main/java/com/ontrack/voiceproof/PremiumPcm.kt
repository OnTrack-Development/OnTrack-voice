package com.ontrack.voiceproof

import java.io.ByteArrayOutputStream

/**
 * Fish IO Premium delivers headerless 16-bit little-endian mono PCM (48kHz direct or 24k/44.1k).
 * When source is 48kHz, converts 1:1 with zero resampling and zero allocation overhead.
 * When source is 24kHz or 44.1kHz, resamples continuously to the 48kHz mono SIM bridge,
 * preserving the last source frame and fractional output time across HTTP chunks.
 */
internal class PremiumPcm(private val sourceRate:Int=48000) {
    init { require(sourceRate in setOf(16000,22050,24000,44100,48000)) }
    private var sourceIndex=0L
    private var outputIndex=0L
    private var previous=0
    private var odd=-1
    private val outputRate=48000L

    fun convert(input:ByteArray):ByteArray {
        if(input.isEmpty()) return ByteArray(0)
        // 48kHz direct bypass: zero resampling, exact bit-level pass-through
        if(sourceRate == 48000) {
            if(odd < 0) {
                if(input.size % 2 == 0) return input.copyOf()
                odd = input[input.size - 1].toInt() and 255
                return input.copyOf(input.size - 1)
            }
            val out = ByteArray(input.size + 1)
            out[0] = odd.toByte()
            System.arraycopy(input, 0, out, 1, input.size)
            if(out.size % 2 != 0) {
                odd = out[out.size - 1].toInt() and 255
                return out.copyOf(out.size - 1)
            }
            odd = -1
            return out
        }

        val out=ByteArrayOutputStream(input.size*2)
        var position=0
        fun consume(sample:Int) {
            val index=sourceIndex++
            if(index==0L) {
                write(out,sample)
                outputIndex=1L
            }else{
                while(outputIndex*sourceRate<=index*outputRate) {
                    val numerator=outputIndex*sourceRate-(index-1)*outputRate
                    val fraction=numerator.toDouble()/outputRate.toDouble()
                    val value=(previous+(sample-previous)*fraction).toInt().coerceIn(-32768,32767)
                    write(out,value);outputIndex++
                }
            }
            previous=sample
        }
        if(odd>=0 && input.isNotEmpty()){
            consume((odd or (input[0].toInt() shl 8)).toShort().toInt())
            odd=-1;position=1
        }
        while(position+1<input.size){
            val v=(input[position].toInt() and 255) or (input[position+1].toInt() shl 8)
            consume(v.toShort().toInt())
            position+=2
        }
        if(position<input.size)odd=input[position].toInt() and 255
        return out.toByteArray()
    }

    private fun write(out:ByteArrayOutputStream,value:Int){
        out.write(value and 255);out.write((value shr 8) and 255)
    }

    fun reset(){sourceIndex=0;outputIndex=0;previous=0;odd=-1}
}

internal object PremiumPhrase {
    const val MIN_COMMA_CHARS = 40

    fun speakable(text:String):String {
        return text
            .replace(Regex("\\([^)]{0,120}\\)")," ")
            .replace(Regex("\\[[^\\]]{0,120}\\]")," ")
            .replace(Regex("\\s+")," ")
            .trim()
    }

    fun prefix(text:String, minComma:Int=MIN_COMMA_CHARS):String {
        val clean=text.trim()
        if(clean.length<3)return ""

        // Terminal punctuation: support ellipsis (...), question, exclamation, dot, newline
        val termFound=Regex("^([\\s\\S]{3,160}?(?:\\.{2,}|[.!?؟\\n]))(?=\\s|$)").find(clean)
        if(termFound!=null) {
            val candidate=termFound.groupValues[1].trim()
            if(candidate.count{it=='('}==candidate.count{it==')'} &&
                candidate.count{it=='['}==candidate.count{it==']'}) return candidate
        }

        // Comma / semicolon punctuation (، , ؛): enforce minComma threshold (40 chars)
        if(clean.length>=minComma) {
            val commaFound=Regex("^([\\s\\S]{$minComma,160}?[،,؛])(?=\\s|$)").find(clean)
            if(commaFound!=null) {
                val candidate=commaFound.groupValues[1].trim()
                if(candidate.count{it=='('}==candidate.count{it==')'} &&
                    candidate.count{it=='['}==candidate.count{it==']'}) return candidate
            }
        }

        // Fallback space cut for long continuous text without punctuation
        if(clean.length<38)return ""
        val cutoff=clean.take(54).lastIndexOf(' ')
        if(cutoff<=20)return ""
        val candidate=clean.substring(0,cutoff).trim()
        return if(candidate.count{it=='('}==candidate.count{it==')'} &&
            candidate.count{it=='['}==candidate.count{it==']'}) candidate else ""
    }

    fun merge(old:String,next:String):String {
        val current=old.trim();val chunk=next.trim()
        if(chunk.isEmpty()||chunk==current||current.endsWith(chunk))return current
        if(current.isEmpty()||chunk.startsWith(current))return chunk
        return "$current $chunk"
    }
}
