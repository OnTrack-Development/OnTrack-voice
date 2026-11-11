<?php

namespace App\Models;

use App\Support\VoiceDncBridge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceDncEntry extends Model
{
    protected $fillable = ['tenant_id','account_id','phone_normalized','reason','source','created_by'];

    protected static function booted(): void
    {
        static::created(static function (self $entry): void {
            // Best effort only: the Laravel row is durable even if Console is offline.
            VoiceDncBridge::mirrorToConsole($entry);
        });
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
