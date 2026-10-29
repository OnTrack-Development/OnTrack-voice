<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAuditEvent extends Model
{
    protected $fillable = ['tenant_id','actor_user_id','action','resource_type','resource_id','redacted_context','trace_id','occurred_at'];
    protected $casts = ['redacted_context'=>'array','occurred_at'=>'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
}