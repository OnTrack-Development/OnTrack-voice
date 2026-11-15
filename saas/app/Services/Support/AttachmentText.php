<?php

namespace App\Services\Support;

final class AttachmentText
{
    /**
     * Extracts and sanitizes content from text/log attachments.
     */
    public static function extract(string $filePath): string
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return '';
        }

        $size = filesize($filePath);
        if ($size === false || $size === 0) {
            return '';
        }

        // Limit to 64KB for safety
        $content = file_get_contents($filePath, false, null, 0, 65536);
        if ($content === false) {
            return '';
        }

        // Clean UTF-8 and redact credentials
        $clean = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        return Security::redactSensitiveText($clean)['text'];
    }
}
