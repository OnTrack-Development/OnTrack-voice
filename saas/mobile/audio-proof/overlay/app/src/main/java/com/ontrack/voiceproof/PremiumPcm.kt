package com.ontrack.voiceproof

import java.io.ByteArrayOutputStream

/**
 * Fish IO Premium delivers headerless 44.1kHz 16-bit little-endian mono PCM.
 * Resamples continuously to the existing 48kHz mono SIM bridge, preserving
 * the last source frame and fractional output time across HTTP chunks.
 */
internal class PremiumPcm(private val sourceRate:Int=44100) {
    init { require(sourceRate in setOf(16000,22050,24000,44100,48000)) }
    private var sourceIndex=0L
    private var outputIndex=0L
    private var previous=0
    private var odd=-1
    private val outputRate=48000L
    fun convert(input:ByteArray):ByteArray {
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
    fun speakable(text:String):String {
        return text
            .replace(Regex("\\([^)]{0,120}\\)")," ")
            .replace(Regex("\\[[^\\]]{0,120}\\]")," ")
            .replace(Regex("\\s+")," ")
            .trim()
    }
    fun prefix(text:String):String {
        val clean=text.trim()
        if(clean.length<3)return ""
        val found=Regex("^([\\s\\S]{3,120}?[.!؟\\n])(?=\\s|$)").find(clean)
        if(found!=null) {
            val candidate=found.groupValues[1].trim()
            if(candidate.count{it=='('}==candidate.count{it==')'} &&
                candidate.count{it=='['}==candidate.count{it==']'}) return candidate
        }
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
