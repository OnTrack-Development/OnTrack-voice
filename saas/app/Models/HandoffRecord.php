<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandoffRecord extends Model
{
    protected $fillable = ['tenant_id','conversation_id','reason','opened_by','closed_by','opened_at','closed_at'];
    protected $casts = ['opened_at' => 'datetime', 'closed_at' => 'datetime'];
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
}