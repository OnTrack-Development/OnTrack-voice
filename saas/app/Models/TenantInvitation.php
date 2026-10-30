<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantInvitation extends Model
{
    protected $fillable = ['tenant_id','workspace_id','email','role','token_hash','expires_at','accepted_at','created_by'];
    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function isPending(): bool { return $this->accepted_at === null && $this->expires_at->isFuture(); }
}