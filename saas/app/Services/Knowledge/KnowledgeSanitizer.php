<?php

namespace App\Services\Knowledge;

use App\Services\Support\Security;

final class KnowledgeSanitizer
{
    /**
     * Sanitizes raw ticket resolutions or conversation texts into general reusable knowledge patterns.
     */
    public static function sanitize(string $content): string
    {
        // 1. Redact credentials & sensitive numbers
        $redacted = Security::redactSensitiveText($content)['text'];

        // 2. Strip personal customer identifiers (emails, phone numbers, client names)
        $clean = preg_replace('/[a-zA-Z0-9_\.\+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-\.]+/u', '[CUSTOMER_EMAIL]', $redacted) ?? $redacted;
        $clean = preg_replace('/(?:\+?20|0)?1[0125][0-9]{8}\b/', '[CUSTOMER_PHONE]', $clean) ?? $clean;
        $clean = preg_replace('/\b(?:1[0-9]{2}|2[0-4][0-9]|25[0-5]|[1-9]?[0-9])\.(?:1[0-9]{2}|2[0-4][0-9]|25[0-5]|[1-9]?[0-9])\.(?:1[0-9]{2}|2[0-4][0-9]|25[0-5]|[1-9]?[0-9])\.(?:1[0-9]{2}|2[0-4][0-9]|25[0-5]|[1-9]?[0-9])\b/', '[SERVER_IP]', $clean) ?? $clean;

        return trim($clean);
    }
}
