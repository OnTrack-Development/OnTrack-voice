<?php
declare(strict_types=1);

/**
 * Sanitize diagnostic values from an untrusted upstream.
 * Keep actionable status/transport context without echoing access tokens.
 */
function ontrack_diag_scrub(string $message, string $apiKey = ''): string {
    if ($apiKey !== '') $message = str_replace($apiKey, '[REDACTED]', $message);
    $patterns = [
        '/\bAIza[0-9A-Za-z_\\-]{20,}\b/',
        '/\b(?:github_pat_|gh[pousr]_)[A-Za-z0-9_]+\b/',
        '/\b(?:sk-|omnira_sk_)[A-Za-z0-9_.\\-]{12,}\b/',
        '/\bBearer\s+[^\s,;"\']+/i',
        '/\b(?:api[_-]?key|access[_-]?token|authorization|password|secret)\s*[=:]\s*[^\s,;"\']+/i',
    ];
    foreach ($patterns as $pattern) $message = preg_replace($pattern, '[REDACTED]', $message) ?? '[REDACTED]';
    return mb_substr($message, 0, 160, 'UTF-8');
}
function ontrack_diag_transport_error(string $message): ?string {
    if ($message === '') return null;
    $message = strtolower($message);
    if (str_contains($message, 'timed out')) return 'timeout';
    if (str_contains($message, 'ssl') || str_contains($message, 'certificate')) return 'tls_failure';
    if (str_contains($message, 'resolve')) return 'dns_failure';
    if (str_contains($message, 'write')) return 'response_too_large_or_write_failed';
    return 'transport_failure';
}
