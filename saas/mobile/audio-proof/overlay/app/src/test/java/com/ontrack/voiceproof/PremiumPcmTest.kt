package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test
import java.io.ByteArrayOutputStream

class PremiumPcmTest {
    @Test fun chunkBoundaryAndOddByteInvariant() {
        val samples=44100
        val bytes=ByteArray(samples*2) { i-> if(i%2==0) 0x20.toByte() else 0x03 }
        val whole=PremiumPcm().convert(bytes)
        val split=PremiumPcm()
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
        val audio=PremiumPcm().convert(samples)
        for(i in audio.indices step 2) {
            val sample=((audio[i].toInt() and 255) or (audio[i+1].toInt() shl 8)).toShort().toInt()
            assertTrue(sample in -32768..32767)
        }
    }
    @Test fun earlyPhraseAndTranscriptMerge() {
        assertEquals("إزيك؟",PremiumPhrase.prefix("إزيك؟ عامل إيه؟"))
        // A comma no longer produces a separate Fish HTTP request/gap.
        assertEquals("",PremiumPhrase.prefix("أهلًا يا فندم، عندي اقتراح"))
        assertEquals("أهلًا يا فندم، عندي اقتراح واضح؟",
            PremiumPhrase.prefix("أهلًا يا فندم، عندي اقتراح واضح؟"))
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
