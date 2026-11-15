<?php

namespace App\Services\Support;

final class UnifiedSupportKernel
{
    /**
     * Determines the consultation plan based on ticket content and classification.
     *
     * @param string $subject
     * @param string $message
     * @param int $maxConsultations
     * @return array List of planned consultations
     */
    public static function planConsultations(string $subject, string $message, int $maxConsultations = 3): array
    {
        $text = mb_strtolower($subject . ' ' . $message, 'UTF-8');
        $plan = [];

        // Check for multiple hosting tiers inquiries
        $mentionsShared = str_contains($text, 'shared') || str_contains($text, 'مشتركة') || str_contains($text, 'استضافة');
        $mentionsVps = str_contains($text, 'vps') || str_contains($text, 'سيرفر سحابي') || str_contains($text, 'افتراضي');
        $mentionsDedicated = str_contains($text, 'dedicated') || str_contains($text, 'مخصص') || str_contains($text, 'سيرفر كامل');

        $salesCategories = 0;
        if ($mentionsShared) $salesCategories++;
        if ($mentionsVps) $salesCategories++;
        if ($mentionsDedicated) $salesCategories++;

        if ($salesCategories >= 2) {
            if ($mentionsShared && count($plan) < $maxConsultations) {
                $plan[] = ['slug' => 'sales-agent', 'focus' => 'shared_hosting', 'query' => 'استشارة مواصفات وأسعار الاستضافة المشتركة'];
            }
            if ($mentionsVps && count($plan) < $maxConsultations) {
                $plan[] = ['slug' => 'vps-sales-agent', 'focus' => 'vps', 'query' => 'استشارة باقات ومواصفات سيرفرات VPS'];
            }
            if ($mentionsDedicated && count($plan) < $maxConsultations) {
                $plan[] = ['slug' => 'dedicated-sales-agent', 'focus' => 'dedicated', 'query' => 'استشارة السيرفرات الكاملة المخصصة'];
            }
            return $plan;
        }

        // Technical / Diagnostic checks
        if (str_contains($text, 'cpanel') || str_contains($text, '500') || str_contains($text, 'memory') || str_contains($text, 'server')) {
            $plan[] = ['slug' => 'server-agent', 'focus' => 'server', 'query' => 'فحص كفاءة واستهلاك الخادم'];
        }

        if (str_contains($text, 'dns') || str_contains($text, 'nameserver') || str_contains($text, 'domain')) {
            $plan[] = ['slug' => 'dns-agent', 'focus' => 'dns', 'query' => 'فحص ربط النطاق وتوجيهات DNS'];
        }

        if (empty($plan) && $salesCategories === 1) {
            $plan[] = ['slug' => 'sales-agent', 'focus' => 'sales', 'query' => 'استشارة مبيعات مباشرة'];
        }

        return array_slice($plan, 0, $maxConsultations);
    }
}
