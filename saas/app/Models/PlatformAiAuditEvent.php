<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAiAuditEvent extends Model
{
    protected $fillable = [
        'actor_user_id', 'action', 'resource_type', 'resource_id', 'redacted_summary',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}