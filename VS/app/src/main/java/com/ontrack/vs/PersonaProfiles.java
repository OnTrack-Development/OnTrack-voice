package com.ontrack.vs;

/**
 * Fish Drama 3 performance directions for the user's exact words.
 * Use only conservative natural-language tags; never invent laughter or a sigh.
 * The intensity is user-controlled and defaults to soft guidance.
 */
public final class PersonaProfiles {
    public static final int COUNT = 3;
    public static final String[] NAMES = {
        "١ — روشنة ومرقعة",
        "٢ — حنية واشتياق",
        "٣ — دراما وتنهيدات"
    };
    public static final String[] DESCRIPTIONS = {
        "مرِح وخفيف دم، من غير صريخ أو ضحك مصطنع.",
        "نبرة دافئة وهادية، من غير مبالغة في الاشتياق.",
        "إحساس متأثر وهادئ، من غير تنهيدات مفاجئة."
    };
    private static final String[] SOFT = {
        "[friendly and conversational, a subtle playful warmth, natural Egyptian Arabic, no forced laughter or shouting]",
        "[warm and gentle, a little longing, natural Egyptian Arabic, no exaggerated emotion]",
        "[quietly emotional and sincere, natural Egyptian Arabic, no dramatic gasps or forced sighs]"
    };
    private static final String[] MEDIUM = {
        "[warm and playfully amused but not loud, natural Egyptian Arabic, no forced laughter]",
        "[tender and softly affectionate, a little wistful but not theatrical, natural Egyptian Arabic]",
        "[sincere and slightly emotional, restrained dramatic delivery without sighs, natural Egyptian Arabic]"
    };
    private static final String[] SAMPLES = {
        "إزيك يا زميلي، عامل إيه النهارده؟",
        "وحشتني والله، طمني عليك، أخبارك إيه؟",
        "بص، أنا عايز أقولك حاجة مهمة."
    };
    private PersonaProfiles() {}

    public static int validIndex(int index) { return index >= 0 && index < COUNT ? index : 0; }
    public static int validIntensity(int intensity) { return Math.max(0, Math.min(2, intensity)); }
    public static String sample(int index) { return SAMPLES[validIndex(index)]; }

    /** 0: plain text; 1: gentle direction (default); 2: controlled expression. */
    public static String forTts(String original, int index, int intensity) {
        if (original == null) return "";
        String speech = original.trim();
        if (speech.isEmpty()) return "";
        // A custom direction in the typed text wins; don't apply two directions.
        if (speech.startsWith("[") || speech.startsWith("<")) return speech;
        int level = validIntensity(intensity);
        if (level == 0 || speech.length() < 12) return speech;
        return (level == 1 ? SOFT : MEDIUM)[validIndex(index)] + " " + speech;
    }

    public static String forTts(String original, int index) {
        return forTts(original, index, 1);
    }
}
