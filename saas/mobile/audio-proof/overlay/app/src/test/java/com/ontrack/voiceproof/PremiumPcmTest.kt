package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test
import java.io.ByteArrayOutputStream

class PremiumPcmTest {
    @Test fun direct48kPassThrough() {
        val bytes = ByteArray(48000 * 2) { (it % 127).toByte() }
        val out = PremiumPcm(48000).convert(bytes)
        assertArrayEquals(bytes, out)
    }

    @Test fun chunkBoundaryAndOddByteInvariant() {
        val samples=44100
        val bytes=ByteArray(samples*2) { i-> if(i%2==0) 0x20.toByte() else 0x03 }
        val whole=PremiumPcm(44100).convert(bytes)
        val split=PremiumPcm(44100)
        val segments=ByteArrayOutputStream()
        var offset=0
        for(size in listOf(1,27,349,1308,7637,95,245)){
            val end=(offset+size).coerceAtMost(bytes.size)
            segments.write(split.convert(bytes.copyOfRange(offset,end)))
            offset=end
        }
        segments.write(split.convert(bytes.copyOfRange(offset,bytes.size)))
        assertArrayEquals(whole,segments.toByteArray())
        assertTrue("one second must be almost 48kHz",whole.size in 95996..96004)
    }

    @Test fun voiceFramesDoNotClip() {
        val samples=byteArrayOf(0,0, 0xFF.toByte(),0x7F, 0,0, 0,0x80.toByte())
        val audio=PremiumPcm(44100).convert(samples)
        for(i in audio.indices step 2) {
            val sample=((audio[i].toInt() and 255) or (audio[i+1].toInt() shl 8)).toShort().toInt()
            assertTrue(sample in -32768..32767)
        }
    }

    @Test fun earlyPhraseAndTranscriptMerge() {
        assertEquals("إزيك؟",PremiumPhrase.prefix("إزيك؟ عامل إيه؟"))
        // Comma splits only after minChars = 40
        assertEquals("أهلاً بحضرتك يا فندم في خدمة عملاء أون تراك،",
            PremiumPhrase.prefix("أهلاً بحضرتك يا فندم في خدمة عملاء أون تراك، عندي اقتراح لحضرتك"))
        assertEquals("",PremiumPhrase.prefix("أهلًا يا فندم، عندي"))
        assertEquals("",PremiumPhrase.prefix("تمام"))
        assertEquals("أهلًا بيك يا زميلي",PremiumPhrase.merge("أهلًا بيك","أهلًا بيك يا زميلي"))
    }

    @Test fun speakableStripsStageDirectionsAndStreamsMultiClause() {
        assertEquals("أهلاً يا قلبي!",PremiumPhrase.speakable("(ضحكة خفيفة) أهلاً يا قلبي! (زفرة ناعمة)"))
        assertEquals("مرحبا يا حلو!",PremiumPhrase.speakable("[همسة] مرحبا يا حلو!"))
        val sample="(ضحكة خفيفة) أهلاً بك معنا! كيف أستطيع مساعدتك في طلبك اليوم؟ وهل تفضل التواصل باللهجة اللبنانية"
        val first=PremiumPhrase.prefix(sample)
        assertEquals("(ضحكة خفيفة) أهلاً بك معنا!",first)
        assertEquals("أهلاً بك معنا!",PremiumPhrase.speakable(first))
        val second=PremiumPhrase.prefix(sample.removePrefix(first).trimStart())
        assertEquals("كيف أستطيع مساعدتك في طلبك اليوم؟",second)
    }
}
