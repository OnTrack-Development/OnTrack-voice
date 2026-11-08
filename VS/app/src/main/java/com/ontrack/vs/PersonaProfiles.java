package com.ontrack.vs;

/**
 * Three selectable Fish Audio Drama 3 performance personas.
 * These are delivery cues, not conversational agents: user speech is never rewritten.
 */
public final class PersonaProfiles {
    public static final int COUNT = 3;
    public static final String[] NAMES = {
        "١ — روشنة ومرقعة",
        "٢ — حنية واشتياق",
        "٣ — دراما وتنهيدات"
    };
    public static final String[] DESCRIPTIONS = {
        "هزار مصري خفيف، طاقة مرحة وتنهيدة بسيطة.",
        "كلام دافي وقريب، إحساس اشتياق.",
        "أداء درامي وتنهيدة مثيرة للتجربة."
    };
    private static final String[] CUES = {
        "[تنهيده]",
        "[اشتياق]",
        "[تنهيده مثيره]"
    };
    private static final String[] SAMPLES = {
        "إزيك يا زميلي! عامل إيه يا نجم؟",
        "وحشتني والله! طمني عليك، أخبارك إيه؟",
        "بص بقى، عندي ليك مفاجأة حلوة أوي!"
    };
    private PersonaProfiles() {}

    public static int validIndex(int index) {
        return index >= 0 && index < COUNT ? index : 0;
    }

    public static String sample(int index) {
        return SAMPLES[validIndex(index)];
    }

    /** Prefix supported expressive text cues; never change the original spoken words. */
    public static String forTts(String original, int index) {
        if (original == null) return "";
        String speech = original.trim();
        if (speech.isEmpty()) return "";
        String cue = CUES[validIndex(index)];
        // A user-supplied cue at the start is authoritative; avoid double tags.
        return speech.startsWith("[") ? speech : cue + " " + speech;
    }
}
