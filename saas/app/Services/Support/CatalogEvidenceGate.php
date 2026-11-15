<?php

namespace App\Services\Support;

final class CatalogEvidenceGate
{
    /**
     * Validates that price or plan claims in a draft response match catalog records.
     *
     * @param string $draftResponse
     * @param array $catalogItems List of valid product/pricing items
     * @return array{grounded: bool, violations: array, unverified_prices: array}
     */
    public static function auditClaims(string $draftResponse, array $catalogItems = []): array
    {
        $violations = [];
        $unverifiedPrices = [];

        // Match currency figures (e.g. $19.99, 150 EGP, 500 جنيه, 20 USD)
        preg_match_all('/(\$|\bEGP\b|\bUSD\b|\bSAR\b|\bجنيه\b|\bريال\b)\s*([0-9]+(?:\.[0-9]{1,2})?)/iu', $draftResponse, $matches, PREG_SET_ORDER);
        preg_match_all('/([0-9]+(?:\.[0-9]{1,2})?)\s*(\$|\bEGP\b|\bUSD\b|\bSAR\b|\bجنيه\b|\bريال\b)/iu', $draftResponse, $matches2, PREG_SET_ORDER);

        $detectedAmounts = [];
        foreach (array_merge($matches, $matches2) as $m) {
            $amt = (float)(is_numeric($m[1]) ? $m[1] : $m[2]);
            if ($amt > 0) {
                $detectedAmounts[] = $amt;
            }
        }
        $detectedAmounts = array_unique($detectedAmounts);

        if (empty($catalogItems) && !empty($detectedAmounts)) {
            return [
                'grounded' => false,
                'violations' => ['Financial figures were mentioned without an authoritative catalog provided in context.'],
                'unverified_prices' => $detectedAmounts,
            ];
        }

        $validPrices = [];
        foreach ($catalogItems as $item) {
            if (isset($item['price'])) {
                $validPrices[] = (float)$item['price'];
            }
            if (isset($item['monthly_price'])) {
                $validPrices[] = (float)$item['monthly_price'];
            }
            if (isset($item['annual_price'])) {
                $validPrices[] = (float)$item['annual_price'];
            }
        }
        $validPrices = array_unique($validPrices);

        foreach ($detectedAmounts as $amt) {
            $isKnown = false;
            foreach ($validPrices as $v) {
                if (abs($v - $amt) < 0.05) {
                    $isKnown = true;
                    break;
                }
            }
            if (!$isKnown) {
                $unverifiedPrices[] = $amt;
            }
        }

        if (!empty($unverifiedPrices)) {
            $violations[] = 'Detected unverified pricing claims: ' . implode(', ', $unverifiedPrices);
        }

        return [
            'grounded' => empty($violations),
            'violations' => $violations,
            'unverified_prices' => $unverifiedPrices,
        ];
    }
}
