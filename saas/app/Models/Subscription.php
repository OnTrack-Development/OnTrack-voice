<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = ['tenant_id', 'plan_id', 'status', 'started_at', 'renews_at'];
    protected $casts = ['started_at' => 'datetime', 'renews_at' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
}