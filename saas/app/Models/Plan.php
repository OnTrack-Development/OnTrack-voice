<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'kind', 'name', 'status', 'monthly_quota_credits',
        'call_quota', 'site_limit', 'agent_limit', 'staff_limit',
        'reseller_customers', 'price_minor', 'overage_minor',
        'google_key_limit', 'assistant_key_limit', 'currency', 'billing_interval',
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'call_quota' => 'integer',
        'site_limit' => 'integer',
        'agent_limit' => 'integer',
        'staff_limit' => 'integer',
    ];

    public function entitlements(): HasMany
    {
        return $this->hasMany(PlanEntitlement::class);
    }
}
