<?php

namespace App\Services\Support;

final class ReviewerVerdict
{
    /**
     * Conducts mandatory pre-publication review on a proposed AI draft.
     *
     * @param string $draftText
     * @param array $context ['catalog_items' => array, 'client_name' => string, 'is_public' => bool]
     * @return array{verdict: 'approved'|'modified'|'rejected', cleaned_text: string, reasons: array, confidence: float}
     */
    public static function evaluate(string $draftText, array $context = []): array
    {
        $reasons = [];
        $confidence = 0.95;

        // 1. Redact credentials
        $redaction = Security::redactSensitiveText($draftText);
        $cleaned = $redaction['text'];
        if ($redaction['detected']) {
            $reasons[] = "Redacted {$redaction['count']} instances of sensitive credentials.";
            $confidence -= 0.10;
        }

        // 2. Audit catalog claims if present
        $catalogItems = (array)($context['catalog_items'] ?? []);
        $audit = CatalogEvidenceGate::auditClaims($cleaned, $catalogItems);
        if (!$audit['grounded']) {
            $reasons = array_merge($reasons, $audit['violations']);
            return [
                'verdict' => 'rejected',
                'cleaned_text' => $cleaned,
                'reasons' => $reasons,
                'confidence' => 0.40,
            ];
        }

        // 3. Hallucination and Safety Rules
        $forbiddenKeywords = ['root password', 'su -', 'mysql -u root', 'drop database'];
        foreach ($forbiddenKeywords as $kw) {
            if (stripos($cleaned, $kw) !== false) {
                return [
                    'verdict' => 'rejected',
                    'cleaned_text' => $cleaned,
                    'reasons' => ["Draft references prohibited command syntax: {$kw}"],
                    'confidence' => 0.20,
                ];
            }
        }

        $verdict = $redaction['detected'] ? 'modified' : 'approved';

        return [
            'verdict' => $verdict,
            'cleaned_text' => $cleaned,
            'reasons' => $reasons,
            'confidence' => max(0.50, $confidence),
        ];
    }
}
