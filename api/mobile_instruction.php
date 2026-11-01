<?php
declare(strict_types=1);

/**
 * Server-side policy overlay for the authenticated Android SIM pilot.
 * The client cannot set this text: only saas/mobile/server/session.php supplies it
 * after mobile_require_device() accepts the device bearer.
 */
function compose_mobile_live_instruction(string $base, ?string $mobileInstruction, bool $authenticatedDevice): string {
    if (!$authenticatedDevice || $mobileInstruction === null) return $base;
    $policy = trim($mobileInstruction);
    if ($policy === '') return $base;
    // Do not allow an accidentally huge persisted policy into the real-time session.
    $policy = mb_substr($policy, 0, 12000, 'UTF-8');
    return $base . "\n\n"
        . "سياسة الإيجنت المفعّلة على هاتف OnTrack لهذا الاتصال (تتقدم على اسم وشخصية الديمو العامة عند التعارض):\n"
        . $policy;
}
