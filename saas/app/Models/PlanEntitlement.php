<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanEntitlement extends Model
{
    protected $fillable = ['plan_id', 'feature', 'unit', 'quota'];
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
}