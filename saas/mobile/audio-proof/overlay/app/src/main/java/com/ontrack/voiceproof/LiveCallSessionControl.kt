package com.ontrack.voiceproof

import java.util.ArrayDeque
import java.util.concurrent.atomic.AtomicInteger

/**
 * Atomic generation gate for hard barge-in flushing across capture, TTS,
 * and PCM bridge layers.
 */
internal class BargeInGenerationController {
    private val generation = AtomicInteger(0)

    fun current(): Int = generation.get()

    fun bump(): Int = generation.incrementAndGet()

    fun isCurrent(packetGeneration: Int): Boolean = packetGeneration == generation.get()

    fun shouldPlay(packetGeneration: Int): Boolean = packetGeneration == generation.get()
}

/**
 * Process-wide mutex ensuring at most one active MobileLiveCall owns
 * the muted SIM PCM conduit and WebSocket session at a time.
 */
internal object ActiveCallSessionMutex {
    private val lock = Any()
    private var activeToken = 0L
    private var activeStop: (() -> Unit)? = null

    fun acquire(stopHandler: () -> Unit): Long {
        val previous: (() -> Unit)?
        val token: Long
        synchronized(lock) {
            previous = activeStop
            activeToken += 1L
            token = activeToken
            activeStop = stopHandler
        }
        if (previous != null) {
            runCatching { previous.invoke() }
        }
        return token
    }

    fun release(token: Long) {
        synchronized(lock) {
            if (activeToken == token) {
                activeStop = null
            }
        }
    }

    fun isActive(token: Long): Boolean = synchronized(lock) {
        activeToken == token && activeStop != null
    }
}

/**
 * Tracks sticky caller gender (male -> female switch with permanent female lock),
 * extracted rating/name, and bounded sliding conversation turns (max 6) so
 * WebSocket reconnects or key failover preserve live context cleanly.
 */
internal class CallMemoryTracker(private val maxTurns: Int = 6) {
    private val lock = Any()
    private val turns = ArrayDeque<String>()
    private val rollingCaller = StringBuilder()

    @Volatile var gender: String = "unknown"
        private set
    @Volatile var genderLocked: Boolean = false
        private set
    @Volatile var callerName: String = ""
        private set
    @Volatile var rating: String = ""
        private set

    private val tatweelAndMarks = Regex("[\u0640\u064B-\u0652]")

    private val explicitFemaleRegex = Regex(
        """(?:^|\s)(?:أنا|انا)\s+(?:بنت|واحدة|واحده|ست|مدام|آنسة|انسه|أنثى|انثى|سيدة|سيده|عميلة|عميله|اختك|أختك|صبية|صبيه|حرم)(?=\s|$|[.!؟،])"""
    )
    private val femaleNameRegex = Regex(
        """(?:اسمي|اسمى|معاك|معاكي|انا|أنا)\s+(سارة|ساره|مريم|نور|نورة|هدى|منى|دينا|ريم|ليلى|لينا|رنا|مي|ندى|هبة|هبه|آية|ايه|زينب|مها|جنى|ملك|فاطمة|فاطمه|سلمى|نسمة|ياسمين|أميرة|اميره|شيماء|خلود|نهى|سها|علا|لمياء|رانيا|هند|غادة|إيمان|ايمان|نانسي|ديانا|تالا|جوري|يارا|هلا|شهد|داليا|سمر|مروة|جيهان|صفاء)(?=\s|$|[.!؟،])"""
    )
    private val femaleVerbAdjRegex = Regex(
        """(?:^|\s)(?:مبسوطة|فرحانة|زعلانة|متضايقة|حابة|عاوزة|عايزة|محتاجة|مستنية|مرتاحة|راجعتلك|رجعتلك|بكلمك|موافقة|متأكدة|مقدرة|سعيدة|مستعدة|رايحة|جاية|شايفة|حاسة|متفهمة|مهتمة|طالبة|قاصدة)(?=\s|$|[.!؟،])"""
    )

    private val explicitMaleRegex = Regex(
        """(?:^|\s)(?:أنا|انا)\s+(?:شاب|راجل|رجل|واحد|عميل|اخوك|أخوك|استاذ|أستاذ)(?=\s|$|[.!؟،])"""
    )
    private val maleNameRegex = Regex(
        """(?:اسمي|اسمى|معاك|انا|أنا)\s+(أحمد|احمد|محمد|علي|على|عمر|كريم|مصطفى|خالد|طارق|يوسف|حسن|حسين|ماجد|وليد|عمرو|إسلام|اسلام|رامي|تامر|هاني|شريف|أشرف|اشرف|سامح|باسم|وائل|نادر|زياد|مازن|آدم|ادم|بهاء|زياد|ياسر|حاتم|محمود|إبراهيم|ابراهيم)(?=\s|$|[.!؟،])"""
    )
    private val maleVerbAdjRegex = Regex(
        """(?:^|\s)(?:مبسوط|فرحان|زعلان|متضايق|عاوز|عايز|محتاج|مستني|مرتاح|موافق|متأكد|سعيد|مستعد|رايح|جاي|شايف|حاسس|مهتم|طالب)(?=\s|$|[.!؟،])"""
    )

