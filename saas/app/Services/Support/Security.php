<?php

namespace App\Services\Support;

final class Security
{
    private const SENSITIVE_PATTERNS = [
        '/(password|passwd|pwd|secret|token|api[_\-]?key|auth[_\-]?key|private[_\-]?key)\s*[:=]\s*["\']?([^"\'\s\r\n]{4,})/iu',
        '/\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|6(?:011|5[0-9]{2})[0-9]{12})\b/',
        '/bearer\s+[a-zA-Z0-9_\-\.]{15,}/i',
        '/-----BEGIN\s+(?:RSA\s+)?PRIVATE\s+KEY-----[\s\S]*?-----END\s+(?:RSA\s+)?PRIVATE\s+KEY-----/i',
    ];

    /**
     * Redacts passwords, API keys, tokens, and credit card numbers from raw text.
     *
     * @return array{text: string, detected: bool, count: int}
     */
    public static function redactSensitiveText(string $text): array
    {
        if (trim($text) === '') {
            return ['text' => '', 'detected' => false, 'count' => 0];
        }

        $detected = false;
        $count = 0;
        $cleaned = $text;

        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            $matched = preg_match_all($pattern, $cleaned);
            if ($matched) {
                $detected = true;
                $count += $matched;
                $cleaned = preg_replace_callback($pattern, function ($matches) {
                    if (isset($matches[2])) {
                        return str_replace($matches[2], '[REDACTED_CREDENTIAL]', $matches[0]);
                    }
                    return '[REDACTED_SENSITIVE]';
                }, $cleaned) ?? $cleaned;
            }
        }

        return ['text' => $cleaned, 'detected' => $detected, 'count' => $count];
    }

    /**
     * Recursively redacts sensitive strings in complex nested arrays.
     */
    public static function redactSensitiveRecursive(mixed $data): mixed
    {
        if (is_string($data)) {
            return self::redactSensitiveText($data)['text'];
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                if (is_string($key) && preg_match('/(password|secret|token|private|api_key)/i', $key)) {
                    $result[$key] = '[REDACTED_KEY]';
                } else {
                    $result[$key] = self::redactSensitiveRecursive($value);
                }
            }
            return $result;
        }

        return $data;
    }
}
