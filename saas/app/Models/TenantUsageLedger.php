<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUsageLedger extends Model
{
    protected $fillable = ['tenant_id', 'kind', 'units', 'ref_type', 'ref_id', 'idempotency_key', 'occurred_at'];
    protected $casts = ['occurred_at' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}