<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ButtonPresentation extends Model
{
    protected $fillable = ['tenant_id','button_rule_id','subject_key','state_revision','presentation_token_hash','expires_at'];
    protected $casts = ['expires_at' => 'datetime'];
    public function rule(): BelongsTo { return $this->belongsTo(ButtonRule::class, 'button_rule_id'); }
}