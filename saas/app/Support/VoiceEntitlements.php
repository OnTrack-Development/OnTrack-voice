<?php
namespace App\Support;

use App\Models\Subscription;

final class VoiceEntitlements
{
    public const FEATURES = [
        'voice.campaigns','voice.contact_import','voice.call_history',
        'voice.agent_builder','voice.recording','voice.reports',
        'voice.ayo.calm','voice.ayo.excited','voice.ayo.sleepy',
    ];

    public static function has(int $tenantId, string $feature): bool
    {
        if (!in_array($feature, self::FEATURES, true)) return false;
        $sub = Subscription::query()->with('plan.entitlements')
            ->where('tenant_id', $tenantId)->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('renews_at')->orWhere('renews_at', '>', now());
            })->latest('id')->first();
        if (!$sub || !$sub->plan || $sub->plan->status !== 'active') return false;
        return $sub->plan->entitlements->contains(fn ($entitlement) =>
            $entitlement->feature === $feature && (int) $entitlement->quota > 0
        );
    }

    public static function canUseVoice(int $tenantId, string $voice): bool
    {
        $voices = config('voice_catalog', []);
        if (!isset($voices[$voice]) || empty($voices[$voice]['ready'])) return false;
        return ($voices[$voice]['tier'] ?? '') === 'basic'
            || self::has($tenantId, 'voice.'. $voice);
    }
}
