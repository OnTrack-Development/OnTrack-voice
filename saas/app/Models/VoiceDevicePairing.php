<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceDevicePairing extends Model
{
    protected $fillable = [
        'tenant_id','account_id','device_id','pairing_token_hash','status','last_seen_at',
    ];
    protected $hidden = ['pairing_token_hash'];
    protected $casts = ['last_seen_at'=>'datetime'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
    public static function activeForTenant(int $tenantId, string $deviceId): ?self
    {
        return self::query()->forTenant($tenantId)
            ->where('device_id', $deviceId)->where('status', 'active')->first();
    }
}
