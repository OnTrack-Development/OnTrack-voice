<?php

namespace App\Services\Billing;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\PlanEntitlement;
use App\Models\TenantUsageLedger;
use Illuminate\Support\Facades\Cache;

final class FeatureGateService
{
    private const DEFAULT_LIMITS = [
        'voice_calls_minutes' => 500,
        'whatsapp_official_channels' => 2,
        'whatsapp_qr_channels' => 3,
        'whatsapp_dynamic_buttons' => 1,
        'multi_agent_consultations' => 3,
        'attachment_vision_scans' => 250,
        'cpanel_deep_diagnostics' => 5,
        'knowledge_auto_learning' => 1,
        'connected_bridges_limit' => 3,
    ];

    /**
     * Checks if a specific feature is allowed for the account or tenant.
     */
    public function allows(Account|Tenant $subject, string $featureKey): bool
    {
        $plan = $subject->plan ?? null;
        if (!$plan) {
            // Internal testing or default allowance
            return true;
        }

        $cacheKey = "gate_allow_{$subject->id}_{$featureKey}";
        return Cache::remember($cacheKey, 120, function () use ($plan, $featureKey) {
            $entitlement = PlanEntitlement::where('plan_id', $plan->id)
                ->where('feature', $featureKey)
                ->first();

            if (!$entitlement) {
                return isset(self::DEFAULT_LIMITS[$featureKey]);
            }

            return (int)$entitlement->quota !== 0;
        });
    }

    /**
     * Consumes a specific amount of feature units and verifies quota.
     *
     * @throws \Exception If quota is exceeded
     */
    public function consume(Account|Tenant $subject, string $featureKey, int $units = 1): void
    {
        $plan = $subject->plan ?? null;
        if (!$plan) {
            $this->recordUsage($subject, $featureKey, $units);
            return;
        }

        $limit = $this->limitFor($subject, $featureKey);
        if ($limit === -1) {
            // Unlimited
            $this->recordUsage($subject, $featureKey, $units);
            return;
        }

        $currentUsage = (int)TenantUsageLedger::where('tenant_id', $subject->id)
            ->where('feature', $featureKey)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('units');

        if (($currentUsage + $units) > $limit) {
            throw new \Exception("تم استنفاد الحصة الشهرية للميزة [{$featureKey}]. الحد المسموح: {$limit}، المستهلك: {$currentUsage}");
        }

        $this->recordUsage($subject, $featureKey, $units);
    }

    /**
     * Returns the configured numeric limit for a feature.
     */
    public function limitFor(Account|Tenant $subject, string $featureKey, int $default = 0): int
    {
        $plan = $subject->plan ?? null;
        if (!$plan) {
            return self::DEFAULT_LIMITS[$featureKey] ?? $default;
        }

        $entitlement = PlanEntitlement::where('plan_id', $plan->id)
            ->where('feature', $featureKey)
            ->first();

        if ($entitlement) {
            return (int)$entitlement->quota;
        }

        return self::DEFAULT_LIMITS[$featureKey] ?? $default;
    }

    private function recordUsage(Account|Tenant $subject, string $featureKey, int $units): void
    {
        if ($units <= 0) return;

        try {
            TenantUsageLedger::create([
                'tenant_id' => $subject->id,
                'feature' => $featureKey,
                'units' => $units,
                'recorded_at' => now(),
            ]);
        } catch (\Throwable $e) {}
    }
}
