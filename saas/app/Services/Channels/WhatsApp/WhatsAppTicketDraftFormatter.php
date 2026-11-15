<?php

namespace App\Services\Channels\WhatsApp;

final class WhatsAppTicketDraftFormatter
{
    /**
     * Composes a clear title and description from raw customer input.
     *
     * @param string $rawInput
     * @return array{subject: string, message: string}
     */
    public static function compose(string $rawInput): array
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $rawInput) ?? $rawInput);
        $length = mb_strlen($clean, 'UTF-8');

        if ($length <= 50) {
            $subject = $clean;
        } else {
            $words = explode(' ', $clean);
            $subjectWords = array_slice($words, 0, 7);
            $subject = implode(' ', $subjectWords) . '...';
        }

        return [
            'subject' => $subject,
            'message' => $clean,
        ];
    }
}
