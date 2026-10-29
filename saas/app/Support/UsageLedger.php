<?php

namespace App\Support;

use App\Models\PlanEntitlement;
use App\Models\Subscription;
use App\Models\TenantUsageLedger;

final class UsageLedger
{
    public static function entitlement(int $tenantId, string $feature): ?PlanEntitlement
    {
        $subscription = Subscription::where('tenant_id', $tenantId)->where('status', 'active')->latest('id')->first();
        if (! $subscription) return null;
        return PlanEntitlement::where('plan_id', $subscription->plan_id)->where('feature', $feature)->first();
    }

    public static function authorize(int $tenantId, string $feature, int $units = 1): bool
    {
        $entitlement = self::entitlement($tenantId, $feature);
        if (! $entitlement || $entitlement->quota <= 0) return false;
        $periodStart = now()->startOfMonth();
        $used = TenantUsageLedger::where('tenant_id', $tenantId)
            ->where('kind', $feature)
            ->where('occurred_at', '>=', $periodStart)
            ->sum('units');
        return ($used + $units) <= $entitlement->quota;
    }

    public static function record(int $tenantId, string $kind, int $units, string $idempotencyKey, ?string $refType = null, ?int $refId = null): TenantUsageLedger
    {
        $existing = TenantUsageLedger::where('tenant_id', $tenantId)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) return $existing;
        return TenantUsageLedger::create([
            'tenant_id' => $tenantId,
            'kind' => $kind,
            'units' => max(1, $units),
            'ref_type' => $refType,
            'ref_id' => $refId,
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => now(),
        ]);
    }

    public static function usedThisMonth(int $tenantId, string $kind): int
    {
        return (int) TenantUsageLedger::where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->where('occurred_at', '>=', now()->startOfMonth())
            ->sum('units');
    }
}