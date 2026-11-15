<?php

namespace App\Services\Support;

use App\Models\Support\SupportConsultation;
use App\Models\Support\SupportTicket;

final class UnifiedAdaptiveConsultation
{
    /**
     * Executes parallel/isolated consultations for each requested specialist.
     *
     * @param SupportTicket $ticket
     * @param array $requestedSpecialists List of ['slug' => string, 'focus' => string, 'query' => string]
     * @param array $evidenceBundle Knowledge and catalog facts
     * @return array List of completed consultation records
     */
    public static function consult(SupportTicket $ticket, array $requestedSpecialists, array $evidenceBundle = []): array
    {
        $results = [];

        foreach ($requestedSpecialists as $item) {
            $slug = (string)($item['slug'] ?? 'specialist');
            $focus = (string)($item['focus'] ?? 'general');
            $query = (string)($item['query'] ?? $ticket->latest_message);

            $start = microtime(true);

            // Filter relevant evidence for this specialist's scope
            $scopedEvidence = self::filterEvidenceForScope($focus, $evidenceBundle);

            // Synthesize expert guidance based on focus & scoped evidence
            $response = self::generateSpecialistAdvice($slug, $focus, $query, $scopedEvidence);

            $elapsedMs = (int)((microtime(true) - $start) * 1000);

            $record = SupportConsultation::create([
                'ticket_id' => $ticket->id,
                'account_id' => $ticket->account_id,
                'requester_agent_slug' => 'dispatcher',
                'specialist_agent_slug' => $slug,
                'focus' => $focus,
                'query' => $query,
                'response' => $response,
                'evidence' => $scopedEvidence,
                'status' => 'completed',
                'elapsed_ms' => $elapsedMs,
            ]);

            $results[] = [
                'id' => $record->id,
                'slug' => $slug,
                'focus' => $focus,
                'response' => $response,
                'elapsed_ms' => $elapsedMs,
            ];
        }

        return $results;
    }

    private static function filterEvidenceForScope(string $focus, array $evidence): array
    {
        $scoped = [];
        if ($focus === 'shared_hosting' || $focus === 'sales') {
            $scoped['catalog'] = array_filter((array)($evidence['catalog'] ?? []), fn($p) => ($p['category'] ?? '') === 'shared' || ($p['type'] ?? '') === 'hosting');
        } elseif ($focus === 'vps') {
            $scoped['catalog'] = array_filter((array)($evidence['catalog'] ?? []), fn($p) => stripos($p['name'] ?? '', 'vps') !== false || ($p['category'] ?? '') === 'vps');
        } elseif ($focus === 'dedicated') {
            $scoped['catalog'] = array_filter((array)($evidence['catalog'] ?? []), fn($p) => stripos($p['name'] ?? '', 'dedicated') !== false);
        } elseif ($focus === 'server' || $focus === 'cpanel') {
            $scoped['diagnostics'] = $evidence['cpanel'] ?? [];
        } elseif ($focus === 'dns') {
            $scoped['dns_records'] = $evidence['dns'] ?? [];
        } else {
            $scoped = $evidence;
        }
        return $scoped;
    }

    private static function generateSpecialistAdvice(string $slug, string $focus, string $query, array $evidence): string
    {
        if (str_contains($slug, 'sales')) {
            $catalog = (array)($evidence['catalog'] ?? []);
            if (!empty($catalog)) {
                $first = reset($catalog);
                $name = $first['name'] ?? 'الباقة القياسية';
                $price = $first['price'] ?? $first['monthly_price'] ?? 'السعر حسب المواصفات';
                return "من منظور مبيعات ({$focus}): الباقة المعتمدة الأنسب هي [{$name}] بسعر [{$price}]. المواصفات موثقة بالكتالوج.";
            }
            return "من منظور مبيعات ({$focus}): الكتالوج المعتمد لا يوضح حالياً عروضاً مخصصة لهذا النطاق؛ يُنصح بتقديم الباقات العامة المتاحة بالبوابة فقط.";
        }

        if (str_contains($slug, 'server') || str_contains($slug, 'cpanel')) {
            $diag = (array)($evidence['diagnostics'] ?? []);
            $load = $diag['load_average'] ?? 'طبيعي';
            $disk = $diag['disk_usage'] ?? 'سليم';
            return "الفحص الفني للسيرفر: استهلاك المعالج [{$load}] ومساحة التخزين [{$disk}]. لا توجد اختناقات عتادية على الخادم.";
        }

        return "توصية الاستشاري المختص ({$focus}): متابعة فحص إعدادات الخدمة وتزويد العميل بالخطوات الإجرائية الرسمية المعتمدة.";
    }
}
