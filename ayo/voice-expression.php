<?php
declare(strict_types=1);
/**
 * AYO demo-only voice expression policy. Does not call an LLM.
 * Inworld TTS-2 supports synthesisContext.reasoning = REASONING_ON.
 * Inline non-verbals work on TTS-2 and TTS-2 Flash; steering only on TTS-2.
 */
function ayo_expression_cue(string $text): string {
    if (preg_match('/(?:للأسف|معلش|آسفين|اسفين|بعتذر|بنعتذر|حقك علينا|مضايق|زعلان|متضايق|محزن|خسارة)/u', $text)) return 'sigh';
    if (preg_match('/(?:هههه|نكتة|هزار|ضحكت|بيضحك|مضحك|طريف|خفة دم)/u', $text)) return 'chuckle';
    if (preg_match('/(?:يا نهار|مش معقول|مفاجأة|يا سلام|مذهل|واو)/u', $text)) return 'gasp';
    if (preg_match('/(?:أوف|اوف|آه يا|ااه يا)/u', $text)) return 'groan';
    return '';
}
function ayo_expression_prepare(string $text, string $mode, string $model, string $callerText=''): array {
    if (!in_array($mode, ['off', 'auto', 'expressive'], true)) $mode = 'auto';
    $synthesis = $text;
    $cue = '';
    $reasoning = $mode !== 'off' && $model === 'inworld-tts-2';
    $hasCue = preg_match('/\\[(?:laugh|chuckle|giggle|breathe|sigh|gasp|groan|moan|cough|yawn|clear throat)\\]/iu', $text);
    if ($mode === 'expressive' && !$hasCue) {
        $cue = ayo_expression_cue($text);
        if ($cue !== '') $synthesis = '['.$cue.'] '.$text;
    }
    $context = null;
    if ($reasoning) {
        $contextText = 'AYO is a friendly Egyptian Arabic sales and support assistant. Speak naturally in Egyptian Arabic, with human warmth and clear pacing. Match the emotional intent of the words, avoid exaggerated drama or inappropriate reactions.';
        if ($callerText !== '') {
            $snippet = preg_replace('/[\\[\\]<>\\r\\n]+/u', ' ', $callerText) ?? '';
            preg_match_all('/./us', $snippet, $letters);
            $contextText .= ' Customer said: '.implode('', array_slice($letters[0] ?? [], 0, 170));
        }
        $context = ['reasoning' => 'REASONING_ON', 'reasoningContext' => $contextText];
    }
    return ['text' => $synthesis, 'synthesisContext' => $context, 'reasoningEnabled' => $reasoning, 'nonverbalCue' => $cue, 'mode' => $mode];
}
