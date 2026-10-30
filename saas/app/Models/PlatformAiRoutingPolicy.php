<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAiRoutingPolicy extends Model
{
    protected $fillable = ['plan_id','feature','risk_class','writer_model_id','reviewer_model_id','fallback_model_id','config_json'];
    protected $casts = ['config_json' => 'array'];
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
}