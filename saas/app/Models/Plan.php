<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['name', 'status', 'monthly_quota_credits', 'price_minor', 'currency', 'billing_interval'];
    protected $casts = ['price_minor' => 'integer'];
    public function entitlements(): HasMany { return $this->hasMany(PlanEntitlement::class); }
}