    private val explicitRatingRegex = Regex(
        """(?:تقييم(?:ي)?|أعطيكم|بعطي|أدي|هدي|نجم|نجوم|من\s*عشرة|من\s*10|\b[1-9]|10)\s*(?:من\s*(?:عشرة|10))?"""
    )
    private val digitRatingRegex = Regex("""\b(10|[1-9])\s*(?:/|من\s*(?:10|عشرة)|نجم|نجوم)?\b""")

    fun observeCaller(text: String) {
        val raw = text.trim()
        if (raw.isBlank()) return
        synchronized(lock) {
            appendTurnLocked("المتصل", raw)
            if (rollingCaller.isNotEmpty()) rollingCaller.append(' ')
            rollingCaller.append(raw)
            if (rollingCaller.length > 260) {
                rollingCaller.delete(0, rollingCaller.length - 260)
            }
            val normalized = tatweelAndMarks.replace(rollingCaller.toString(), "")

            val femaleName = femaleNameRegex.find(normalized)?.groupValues?.getOrNull(1)?.trim()
            if (!femaleName.isNullOrBlank()) {
                callerName = femaleName
            }
            val femaleDetected = explicitFemaleRegex.containsMatchIn(normalized) ||
                !femaleName.isNullOrBlank() ||
                femaleVerbAdjRegex.containsMatchIn(normalized)

            if (femaleDetected) {
                gender = "female"
                genderLocked = true
            } else if (!genderLocked) {
                val maleName = maleNameRegex.find(normalized)?.groupValues?.getOrNull(1)?.trim()
                if (!maleName.isNullOrBlank() && callerName.isBlank()) {
                    callerName = maleName
                }
                if (explicitMaleRegex.containsMatchIn(normalized) ||
                    !maleName.isNullOrBlank() ||
                    maleVerbAdjRegex.containsMatchIn(normalized)) {
                    gender = "male"
                }
            }

            if (normalized.contains("تقييم") || normalized.contains("عشرة") ||
                normalized.contains("نجوم") || normalized.contains("نجم") ||
                normalized.contains("/10") || explicitRatingRegex.containsMatchIn(normalized)) {
                digitRatingRegex.findAll(normalized).lastOrNull()?.groupValues?.getOrNull(1)?.let { found ->
                    rating = "$found/10"
                }
            }
        }
    }

    fun observeAssistant(text: String) {
        val raw = text.trim()
        if (raw.isBlank()) return
        synchronized(lock) {
            appendTurnLocked("الإيجنت", raw)
        }
    }

    private fun appendTurnLocked(speaker: String, chunk: String) {
        val prefix = "$speaker: "
        val last = turns.peekLast()
        if (last != null && last.startsWith(prefix) && last.length < 220) {
            turns.removeLast()
            val sep = if (last.endsWith(" ")) "" else " "
            turns.addLast((last + sep + chunk).take(260))
        } else {
            turns.addLast((prefix + chunk).take(260))
        }
        while (turns.size > maxTurns) {
            turns.removeFirst()
        }
    }

    fun recentTurnsSnapshot(): List<String> = synchronized(lock) {
        turns.toList()
    }

    fun genderInstructionLine(): String = when {
        genderLocked || gender == "female" ->
            "تذكير إجباري بالهوية: المتصلة أنثى ومقفولة على صيغة المؤنث لنهاية المكالمة — التزم حصرًا بخطاب المؤنث (يا حلوة، يا عيوني إنتي، حبيبتي، إنتي، كرمالي إنتي) ولا ترجع لصيغة المذكر أبدًا."
        gender == "male" ->
            "حالة المخاطبة: المتصل حاليًا بصيغة المذكر (يا حلو، يا قلبي، إنت)، ولكن إن تبين في أي لحظة أنه أنثى فانتقل فورًا لصيغة المؤنث واقفلها لنهاية المكالمة."
        else ->
            "حالة المخاطبة: راقب نبرة وصيغة المتصل؛ وعند أول إشارة أنثوية انتقل فورًا لصيغة المؤنث واقفلها لنهاية المكالمة."
    }

    fun buildReconnectSummary(): String = synchronized(lock) {
        val sb = StringBuilder()
        sb.append(genderInstructionLine())
        if (callerName.isNotBlank()) {
            sb.append("\nاسم المتصل المذكور: ").append(callerName)
        }
        if (rating.isNotBlank()) {
            sb.append("\nالتقييم المذكور سابقًا: ").append(rating).append(" (لا تعيد السؤال عن التقييم)")
        }
        if (turns.isNotEmpty()) {
            sb.append("\nآخر أدوار الحوار المحفوظة:\n")
            for (line in turns) {
                sb.append(line).append('\n')
            }
        }
        sb.toString().trim().take(1200)
    }
}

